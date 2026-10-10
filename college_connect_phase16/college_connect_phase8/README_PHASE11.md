# AITAM SLACK Phase 11 — Community Achievements

## What's new
- `achievements.php` shows earned community badges for published answers, accepted answers, helpful votes, and saved questions.
- The main navigation includes an Achievements link.
- Badges are calculated from existing Phase 9 data; no new database migration is required.

## Install
1. Back up your project folder and database.
2. Extract this ZIP.
3. Copy the contents of `college_connect_phase8` into your existing project folder, preserving your own `config.php` settings.
4. Ensure Phase 9 migration has been imported (it creates `question_bookmarks` and `answer_votes`).
5. Open `http://localhost/college_connect/college_connect_phase8/achievements.php` and adjust the path to your actual XAMPP folder.

## Notes
- PHP syntax checks are performed on the new page and modified navigation.
- A live integration test against your local MySQL database has not been performed.
- Badge thresholds are simple rules and can be changed in `cc_badges()` in `achievements.php`.
