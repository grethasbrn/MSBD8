-- =====================================================================
-- 02_functions_triggers.sql  -  Aturan bisnis yang dijaga langsung oleh database
-- Semua pesan error ditulis dalam Bahasa Indonesia; Laravel menampilkannya apa adanya.
-- =====================================================================

-- ---------- Fungsi bantu ----------
CREATE FUNCTION fn_today_wib() RETURNS date
LANGUAGE sql STABLE AS $$ SELECT (now() AT TIME ZONE 'Asia/Jakarta')::date $$;

CREATE FUNCTION fn_setting(p_key text) RETURNS text
LANGUAGE sql STABLE AS $$ SELECT value FROM app_settings WHERE key = p_key $$;

-- updated_at otomatis
CREATE FUNCTION trg_set_updated_at() RETURNS trigger
LANGUAGE plpgsql AS $$
BEGIN
  NEW.updated_at := now();
  RETURN NEW;
END $$;

DO $$
DECLARE t text;
BEGIN
  FOREACH t IN ARRAY ARRAY['users','packages','addons','inventory_items','gallery_items',
                           'app_settings','honor_rates','orders','payments','daily_quota',
                           'event_assignments']
  LOOP
    EXECUTE format('CREATE TRIGGER %I BEFORE UPDATE ON %I FOR EACH ROW EXECUTE FUNCTION trg_set_updated_at()',
                   'trg_' || t || '_updated_at', t);
  END LOOP;
END $$;

-- ---------- Hitung biaya: total & minimal DP ----------
CREATE FUNCTION fn_hitung_biaya(p_package_id bigint, p_addon_ids bigint[] DEFAULT '{}')
RETURNS TABLE (package_price numeric, addons_total numeric, total_amount numeric, min_dp_amount numeric)
LANGUAGE plpgsql STABLE AS $$
DECLARE
  v_pkg    numeric;
  v_addons numeric;
  v_ids    bigint[];
  v_found  int;
  v_pct    numeric;
BEGIN
  SELECT p.price INTO v_pkg FROM packages p WHERE p.id = p_package_id AND p.is_active;
  IF NOT FOUND THEN
    RAISE EXCEPTION 'Paket tidak ditemukan atau sudah tidak aktif';
  END IF;

  SELECT COALESCE(array_agg(DISTINCT x), '{}') INTO v_ids FROM unnest(COALESCE(p_addon_ids, '{}')) x;

  SELECT COUNT(*), COALESCE(SUM(a.price), 0) INTO v_found, v_addons
  FROM addons a WHERE a.id = ANY (v_ids) AND a.is_active;

  IF v_found <> COALESCE(array_length(v_ids, 1), 0) THEN
    RAISE EXCEPTION 'Ada add-on yang tidak ditemukan atau sudah tidak aktif';
  END IF;

  v_pct := fn_setting('dp_percent')::numeric;
  package_price  := v_pkg;
  addons_total   := v_addons;
  total_amount   := v_pkg + v_addons;
  min_dp_amount  := CEIL(total_amount * v_pct / 100);
  RETURN NEXT;
END $$;

-- ---------- Kalender kuota publik ----------
CREATE FUNCTION fn_kalender(p_from date, p_to date)
RETURNS TABLE (event_date date, booked_slots int, max_slots int, available_slots int, is_full boolean)
LANGUAGE sql STABLE AS $$
  SELECT (p_from + i)::date,
         COALESCE(q.booked_slots, 0),
         m.v,
         GREATEST(m.v - COALESCE(q.booked_slots, 0), 0),
         COALESCE(q.booked_slots, 0) >= m.v
  FROM generate_series(0, p_to - p_from) AS i
  CROSS JOIN (SELECT fn_setting('max_events_per_day')::int AS v) m
  LEFT JOIN daily_quota q ON q.event_date = (p_from + i)::date
  ORDER BY 1
$$;

-- ---------- Kunci / lepas slot kuota (aman dari rebutan 2 pengguna) ----------
CREATE FUNCTION fn_quota_take(p_date date) RETURNS void
LANGUAGE plpgsql AS $$
DECLARE
  v_max    int := fn_setting('max_events_per_day')::int;
  v_booked int;
