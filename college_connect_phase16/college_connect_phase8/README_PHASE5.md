# AITAM SLACK — Phase 5: AI-assisted community features

## Included
- Suggested question category based on transparent keyword rules.
- Candidate duplicate-question detection based on title token overlap.
- Configured keyword risk flags for threats, self-harm language, personal data, and harassment.
- A suggested-answer template that reminds users to verify official information.
- Admin review page at `admin/ai_review.php`.
- Human review remains in control; no automatic content blocking or publishing is performed by the AI helper.

## Important limitation
This is a local rules-based AI-assist prototype, not a connected large language model. It can miss context, make false positives, and is not a safety guarantee. Do not use its risk flags as the sole basis for account or content decisions.

## Install
1. Back up your working `college_connect_phase4` folder and database.
2. Extract this ZIP separately, then merge files into your `college_connect_phase4` folder.
3. Preserve your current `config.php`.
4. In phpMyAdmin, select `college_connect` and run `database_migration_phase5.sql`.
5. Submit a new question through `ask_question.php`.
6. Log in as admin and open `admin/ai_review.php` or use the AI question insights button on the admin dashboard.

## Data
Insights are stored in `ai_question_insights`; existing questions are not retroactively processed. No API key or external AI service is required.
