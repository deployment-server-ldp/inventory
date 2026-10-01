-- =====================================================================================
--  SPIMS — CLEAR DEMO DATA (keep your admin login and base settings)
--
--  Use this ONLY on a database that was loaded from spims_demo_full.sql, when you want to
--  start real work with the same admin account.
--
--  KEEPS:   your logged-in admin user (demo_admin — rename it in Admin → Users),
--           roles & permissions, CNC machines M-1…M-20, operations, units,
--           part categories, machinery models, company settings.
--  DELETES: all parts/products and their image records, all CNC production, completions,
--           stock ledgers (CNC + imported), adjustments, assemblies, demo operators,
--           demo suppliers, the other demo users (demo_cnc, demo_import, demo_combined),
--           the demo activity log, and resets reference numbers to start from 000001.
--
--  HOW: phpMyAdmin → select the database → Import → this file → Go.
--  Take a backup first (phpMyAdmin → Export) if you are unsure.
--  Afterwards you may delete the folder  storage/app/private/parts  in File Manager
--  (demo image files are no longer used).
-- =====================================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

TRUNCATE TABLE imported_inventory_transactions;
TRUNCATE TABLE cnc_inventory_transactions;
TRUNCATE TABLE stock_adjustments;
TRUNCATE TABLE cnc_production_completions;
TRUNCATE TABLE cnc_production_records;
TRUNCATE TABLE machine_assembly_items;
TRUNCATE TABLE machine_assemblies;
TRUNCATE TABLE spare_part_machinery_model;
TRUNCATE TABLE spare_part_images;
TRUNCATE TABLE spare_parts;
TRUNCATE TABLE operators;
TRUNCATE TABLE suppliers;
TRUNCATE TABLE document_sequences;
TRUNCATE TABLE activity_logs;
TRUNCATE TABLE cache;
TRUNCATE TABLE cache_locks;

-- remove the other demo users (keep demo_admin = your current login)
DELETE FROM user_permissions WHERE user_id IN (SELECT id FROM users WHERE username IN ('demo_cnc', 'demo_import', 'demo_combined'));
DELETE FROM sessions WHERE user_id IN (SELECT id FROM users WHERE username IN ('demo_cnc', 'demo_import', 'demo_combined'));
DELETE FROM users WHERE username IN ('demo_cnc', 'demo_import', 'demo_combined');

-- machines back to "active" (demo may have changed nothing, this is just a clean start)
UPDATE machines SET status = 'active' WHERE status <> 'inactive';

SET FOREIGN_KEY_CHECKS = 1;
