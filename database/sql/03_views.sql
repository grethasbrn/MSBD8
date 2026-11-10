-- =====================================================================
-- 03_views.sql  -  View (tabel virtual) untuk dashboard & laporan
-- =====================================================================

-- Ringkasan angka di dashboard Owner (1 baris)
CREATE VIEW v_owner_ringkasan AS
SELECT s.*,
       s.pendapatan_acara + s.pendapatan_kompensasi          AS total_pendapatan,
       s.pendapatan_acara + s.pendapatan_kompensasi - s.pengeluaran_honor AS saldo_bersih
FROM (
  SELECT
    COALESCE((SELECT SUM(p.amount) FROM payments p JOIN orders o ON o.id = p.order_id
               WHERE p.status = 'verified' AND o.status <> 'cancelled'), 0)       AS pendapatan_acara,
    COALESCE((SELECT SUM(compensation_amount) FROM orders WHERE status = 'cancelled'), 0)
                                                                                  AS pendapatan_kompensasi,
    COALESCE((SELECT SUM(amount) FROM honorariums), 0)                            AS pengeluaran_honor,
    (SELECT COUNT(*) FROM orders WHERE status = 'pending_verification')           AS jumlah_pending,
    (SELECT COUNT(*) FROM orders WHERE status = 'approved')                       AS jumlah_approved,
    (SELECT COUNT(*) FROM orders WHERE status = 'cancelled')                      AS jumlah_cancelled,
    (SELECT COUNT(*) FROM orders WHERE status = 'completed')                      AS jumlah_completed,
    (SELECT COUNT(*) FROM payments WHERE status = 'pending')                      AS pembayaran_menunggu_verifikasi
) s;

-- Grafik arus kas per bulan (zona waktu WIB)
CREATE VIEW v_arus_kas_bulanan AS
WITH masuk AS (
  SELECT date_trunc('month', p.verified_at AT TIME ZONE 'Asia/Jakarta')::date AS bulan,
         SUM(p.amount) AS pemasukan
  FROM payments p WHERE p.status = 'verified' GROUP BY 1
), keluar AS (
  SELECT date_trunc('month', h.paid_at AT TIME ZONE 'Asia/Jakarta')::date AS bulan,
         SUM(h.amount) AS pengeluaran
  FROM honorariums h GROUP BY 1
)
SELECT COALESCE(m.bulan, k.bulan)                              AS bulan,
       COALESCE(m.pemasukan, 0)                                AS pemasukan,
       COALESCE(k.pengeluaran, 0)                              AS pengeluaran,
       COALESCE(m.pemasukan, 0) - COALESCE(k.pengeluaran, 0)   AS arus_bersih
FROM masuk m FULL JOIN keluar k ON k.bulan = m.bulan;

-- Daftar pesanan lengkap dengan pelanggan, paket, Korlap, dan sisa tagihan
CREATE VIEW v_order_ringkas AS
SELECT o.id, o.code, o.status, o.event_date, o.event_time, o.event_location,
       o.total_amount, o.min_dp_amount, o.compensation_amount, o.lunas_at, o.created_at,
       COALESCE(pay.dibayar, 0)                      AS total_dibayar,
       o.total_amount - COALESCE(pay.dibayar, 0)     AS sisa_tagihan,
       c.id   AS customer_id,  c.name AS customer_name, c.phone AS customer_phone,
       pk.id  AS package_id,   pk.name AS package_name,
       k.korlap_id, k.korlap_name
FROM orders o
JOIN users c     ON c.id  = o.customer_id
JOIN packages pk ON pk.id = o.package_id
LEFT JOIN LATERAL (
  SELECT SUM(p.amount) AS dibayar FROM payments p WHERE p.order_id = o.id AND p.status = 'verified'
) pay ON TRUE
LEFT JOIN LATERAL (
  SELECT u.id AS korlap_id, u.name AS korlap_name
  FROM event_assignments ea JOIN users u ON u.id = ea.user_id
  WHERE ea.order_id = o.id AND ea.role = 'korlap' AND ea.status IN ('ditawarkan', 'diterima')
  LIMIT 1
) k ON TRUE;
