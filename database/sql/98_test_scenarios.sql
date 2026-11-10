-- =====================================================================
-- 98_test_scenarios.sql - Uji otomatis aturan bisnis database
-- Cara pakai:  psql -d NAMA_DB -f database/sql/98_test_scenarios.sql
-- Semua berjalan di dalam transaksi dan di-ROLLBACK: data asli tidak tersentuh.
-- Setiap baris "OK" berarti aturan bekerja; jika ada yang gagal, skrip berhenti dengan error.
-- =====================================================================
BEGIN;

-- helper: pastikan sebuah perintah SQL GAGAL dengan pesan yang mengandung teks tertentu
CREATE FUNCTION pg_temp.harus_gagal(p_sql text, p_pesan text, p_label text) RETURNS void
LANGUAGE plpgsql AS $$
BEGIN
  BEGIN
    EXECUTE p_sql;
  EXCEPTION WHEN OTHERS THEN
    IF position(p_pesan in SQLERRM) > 0 THEN
      RAISE NOTICE 'OK  (ditolak) %', p_label;
      RETURN;
    END IF;
    RAISE EXCEPTION 'GAGAL % : error berbeda dari yang diharapkan: %', p_label, SQLERRM;
  END;
  RAISE EXCEPTION 'GAGAL % : seharusnya ditolak tetapi lolos', p_label;
END $$;

CREATE FUNCTION pg_temp.cek(p_ok boolean, p_label text) RETURNS void
LANGUAGE plpgsql AS $$
BEGIN
  IF NOT p_ok THEN RAISE EXCEPTION 'GAGAL %', p_label; END IF;
  RAISE NOTICE 'OK  %', p_label;
END $$;

DO $$
DECLARE
  hari   date := fn_today_wib();
  v_owner bigint; v_korlap bigint; v_korlap2 bigint; v_helper bigint; v_penari bigint; v_penari2 bigint;
  v_pemusik bigint; v_cust bigint;
  v_pkg bigint; v_ad1 bigint; v_ad2 bigint; v_item bigint; v_item2 bigint;
  o1 bigint; o2 bigint; o3 bigint; o4 bigint;
  p1 bigint; pl bigint;
  a_korlap bigint; a_penari bigint; a_helper bigint; a_pemusik bigint;
  loan1 bigint;
  r record;
