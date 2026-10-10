# AITAM SLACK — Phase 2

## Features
- Open registration for students, alumni, and prospective students only.
- Secure login/logout, PHP sessions, password hashing, profile editing.
- Role-based authorization helper `require_role(['admin'])`.
- CSRF protection on state-changing forms.
- PDO prepared statements, HTML escaping, session ID regeneration.
- Privileged roles cannot be selected during public registration.

## Update existing Phase 1 project
1. Back up `C:\xampp\htdocs\college_connect` and export the `college_connect` database.
2. Merge the ZIP contents into the existing project folder, allowing the updated PHP files and `assets/style.css` to replace previous versions.
3. The Phase 1 `database.sql` already includes the required user fields (`department`, `graduation_year`, `affiliation_verified`, `status`) and roles. No migration is normally needed. If your actual table is older, run `database_migration_phase2.sql` after backup.
4. Confirm `config.php` database settings. Change `APP_KEY` to a long random secret before deployment.
5. Start Apache and MySQL, visit `http://localhost/college_connect/register.php`, register, update your profile, log out, then log in.

## Promote first administrator — local only
1. Register a normal account first.
2. Open `http://localhost/college_connect/admin/create_admin.php`.
3. Enter that account's email and the exact `APP_KEY` value from `config.php`.
4. Confirm the role change, then **delete `admin/create_admin.php` immediately**.
5. This utility must never be deployed to public hosting. Use an audited provisioning workflow for production.

## Role policy
Public registration supports only `student`, `alumni`, `prospective`. Faculty, authority, and admin roles must be assigned through a trusted administrative process. Use `require_role([...])` at the start of every protected page; UI hiding alone is not security.

## Security and scope
Use HTTPS in production; do not commit credentials to Git. The app does not yet implement question submission, AI moderation, reports, notifications, or a complete admin dashboard. Those belong to later phases.