BEGIN
  INSERT INTO daily_quota (event_date, booked_slots) VALUES (p_date, 0)
  ON CONFLICT (event_date) DO NOTHING;

  SELECT booked_slots INTO v_booked FROM daily_quota WHERE event_date = p_date FOR UPDATE;  -- kunci baris

  IF v_booked >= v_max THEN
    RAISE EXCEPTION 'Kuota tanggal % sudah penuh (maksimal % acara per hari)',
                    to_char(p_date, 'DD-MM-YYYY'), v_max;
  END IF;

  UPDATE daily_quota SET booked_slots = booked_slots + 1 WHERE event_date = p_date;
END $$;

CREATE FUNCTION fn_quota_release(p_date date) RETURNS void
LANGUAGE plpgsql AS $$
BEGIN
  UPDATE daily_quota SET booked_slots = booked_slots - 1 WHERE event_date = p_date;
END $$;

-- =====================================================================
-- PESANAN
-- =====================================================================

-- Penjaga perubahan status + aturan DP hangus
CREATE FUNCTION trg_orders_guard() RETURNS trigger
LANGUAGE plpgsql AS $$
DECLARE
  v_dp numeric;
BEGIN
  IF NEW.status IS DISTINCT FROM OLD.status THEN
    -- alur status yang diperbolehkan
    IF NOT (
         (OLD.status = 'pending_verification' AND NEW.status IN ('approved', 'cancelled'))
      OR (OLD.status = 'approved'             AND NEW.status IN ('cancelled', 'completed'))
    ) THEN
      RAISE EXCEPTION 'Status pesanan tidak boleh berubah dari % ke %', OLD.status, NEW.status;
    END IF;

    IF NEW.status = 'approved' THEN
      IF NOT EXISTS (SELECT 1 FROM payments WHERE order_id = NEW.id AND type = 'dp' AND status = 'verified') THEN
        RAISE EXCEPTION 'DP belum diverifikasi, pesanan belum bisa disetujui';
      END IF;
      IF NEW.event_date < fn_today_wib() THEN
        RAISE EXCEPTION 'Tanggal acara sudah lewat, pesanan tidak bisa disetujui';
      END IF;
    END IF;

    IF NEW.status = 'cancelled' THEN
      IF OLD.lunas_at IS NOT NULL THEN
        RAISE EXCEPTION 'Pesanan yang sudah lunas tidak bisa dibatalkan';
      END IF;
      NEW.cancelled_at := COALESCE(NEW.cancelled_at, now());
      IF OLD.status = 'approved' THEN
        -- DP Non-Refundable: DP yang sudah sah menjadi Pendapatan Kompensasi
        SELECT COALESCE(SUM(amount), 0) INTO v_dp
        FROM payments WHERE order_id = NEW.id AND type = 'dp' AND status = 'verified';
        NEW.compensation_amount := v_dp;
      END IF;
    END IF;

    IF NEW.status = 'completed' THEN
      IF NEW.event_date > fn_today_wib() THEN
        RAISE EXCEPTION 'Acara belum berlangsung, status belum bisa diubah menjadi Completed';
      END IF;
      NEW.completed_at := COALESCE(NEW.completed_at, now());
    END IF;
  END IF;

  -- Data inti terkunci setelah pesanan disetujui
  IF OLD.status <> 'pending_verification' AND (
         NEW.event_date    IS DISTINCT FROM OLD.event_date
      OR NEW.package_id    IS DISTINCT FROM OLD.package_id
      OR NEW.total_amount  IS DISTINCT FROM OLD.total_amount
  ) THEN
    RAISE EXCEPTION 'Tanggal, paket, dan total biaya terkunci setelah pesanan disetujui';
  END IF;

  RETURN NEW;
END $$;

CREATE TRIGGER trg_orders_guard BEFORE UPDATE ON orders
FOR EACH ROW EXECUTE FUNCTION trg_orders_guard();