BEGIN
  -- ---------- data dasar ----------
  INSERT INTO users(name,email,phone,password,role) VALUES ('Owner','o@t.id','6281','x','owner') RETURNING id INTO v_owner;
  INSERT INTO users(name,email,phone,password,role) VALUES ('Korlap A','k@t.id','6282','x','korlap') RETURNING id INTO v_korlap;
  INSERT INTO users(name,email,phone,password,role) VALUES ('Korlap B','k2@t.id','6283','x','korlap') RETURNING id INTO v_korlap2;
  INSERT INTO users(name,email,phone,password,role) VALUES ('Helper','h@t.id','6284','x','helper') RETURNING id INTO v_helper;
  INSERT INTO users(name,email,phone,password,role) VALUES ('Penari 1','p@t.id','6285','x','penari') RETURNING id INTO v_penari;
  INSERT INTO users(name,email,phone,password,role) VALUES ('Penari 2','p2@t.id','6286','x','penari') RETURNING id INTO v_penari2;
  INSERT INTO users(name,email,phone,password,role,specialization) VALUES ('Pemusik','m@t.id','6287','x','pemusik','Kendang') RETURNING id INTO v_pemusik;
  INSERT INTO users(name,email,phone,password,role) VALUES ('Pelanggan','c@t.id','6288','x','customer') RETURNING id INTO v_cust;

  INSERT INTO packages(name,category,price,dancers_needed,musicians_needed) VALUES ('Tari Sambut','tari',1500000,5,3) RETURNING id INTO v_pkg;
  INSERT INTO addons(name,price,extra_dancers) VALUES ('+2 Penari',100000,2) RETURNING id INTO v_ad1;
  INSERT INTO addons(name,price) VALUES ('+1 Tari Ekstra',200000) RETURNING id INTO v_ad2;
  INSERT INTO inventory_items(code,name,category) VALUES ('KST-001','Kostum Sambut A','kostum') RETURNING id INTO v_item;
  INSERT INTO inventory_items(code,name,category) VALUES ('ALT-001','Kendang','alat_musik') RETURNING id INTO v_item2;

  -- ---------- hitung biaya ----------
  SELECT * INTO r FROM fn_hitung_biaya(v_pkg, ARRAY[v_ad1, v_ad2]);
  PERFORM pg_temp.cek(r.total_amount = 1800000 AND r.min_dp_amount = 540000, 'hitung biaya: 1.5jt + 100rb + 200rb = 1.8jt, DP 30% = 540rb');
  SELECT * INTO r FROM fn_hitung_biaya(v_pkg, '{}');
  PERFORM pg_temp.cek(r.total_amount = 1500000 AND r.min_dp_amount = 450000, 'hitung biaya tanpa add-on');
  PERFORM pg_temp.harus_gagal(format('SELECT * FROM fn_hitung_biaya(%s, ARRAY[999999])', v_pkg), 'add-on', 'add-on tidak dikenal');
  PERFORM pg_temp.harus_gagal('SELECT * FROM fn_hitung_biaya(999999, ''{}'')', 'Paket tidak ditemukan', 'paket tidak dikenal');

  -- ---------- buat pesanan ----------
  INSERT INTO orders(customer_id,package_id,event_date,event_location,package_price,addons_total,total_amount,min_dp_amount)
  VALUES (v_cust,v_pkg,hari,'Jl. Contoh No. 1',1500000,300000,1800000,540000) RETURNING id INTO o1;
  PERFORM pg_temp.cek((SELECT code ~ '^SNG-[0-9]{6}-[0-9]{4}$' FROM orders WHERE id=o1), 'ID Transaksi otomatis berformat SNG-YYMMDD-0001');
  PERFORM pg_temp.cek((SELECT count(*)=1 FROM order_status_logs WHERE order_id=o1), 'log status awal tercatat');

  PERFORM pg_temp.harus_gagal(format('INSERT INTO orders(customer_id,package_id,event_date,event_location,package_price,addons_total,total_amount,min_dp_amount) VALUES (%s,%s,%L,''x'',100,0,999,10)', v_cust, v_pkg, hari),
    'chk_orders_total', 'total tidak sama dengan paket + add-on');

  -- ---------- DP ----------
  PERFORM pg_temp.harus_gagal(format('INSERT INTO payments(order_id,type,amount,proof_path) VALUES (%s,''dp'',100000,''a.jpg'')', o1),
    'Nominal DP kurang', 'DP di bawah minimal 30%');
  PERFORM pg_temp.harus_gagal(format('INSERT INTO payments(order_id,type,amount) VALUES (%s,''dp'',540000)', o1),
    'chk_payments_proof', 'transfer tanpa bukti');
  INSERT INTO payments(order_id,type,amount,proof_path) VALUES (o1,'dp',540000,'proofs/a.jpg') RETURNING id INTO p1;
  PERFORM pg_temp.harus_gagal(format('INSERT INTO payments(order_id,type,amount,proof_path) VALUES (%s,''dp'',540000,''b.jpg'')', o1),
    'payments_one_pending_uq', 'DP ganda yang masih menunggu');

  -- approve tanpa DP sah ditolak
  PERFORM pg_temp.harus_gagal(format('UPDATE orders SET status=''approved'' WHERE id=%s', o1), 'DP belum diverifikasi', 'approve sebelum DP diverifikasi');

  -- ---------- Approve DP ----------
  PERFORM set_config('app.user_id', v_owner::text, true);
  UPDATE payments SET status='verified', verified_by=v_owner, verified_at=now() WHERE id=p1;
  UPDATE orders SET status='approved' WHERE id=o1;
  PERFORM pg_temp.cek((SELECT booked_slots=1 FROM daily_quota WHERE event_date=hari), 'Approve DP mengunci 1 slot kuota');
  PERFORM pg_temp.cek((SELECT changed_by=v_owner FROM order_status_logs WHERE order_id=o1 AND to_status='approved'), 'log status mencatat siapa yang menyetujui');
  PERFORM pg_temp.harus_gagal(format('UPDATE payments SET amount=1 WHERE id=%s', p1), 'sudah terverifikasi', 'pembayaran sah tidak bisa diubah');
  PERFORM pg_temp.harus_gagal(format('UPDATE orders SET event_date=event_date+1 WHERE id=%s', o1), 'terkunci', 'tanggal terkunci setelah approved');

  -- ---------- Kuota maksimal 3 / hari ----------
  FOR i IN 1..2 LOOP
    INSERT INTO orders(customer_id,package_id,event_date,event_location,package_price,addons_total,total_amount,min_dp_amount)
    VALUES (v_cust,v_pkg,hari,'Lokasi',1500000,0,1500000,450000) RETURNING id INTO o2;
    INSERT INTO payments(order_id,type,amount,proof_path) VALUES (o2,'dp',450000,'p.jpg') RETURNING id INTO pl;
    UPDATE payments SET status='verified', verified_by=v_owner, verified_at=now() WHERE id=pl;
    UPDATE orders SET status='approved' WHERE id=o2;
  END LOOP;
  PERFORM pg_temp.cek((SELECT booked_slots=3 FROM daily_quota WHERE event_date=hari), 'kuota terisi 3 dari 3');
  SELECT * INTO r FROM fn_kalender(hari, hari);
  PERFORM pg_temp.cek(r.is_full AND r.available_slots=0, 'kalender publik menandai tanggal penuh');

  INSERT INTO orders(customer_id,package_id,event_date,event_location,package_price,addons_total,total_amount,min_dp_amount)
  VALUES (v_cust,v_pkg,hari,'Lokasi',1500000,0,1500000,450000) RETURNING id INTO o3;
  INSERT INTO payments(order_id,type,amount,proof_path) VALUES (o3,'dp',450000,'p.jpg') RETURNING id INTO pl;
  UPDATE payments SET status='verified', verified_by=v_owner, verified_at=now() WHERE id=pl;
  PERFORM pg_temp.harus_gagal(format('UPDATE orders SET status=''approved'' WHERE id=%s', o3), 'Kuota tanggal', 'pesanan ke-4 di tanggal penuh ditolak');

  -- ---------- Pembatalan: DP hangus + slot dibuka ----------
  UPDATE orders SET status='cancelled', cancel_reason='Pelanggan batal' WHERE id=o2;
  PERFORM pg_temp.cek((SELECT booked_slots=2 FROM daily_quota WHERE event_date=hari), 'pembatalan membuka kembali 1 slot (+1)');
  PERFORM pg_temp.cek((SELECT compensation_amount=450000 FROM orders WHERE id=o2), 'DP menjadi Pendapatan Kompensasi (DP hangus)');
  UPDATE orders SET status='approved' WHERE id=o3;   -- sekarang ada slot kosong
  PERFORM pg_temp.cek((SELECT booked_slots=3 FROM daily_quota WHERE event_date=hari), 'slot yang dibuka bisa dipakai pesanan lain');
  PERFORM pg_temp.harus_gagal(format('UPDATE orders SET status=''approved'' WHERE id=%s', o2), 'tidak boleh berubah', 'status cancelled tidak bisa dihidupkan lagi');

  -- pesanan pending yang dibatalkan tidak menghasilkan kompensasi
  INSERT INTO orders(customer_id,package_id,event_date,event_location,package_price,addons_total,total_amount,min_dp_amount)
  VALUES (v_cust,v_pkg,hari+5,'Lokasi',1500000,0,1500000,450000) RETURNING id INTO o4;
  INSERT INTO payments(order_id,type,amount,proof_path) VALUES (o4,'dp',450000,'p.jpg');
  UPDATE orders SET status='cancelled' WHERE id=o4;
  PERFORM pg_temp.cek((SELECT compensation_amount=0 FROM orders WHERE id=o4), 'batal sebelum DP disetujui: tanpa kompensasi');
  PERFORM pg_temp.cek((SELECT status='rejected' FROM payments WHERE order_id=o4), 'bukti DP yang menggantung otomatis ditutup');
  PERFORM pg_temp.harus_gagal(format('INSERT INTO payments(order_id,type,amount,proof_path) VALUES (%s,''dp'',450000,''z.jpg'')', o4), 'dibatalkan', 'tidak bisa bayar pesanan yang dibatalkan');

  -- ---------- Penugasan tim ----------
  INSERT INTO event_assignments(order_id,user_id,role,status,assigned_by) VALUES (o1,v_korlap,'korlap','diterima',v_owner) RETURNING id INTO a_korlap;
  PERFORM pg_temp.cek((SELECT event_date=hari FROM event_assignments WHERE id=a_korlap), 'tanggal penugasan disalin otomatis dari pesanan');
  PERFORM pg_temp.harus_gagal(format('INSERT INTO event_assignments(order_id,user_id,role,status) VALUES (%s,%s,''korlap'',''diterima'')', o1, v_korlap2), 'sudah memiliki Korlap', 'satu pesanan hanya 1 Korlap');
  PERFORM pg_temp.harus_gagal(format('INSERT INTO event_assignments(order_id,user_id,role) VALUES (%s,%s,''penari'')', o1, v_pemusik), 'tidak terdaftar sebagai', 'peran tidak sesuai (pemusik dijadikan penari)');
  PERFORM pg_temp.harus_gagal(format('INSERT INTO event_assignments(order_id,user_id,role) VALUES (%s,%s,''penari'')', o4, v_penari), 'Approved', 'penugasan di pesanan yang belum Approved');

  INSERT INTO event_assignments(order_id,user_id,role,assigned_by) VALUES (o1,v_penari,'penari',v_korlap) RETURNING id INTO a_penari;
  INSERT INTO event_assignments(order_id,user_id,role,assigned_by) VALUES (o1,v_helper,'helper',v_korlap) RETURNING id INTO a_helper;
  INSERT INTO event_assignments(order_id,user_id,role,instrument,assigned_by) VALUES (o1,v_pemusik,'pemusik','Kendang',v_korlap) RETURNING id INTO a_pemusik;
  -- penari yang sama di acara lain pada tanggal sama
  SELECT id INTO o2 FROM orders WHERE status='approved' AND event_date=hari AND id<>o1 ORDER BY id LIMIT 1;
  PERFORM pg_temp.harus_gagal(format('INSERT INTO event_assignments(order_id,user_id,role) VALUES (%s,%s,''penari'')', o2, v_penari), 'penugasan lain', 'penari tidak boleh dobel jadwal di tanggal sama');

  -- terima / tolak
  PERFORM pg_temp.harus_gagal(format('UPDATE event_assignments SET attended_at=now() WHERE id=%s', a_penari), 'chk_assign_attend', 'presensi sebelum tugas diterima');
  UPDATE event_assignments SET status='diterima' WHERE id IN (a_penari, a_helper, a_pemusik);
  PERFORM pg_temp.cek((SELECT responded_at IS NOT NULL FROM event_assignments WHERE id=a_penari), 'waktu respons terisi saat Terima');
  INSERT INTO event_assignments(order_id,user_id,role) VALUES (o1,v_penari2,'penari');
  UPDATE event_assignments SET status='ditolak' WHERE order_id=o1 AND user_id=v_penari2;
  INSERT INTO event_assignments(order_id,user_id,role) VALUES (o1,v_penari2,'penari');   -- boleh ditawari ulang setelah menolak
  PERFORM pg_temp.cek(true, 'setelah Tolak, SDM bisa ditawari ulang / diganti');
  PERFORM pg_temp.harus_gagal(format('UPDATE event_assignments SET status=''ditawarkan'' WHERE id=%s', a_penari), 'tidak boleh berubah', 'status penugasan tidak bisa mundur');

  -- ---------- Peminjaman barang ----------
  PERFORM pg_temp.harus_gagal(format('INSERT INTO item_loans(item_id,order_id,borrowed_by) VALUES (%s,%s,%s)', v_item, o4, v_helper), 'Approved', 'pinjam barang untuk pesanan non-Approved');
  INSERT INTO item_loans(item_id,order_id,borrowed_by) VALUES (v_item,o1,v_helper) RETURNING id INTO loan1;
  PERFORM pg_temp.cek((SELECT status='dipinjam' FROM inventory_items WHERE id=v_item), 'barang keluar: status katalog otomatis "dipinjam"');
  PERFORM pg_temp.harus_gagal(format('INSERT INTO item_loans(item_id,order_id,borrowed_by) VALUES (%s,%s,%s)', v_item, o1, v_helper), 'tidak tersedia', 'barang yang sedang dipinjam tidak bisa dipinjam lagi');
  INSERT INTO item_loans(item_id,order_id,borrowed_by) VALUES (v_item2,o1,v_helper);

  -- ---------- Presensi (hari acara = hari ini) ----------
  UPDATE event_assignments SET attended_at=now(), attendance_by=v_korlap WHERE id IN (a_korlap,a_penari,a_helper,a_pemusik);
  PERFORM pg_temp.cek((SELECT count(*)=4 FROM event_assignments WHERE order_id=o1 AND attended_at IS NOT NULL), 'presensi 4 orang tercatat');

  -- ---------- Pengembalian barang ----------
  UPDATE item_loans SET returned_at=now(), returned_by=v_helper, return_condition='baik' WHERE id=loan1;
  PERFORM pg_temp.cek((SELECT status='tersedia' FROM inventory_items WHERE id=v_item), 'dikembalikan baik: status kembali "tersedia"');
  UPDATE item_loans SET returned_at=now(), returned_by=v_helper, return_condition='rusak' WHERE item_id=v_item2;
  PERFORM pg_temp.cek((SELECT status='rusak' FROM inventory_items WHERE id=v_item2), 'dikembalikan rusak: status barang "rusak"');
  PERFORM pg_temp.harus_gagal(format('UPDATE item_loans SET returned_at=now(), returned_by=%s WHERE id=%s', v_helper, loan1), 'sudah tercatat dikembalikan', 'pengembalian ganda');

  -- ---------- Selesai & Pelunasan & Honor ----------
  UPDATE orders SET status='completed', field_report='Acara lancar' WHERE id=o1;
  PERFORM pg_temp.cek((SELECT completed_at IS NOT NULL FROM orders WHERE id=o1), 'status Completed mengisi completed_at');
  PERFORM pg_temp.harus_gagal(format('SELECT * FROM fn_cairkan_honor(%s,%s)', o1, v_owner), 'Lunas', 'honor tidak bisa cair sebelum Lunas');
  PERFORM pg_temp.harus_gagal(format('INSERT INTO payments(order_id,type,amount,proof_path) VALUES (%s,''pelunasan'',100000,''l.jpg'')', o1), 'kurang dari sisa tagihan', 'pelunasan kurang dari sisa tagihan');
  INSERT INTO payments(order_id,type,method,amount,received_by) VALUES (o1,'pelunasan','tunai',1260000,v_korlap) RETURNING id INTO pl;   -- 1.800.000 - 540.000
  PERFORM pg_temp.cek((SELECT lunas_at IS NULL FROM orders WHERE id=o1), 'pelunasan belum diverifikasi: belum Lunas');
  UPDATE payments SET status='verified', verified_by=v_owner, verified_at=now() WHERE id=pl;
  PERFORM pg_temp.cek((SELECT lunas_at IS NOT NULL FROM orders WHERE id=o1), 'pelunasan diverifikasi: status Lunas otomatis');
  PERFORM pg_temp.harus_gagal(format('UPDATE orders SET status=''cancelled'' WHERE id=%s', o1), 'tidak boleh berubah', 'pesanan Completed tidak bisa dibatalkan');

  PERFORM pg_temp.harus_gagal(format('SELECT * FROM fn_cairkan_honor(%s,%s)', o1, v_cust), 'Hanya Owner', 'selain Owner tidak bisa cairkan honor');
  SELECT * INTO r FROM fn_cairkan_honor(o1, v_owner);
  PERFORM pg_temp.cek(r.jumlah_penerima = 4 AND r.total_honor = 300000+250000+250000+150000, 'honor dicairkan: 4 orang, total sesuai tarif per peran');
  PERFORM pg_temp.cek((SELECT count(*)=0 FROM honorariums h JOIN event_assignments ea ON ea.id=h.assignment_id WHERE ea.status<>'diterima'), 'yang menolak tidak menerima honor');
  PERFORM pg_temp.harus_gagal(format('SELECT * FROM fn_cairkan_honor(%s,%s)', o1, v_owner), 'sudah pernah dicairkan', 'honor tidak bisa dicairkan dua kali');

  -- ---------- View dashboard ----------
  SELECT * INTO r FROM v_owner_ringkasan;
  -- pembayaran sah: o1 1,8jt + 2 pesanan Approved @450rb = 2,7jt (acara); 1 pesanan batal 450rb (kompensasi); total 3,15jt
  PERFORM pg_temp.cek(r.pendapatan_acara = 1800000+450000+450000 AND r.pendapatan_kompensasi = 450000, 'dashboard: pendapatan acara & kompensasi benar');
  PERFORM pg_temp.cek(r.pengeluaran_honor = 950000 AND r.saldo_bersih = 3150000 - 950000, 'dashboard: pengeluaran honor & saldo bersih benar');
  PERFORM pg_temp.cek(r.jumlah_completed = 1 AND r.jumlah_cancelled = 2 AND r.jumlah_approved = 2, 'dashboard: hitungan status pesanan benar');
  PERFORM pg_temp.cek((SELECT count(*)=1 FROM v_arus_kas_bulanan WHERE pemasukan=3150000 AND pengeluaran=950000), 'grafik arus kas bulanan benar');
  SELECT * INTO r FROM v_order_ringkas WHERE id=o1;
  PERFORM pg_temp.cek(r.sisa_tagihan = 0 AND r.korlap_name = 'Korlap A' AND r.customer_name = 'Pelanggan', 'v_order_ringkas: sisa tagihan, Korlap, pelanggan');

  RAISE NOTICE '=== SEMUA SKENARIO LULUS ===';
END $$;

ROLLBACK;
