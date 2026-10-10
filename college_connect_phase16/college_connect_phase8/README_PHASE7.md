# AITAM SLACK Phase 7 — Appeals and improved answer relevance

## Included
- Improved lexical relevance score that gives extra weight to terms from the question title as well as body.
- Appeal form for users whose own question or answer was blocked or held by automated checks.
- Blocked questions redirect to an appeal form; blocked or held answers redirect to an appeal form.
- Admin appeal queue at `admin/appeals.php`; administrators can approve and publish or reject an appeal with an optional note.
- Admin dashboard shortcut to Content appeals.

## Install
1. Back up your current Phase 6 folder and database.
2. Extract this ZIP separately and merge its files into your active project folder, preserving `config.php`.
3. Run `database_migration_phase7.sql` in phpMyAdmin with `college_connect` selected.
4. Confirm Phase 5 `ai_question_insights` and Phase 6 `ai_answer_reviews` tables exist; Phase 7 uses them when an appeal is approved.
5. Log in as admin and open `admin/appeals.php`.

## Limitations
Answer relevance is still keyword-based, not semantic AI. Approving an appeal publishes the user's content, so admins should inspect the content before approving. Automated checks can make false positives and false negatives.
