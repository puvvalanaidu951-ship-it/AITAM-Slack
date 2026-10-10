# AITAM SLACK — Phase 4: Admin dashboard

## Included
- Admin dashboard with member, question, answer, report and review counters.
- Manual moderation controls for questions and answers; actions are logged.
- Report queue with status updates.
- Member search and account status management (admin accounts cannot be suspended from this page).
- Announcement creation and publish/hide controls.
- Admin navigation shortcut.

## Install
1. Back up the working Phase 3 folder and database.
2. Extract this ZIP separately and merge the files into your existing project.
3. Preserve your current `config.php` and database settings.
4. The supplied Phase 2 schema already includes the tables used here, so no migration is normally required.
5. Sign in with an account whose database role is `admin`, then open `admin/index.php`.
6. If using the local admin setup utility, delete `admin/create_admin.php` immediately after promotion.

## Notes
Manual moderation only; no AI classifier or public report submission form is connected yet. Test locally before deployment.