-- Efek samping setelah status berubah: kuota, penugasan, pembayaran menggantung, log
CREATE FUNCTION trg_orders_after_status() RETURNS trigger
LANGUAGE plpgsql AS $$
BEGIN
  IF NEW.status = 'approved' THEN
    PERFORM fn_quota_take(NEW.event_date);                    -- kunci 1 slot
  END IF;

  IF NEW.status = 'cancelled' THEN
    IF OLD.status = 'approved' THEN
      PERFORM fn_quota_release(NEW.event_date);               -- buka lagi 1 slot (+1)
    END IF;
    UPDATE event_assignments SET status = 'dibatalkan'
     WHERE order_id = NEW.id AND status IN ('ditawarkan', 'diterima');
    UPDATE payments SET status = 'rejected', reject_reason = 'Pesanan dibatalkan'
     WHERE order_id = NEW.id AND status = 'pending';
  END IF;

  INSERT INTO order_status_logs (order_id, from_status, to_status, changed_by, note)
  VALUES (NEW.id, OLD.status, NEW.status,
          NULLIF(current_setting('app.user_id', true), '')::bigint,
          NULLIF(current_setting('app.note', true), ''));
  RETURN NEW;
END $$;

CREATE TRIGGER trg_orders_after_status AFTER UPDATE OF status ON orders
FOR EACH ROW WHEN (OLD.status IS DISTINCT FROM NEW.status)
EXECUTE FUNCTION trg_orders_after_status();

CREATE FUNCTION trg_orders_after_insert() RETURNS trigger
LANGUAGE plpgsql AS $$
BEGIN
  INSERT INTO order_status_logs (order_id, from_status, to_status, changed_by)
  VALUES (NEW.id, NULL, NEW.status, NULLIF(current_setting('app.user_id', true), '')::bigint);
  RETURN NEW;
END $$;

CREATE TRIGGER trg_orders_after_insert AFTER INSERT ON orders
FOR EACH ROW EXECUTE FUNCTION trg_orders_after_insert();

-- =====================================================================
-- PEMBAYARAN
-- =====================================================================
CREATE FUNCTION trg_payments_guard() RETURNS trigger
LANGUAGE plpgsql AS $$
DECLARE
  o    orders%ROWTYPE;
  v_dp numeric;
BEGIN
  IF TG_OP = 'UPDATE' THEN
    IF OLD.status = 'verified' THEN
      RAISE EXCEPTION 'Pembayaran yang sudah terverifikasi tidak dapat diubah';
    END IF;
    RETURN NEW;
  END IF;

  -- INSERT
  SELECT * INTO o FROM orders WHERE id = NEW.order_id;
  IF o.status = 'cancelled' THEN
    RAISE EXCEPTION 'Pesanan sudah dibatalkan, pembayaran tidak dapat dicatat';
  END IF;

  IF NEW.type = 'dp' THEN
    IF o.status <> 'pending_verification' THEN
      RAISE EXCEPTION 'DP hanya bisa diunggah saat pesanan masih menunggu verifikasi';
    END IF;
    IF NEW.amount < o.min_dp_amount THEN
      RAISE EXCEPTION 'Nominal DP kurang dari minimal (Rp %)', o.min_dp_amount::bigint;
    END IF;
  ELSE
    IF o.status NOT IN ('approved', 'completed') THEN
      RAISE EXCEPTION 'Pelunasan hanya bisa dilakukan untuk pesanan yang sudah disetujui';
    END IF;
    SELECT COALESCE(SUM(amount), 0) INTO v_dp
      FROM payments WHERE order_id = NEW.order_id AND type = 'dp' AND status = 'verified';
    IF NEW.amount < o.total_amount - v_dp THEN
      RAISE EXCEPTION 'Nominal pelunasan kurang dari sisa tagihan (Rp %)', (o.total_amount - v_dp)::bigint;
    END IF;
  END IF;
  RETURN NEW;
END $$;

CREATE TRIGGER trg_payments_guard BEFORE INSERT OR UPDATE ON payments
FOR EACH ROW EXECUTE FUNCTION trg_payments_guard();

