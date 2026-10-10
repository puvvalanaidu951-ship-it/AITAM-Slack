# AITAM SLACK — Phase 13: Advanced Question Search

## Features
- Search published, moderation-approved questions by title or body text.
- Filter by category.
- Sort by newest, oldest, or title.
- Shows category, answer count, author, and date.
- Adds Search to the main navigation.

## Install
1. Back up your current project.
2. Copy `search_questions.php` and the updated `includes/header.php` into your active AITAM SLACK folder. Keep your existing `config.php`.
3. No database migration is required.
4. Visit `http://localhost/college_connect/college_connect_phase8/search_questions.php` (adjust the folder to match your setup).

This feature uses the existing `questions`, `categories`, `answers`, and `users` tables. Only published and moderation-approved questions are shown. The page caps results at 100.
