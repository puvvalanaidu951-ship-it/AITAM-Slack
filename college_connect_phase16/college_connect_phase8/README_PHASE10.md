# AITAM SLACK — Phase 10

## Community leaderboard
- Adds `leaderboard.php` with all-time and last-30-days views.
- Ranks active non-admin members by positive votes on published answers, accepted answers, and published answer count.
- Adds a Leaderboard link to the site navigation.

## Installation
1. Back up your current project.
2. Copy `leaderboard.php`, the updated `includes/header.php`, and this README into your active AITAM SLACK folder. Keep your existing `config.php` and other local configuration.
3. No new database migration is required. This page depends on the Phase 9 `answer_votes` table and `answers.is_accepted` column. Import `database_migration_phase9.sql` first if you have not already done so.
4. Open `http://localhost/college_connect_phase8/leaderboard.php` (adjust the folder name to match your XAMPP path).

## Notes
- The leaderboard is a community recognition feature, not a definitive measure of expertise.
- PHP syntax checks are not equivalent to a full live XAMPP/MySQL integration test.