-- Tandai Lunas otomatis saat total pembayaran sah >= total tagihan
CREATE FUNCTION trg_payments_after() RETURNS trigger
LANGUAGE plpgsql AS $$
BEGIN
  IF NEW.status = 'verified' THEN
    UPDATE orders SET lunas_at = now()
     WHERE id = NEW.order_id
       AND lunas_at IS NULL
       AND (SELECT COALESCE(SUM(amount), 0) FROM payments
             WHERE order_id = NEW.order_id AND status = 'verified') >= total_amount;
  END IF;
  RETURN NEW;
END $$;

CREATE TRIGGER trg_payments_after AFTER INSERT OR UPDATE ON payments
FOR EACH ROW EXECUTE FUNCTION trg_payments_after();

-- =====================================================================
-- PENUGASAN TIM
-- =====================================================================
CREATE FUNCTION trg_assign_before_insert() RETURNS trigger
LANGUAGE plpgsql AS $$
DECLARE
  o orders%ROWTYPE;
  u users%ROWTYPE;
BEGIN
  SELECT * INTO o FROM orders WHERE id = NEW.order_id;
  IF o.status <> 'approved' THEN
    RAISE EXCEPTION 'Penugasan hanya bisa dibuat untuk pesanan berstatus Approved';
  END IF;
  NEW.event_date := o.event_date;

  SELECT * INTO u FROM users WHERE id = NEW.user_id;
  IF NOT u.is_active THEN
    RAISE EXCEPTION '% sudah tidak aktif', u.name;
  END IF;
  IF u.role::text <> NEW.role::text THEN
    RAISE EXCEPTION '% tidak terdaftar sebagai %', u.name, NEW.role;
  END IF;

  IF NEW.status IN ('ditawarkan', 'diterima') THEN
    IF EXISTS (SELECT 1 FROM event_assignments
                WHERE user_id = NEW.user_id AND event_date = NEW.event_date
                  AND status IN ('ditawarkan', 'diterima')) THEN
      RAISE EXCEPTION '% sudah punya penugasan lain pada tanggal tersebut', u.name;
    END IF;
    IF NEW.role = 'korlap' AND EXISTS (SELECT 1 FROM event_assignments
                WHERE order_id = NEW.order_id AND role = 'korlap'
                  AND status IN ('ditawarkan', 'diterima')) THEN
      RAISE EXCEPTION 'Pesanan ini sudah memiliki Korlap';
    END IF;
  END IF;
  RETURN NEW;
END $$;

CREATE TRIGGER trg_assign_before_insert BEFORE INSERT ON event_assignments
FOR EACH ROW EXECUTE FUNCTION trg_assign_before_insert();

CREATE FUNCTION trg_assign_before_update() RETURNS trigger
LANGUAGE plpgsql AS $$
BEGIN
  IF NEW.status IS DISTINCT FROM OLD.status THEN
    IF NOT (
         (OLD.status = 'ditawarkan' AND NEW.status IN ('diterima', 'ditolak', 'dibatalkan'))
      OR (OLD.status = 'diterima'   AND NEW.status = 'dibatalkan')
    ) THEN
      RAISE EXCEPTION 'Status penugasan tidak boleh berubah dari % ke %', OLD.status, NEW.status;
    END IF;
    IF NEW.status IN ('diterima', 'ditolak') THEN
      NEW.responded_at := COALESCE(NEW.responded_at, now());
    END IF;
  END IF;

  IF NEW.attended_at IS NOT NULL AND OLD.attended_at IS NULL THEN
    IF NEW.event_date > fn_today_wib() THEN
      RAISE EXCEPTION 'Presensi baru bisa dicatat pada hari acara';
    END IF;
  END IF;
  RETURN NEW;
END $$;

CREATE TRIGGER trg_assign_before_update BEFORE UPDATE ON event_assignments
FOR EACH ROW EXECUTE FUNCTION trg_assign_before_update();

-- =====================================================================
-- PEMINJAMAN BARANG  (status barang di katalog ikut berubah otomatis)
-- =====================================================================
CREATE FUNCTION trg_loans_sync() RETURNS trigger
LANGUAGE plpgsql AS $$
BEGIN
  IF TG_OP = 'INSERT' THEN
    UPDATE inventory_items SET status = 'dipinjam' WHERE id = NEW.item_id;
  ELSE
    IF OLD.returned_at IS NOT NULL THEN
      RAISE EXCEPTION 'Barang ini sudah tercatat dikembalikan';
    END IF;
    IF NEW.returned_at IS NOT NULL THEN
      UPDATE inventory_items
         SET status = CASE NEW.return_condition
                        WHEN 'baik'  THEN 'tersedia'::item_status
                        WHEN 'rusak' THEN 'rusak'::item_status
                        ELSE              'hilang'::item_status END
       WHERE id = NEW.item_id;
    END IF;
  END IF;
  RETURN NEW;
