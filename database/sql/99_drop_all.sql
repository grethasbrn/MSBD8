-- 99_drop_all.sql - menghapus semua objek Sanggar (dipakai oleh down() pada migration)
DROP VIEW IF EXISTS v_order_ringkas, v_arus_kas_bulanan, v_owner_ringkasan;

DROP FUNCTION IF EXISTS fn_cairkan_honor(bigint, bigint);
DROP FUNCTION IF EXISTS fn_kalender(date, date);
DROP FUNCTION IF EXISTS fn_hitung_biaya(bigint, bigint[]);

DROP TABLE IF EXISTS app_notifications, item_loans, honorariums, event_assignments, payments,
                     daily_quota, order_status_logs, order_addons, orders,
                     honor_rates, app_settings, gallery_items, inventory_items, addons, packages, users CASCADE;

DROP FUNCTION IF EXISTS trg_loans_before_insert(), trg_loans_sync(),
                        trg_assign_before_update(), trg_assign_before_insert(),
                        trg_payments_after(), trg_payments_guard(),
                        trg_orders_after_insert(), trg_orders_after_status(), trg_orders_guard(),
                        trg_set_updated_at();
DROP FUNCTION IF EXISTS fn_quota_release(date), fn_quota_take(date),
                        fn_setting(text), fn_today_wib(), fn_generate_order_code();

DROP SEQUENCE IF EXISTS order_code_seq;

DROP TYPE IF EXISTS honor_status, assignment_status, assignment_role, return_condition, item_status,
                    item_category, package_category, payment_status, payment_method, payment_type,
                    order_status, user_role;
