# AITAM SLACK — Phase 8

## New features
- Admin audit log page at `admin/audit_logs.php`.
- Records admin appeal decisions (approve/reject), including the appeal ID, content type/ID, reviewer note, timestamp and request IP address.
- Search and filter the latest 300 audit entries.
- Adds a dashboard shortcut to the audit log.

## Install on XAMPP
1. Back up your current project folder and `college_connect` database.
2. Copy the Phase 8 files into your active project folder, or extract the ZIP as `college_connect_phase8`.
3. Keep your existing `config.php` database credentials. The database name remains `college_connect`.
4. Open phpMyAdmin → select/import database, then run `database_migration_phase8.sql` (it begins with `USE college_connect;`).
5. Log in with an administrator account and visit `http://localhost/college_connect_phase8/admin/audit_logs.php` (adjust the folder name if you merged files into another folder).
6. Make an appeal decision in Admin → Content appeals. The decision should then be recorded in the audit log.

## Notes
- Phase 8 is additive; it does not replace the Phase 5, 6 or 7 migrations. Those tables must already exist.
- The audit log currently records Phase 8 appeal decisions only; it does not retroactively create entries for earlier actions.
- This package received PHP syntax checks, not a full live XAMPP/MySQL integration test. Back up your database before applying migrations.
