# AITAM SLACK Phase 6 — Automatic safety and answer relevance checks

## Requested behavior implemented
- New questions are scanned before public publication. If configured safety keyword patterns match, the question is stored as rejected/blocked and is not shown publicly. An insight is recorded for admin review.
- New answers are scanned for the same configured safety patterns and compared with the question using lexical overlap.
- Safety-flagged answers are stored as `blocked` and not shown publicly.
- Answers with low keyword overlap are stored as `pending_review` and remain hidden until an admin approves them.
- Answers that pass the heuristic checks are published immediately.
- Admins can review question insights at `admin/ai_review.php` and answer checks at `admin/answer_review.php`.

## Important limitations
This phase is a conservative local heuristic prototype, not a connected LLM or semantic classifier. It only detects configured phrases and keyword overlap, so it can miss harmful content and incorrectly hold relevant answers. The page messages should not be represented as a guarantee of safety. For a production portal, use a vetted moderation model, human appeal/review, rate limits, and audit logs. Rejected questions are hidden rather than physically deleted so an admin can inspect a false positive.

## Installation
1. Back up your Phase 5 project folder and database.
2. Extract this ZIP into a separate folder, then merge its files into your existing `college_connect_phase5` folder (or the active project folder you use). Keep `config.php`.
3. In phpMyAdmin select `college_connect`, open SQL, and run `database_migration_phase6.sql`.
4. Ensure `includes/ai_helpers.php`, `ask_question.php`, `question.php`, and the new `admin/answer_review.php` are copied.
5. Log in as admin. Open `/admin/answer_review.php` for answer checks and `/admin/ai_review.php` for question checks.
6. Test with harmless relevant answers and irrelevant answers before using this in real community traffic.

Existing questions and answers are not retroactively processed. New submissions use the checks.
