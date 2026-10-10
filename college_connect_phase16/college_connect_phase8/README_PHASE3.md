# AITAM SLACK — Phase 3

## Included
- Authenticated users can publish questions in a category.
- Question details page with published answers.
- Logged-in users can post answers.
- Question authors (and admins) can accept an answer.
- Notifications are created when a question receives an answer or an answer is accepted.
- Users can view notifications, mark one read, or mark all read.
- Questions list links to details and includes an Ask a question action.
- Phase 1 homepage and Phase 2 authentication/profile files are retained.

## Install/update
1. Back up your working project folder and database.
2. Extract this ZIP into a separate folder, then merge its files into your working project folder.
3. Confirm `config.php` has your working database settings.
4. In phpMyAdmin, select `college_connect`. Run `database_migration_phase3.sql` only if the notifications table is missing.
5. Open `questions.php` using your existing localhost project URL.
6. Log in, ask a question, post an answer, then test accepting an answer while logged in as the question author. Notifications appear for the question author and answer author.

## Notes
- Uses PHP, MySQL and XAMPP; no Node.js or build step is needed.
- Automated AI moderation is not enabled yet.
- Test locally before deployment.
