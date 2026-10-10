# AITAM SLACK Phase 16 — OpenAI moderation

Question and answer submissions run server-side OpenAI safety checks before any content can be published. Clear, well-formed questions use the safety result and basic input validation; an additional contextual quality/spam request is reserved for ambiguous or structurally suspicious submissions. Answers receive a contextual relevance/spam check against the question loaded from MySQL.

## What it does
- Calls the configurable `OPENAI_MODERATION_MODEL` (default `omni-moderation-latest`) moderation endpoint.
- Calls Chat Completions with configurable `OPENAI_QUALITY_MODEL` and `OPENAI_RELEVANCE_MODEL` settings (both default to `gpt-4o-mini`) for contextual quality and answer relevance.
- Inserts and publishes a question only when safety moderation succeeds without a flag and basic validation passes. Ambiguous or spam-like forms also require a successful contextual quality check.
- Inserts and publishes an answer only when safety and contextual relevance APIs both succeed, safety does not flag it, and the classifier returns `relevant` without spam or abuse flags. An explicit `irrelevant` or `uncertain` result is not published.
- Local keyword matches are not used to automatically block submissions. This avoids rejecting educational content merely because it mentions sensitive terms.
- Fails closed: unsafe, irrelevant, uncertain, malformed, or unavailable required checks do not allow insertion into the main question or answer tables. Users receive a safe retry or rejection message.
- Stores only minimal metadata (content type, user ID, decision category, reason code, and API outcome) in the server log for submissions rejected before insertion. No rejected submission text is retained for admin inspection.
- Records successful moderation results against the accepted content in the existing moderation table. API keys and detailed diagnostics are never included in public responses.
- Admin dismissal uses the existing `rejected`/`blocked` soft-removal state. It removes the question from public views while preserving its answers, votes, bookmarks, notifications, reports, and appeal history; the admin action is CSRF-protected and confirmed in the moderation UI.

## Configure in XAMPP
1. Use the active project folder containing `ask_question.php`, `config.php`, and `includes`; in this checkout that folder is `college_connect_phase16/college_connect_phase8`. Keep your existing `config.php` and database settings.
2. If `.env` does not already exist, copy `.env.example` to `.env` in the active project folder. Do not overwrite an existing local `.env`.
3. Replace `OPENAI_API_KEY` with your own API key. Do not share it or commit `.env` to Git. Optionally set `OPENAI_MODERATION_MODEL`, `OPENAI_QUALITY_MODEL`, and `OPENAI_RELEVANCE_MODEL`.
4. Ensure PHP cURL is enabled in the PHP configuration used by Apache (`extension=curl`), then restart Apache.
5. Open `ask_question.php` and submit a test question; then answer a published question. Only content passing all required checks will be saved and published.
6. If a required API check is unavailable or uncertain, the submission is not saved. Ask the user to retry after correcting the API configuration or submission. Check the Apache/PHP error log for sanitized diagnostics; API keys and authorization headers are never logged.
7. If Apache logs report HTTP 429 with `insufficient_quota` or `credit_balance_exhausted`, the configured API credential has no usable model quota. Restore quota for the API account/project; the application correctly leaves answers unpublished until the relevance check can run.

## Local mocked tests
Run `php tests/openai_moderation_test.php` from the active project folder. These tests use mocked response data and do not make OpenAI requests or consume API credits.

## Database
No new database migration is added. This uses existing Phase 5/6 review tables. Ensure prior migrations are applied, including `ai_question_insights` and `ai_answer_reviews`.

## Important limitations
- OpenAI Moderation is a safety classifier, not a truth detector. The relevance classifier does not verify factual accuracy.
- Automated classifiers can make mistakes. Since rejected submission text is intentionally not retained, administrators cannot inspect that text later; retaining rejected text would require a separate access-controlled review store and explicit approval.
- API calls require internet access. Contextual quality/relevance requests require available model quota; clear, well-formed questions do not make a second quality-model request. The OpenAI moderation endpoint itself is currently documented as free.
- The `.htaccess` file blocks direct access to `.env`, README, and SQL files under Apache; still keep the `.env` file out of version control and outside publicly served directories when possible.