END $$;

CREATE TRIGGER trg_loans_sync AFTER INSERT OR UPDATE ON item_loans
FOR EACH ROW EXECUTE FUNCTION trg_loans_sync();

-- Pengecekan SEBELUM barang keluar: pesanan harus Approved dan barang harus Tersedia
CREATE FUNCTION trg_loans_before_insert() RETURNS trigger
LANGUAGE plpgsql AS $$
DECLARE
  v_status item_status;
  v_name   text;
  v_order  order_status;
BEGIN
  SELECT status INTO v_order FROM orders WHERE id = NEW.order_id;
  IF v_order <> 'approved' THEN
    RAISE EXCEPTION 'Barang hanya bisa dipinjam untuk pesanan berstatus Approved';
  END IF;
  SELECT status, name INTO v_status, v_name FROM inventory_items WHERE id = NEW.item_id FOR UPDATE;
  IF v_status <> 'tersedia' THEN
    RAISE EXCEPTION 'Barang "%" tidak tersedia (status: %)', v_name, v_status;
  END IF;
  RETURN NEW;
END $$;

CREATE TRIGGER trg_loans_before_insert BEFORE INSERT ON item_loans
FOR EACH ROW EXECUTE FUNCTION trg_loans_before_insert();

-- =====================================================================
-- PENCAIRAN HONOR  (dipanggil Owner: SELECT * FROM fn_cairkan_honor(order_id, owner_id))
-- =====================================================================
CREATE FUNCTION fn_cairkan_honor(p_order_id bigint, p_owner_id bigint)
RETURNS TABLE (jumlah_penerima int, total_honor numeric)
LANGUAGE plpgsql AS $$
DECLARE
  o orders%ROWTYPE;
BEGIN
  IF NOT EXISTS (SELECT 1 FROM users WHERE id = p_owner_id AND role = 'owner') THEN
    RAISE EXCEPTION 'Hanya Owner yang dapat mencairkan honor';
  END IF;

  SELECT * INTO o FROM orders WHERE id = p_order_id FOR UPDATE;
  IF NOT FOUND THEN
    RAISE EXCEPTION 'Pesanan tidak ditemukan';
  END IF;
  IF o.status <> 'completed' THEN
    RAISE EXCEPTION 'Honor hanya bisa dicairkan setelah acara berstatus Completed';
  END IF;
  IF o.lunas_at IS NULL THEN
    RAISE EXCEPTION 'Honor hanya bisa dicairkan setelah pesanan Lunas';
  END IF;
  IF EXISTS (SELECT 1 FROM honorariums h JOIN event_assignments ea ON ea.id = h.assignment_id
              WHERE ea.order_id = p_order_id) THEN
    RAISE EXCEPTION 'Honor untuk pesanan ini sudah pernah dicairkan';
  END IF;
  IF NOT EXISTS (SELECT 1 FROM event_assignments
                  WHERE order_id = p_order_id AND status = 'diterima' AND attended_at IS NOT NULL) THEN
    RAISE EXCEPTION 'Belum ada presensi tim untuk acara ini';
  END IF;

  INSERT INTO honorariums (assignment_id, amount, paid_by, paid_at)
  SELECT ea.id, r.amount, p_owner_id, now()
    FROM event_assignments ea
    JOIN honor_rates r ON r.role = ea.role
   WHERE ea.order_id = p_order_id AND ea.status = 'diterima' AND ea.attended_at IS NOT NULL;

  RETURN QUERY
  SELECT COUNT(*)::int, COALESCE(SUM(h.amount), 0)
    FROM honorariums h JOIN event_assignments ea ON ea.id = h.assignment_id
   WHERE ea.order_id = p_order_id;
END $$;
