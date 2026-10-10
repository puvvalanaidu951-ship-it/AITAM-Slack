# AITAM SLACK — Phase 12: My Activity Dashboard

## What is included
- A signed-in user's activity page showing recent questions and answers.
- Counts for saved questions and votes cast.
- Links back to the relevant question and to bookmarks.
- Navigation link to My activity.

## Installation
1. Back up your existing project folder and database.
2. Copy the files in this ZIP over your existing AITAM SLACK project, keeping your `config.php` and database settings.
3. This feature uses the existing Phase 9 `question_bookmarks` and `answer_votes` tables; no new migration is required.
4. Sign in and visit `my_activity.php`.

The page shows up to 10 recent questions and 10 recent answers. Database integration must be checked in your local XAMPP environment.
