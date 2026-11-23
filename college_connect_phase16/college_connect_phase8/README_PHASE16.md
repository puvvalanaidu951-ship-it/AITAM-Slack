# AITAM SLACK Phase 16 - OpenAI moderation

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

## Database-backed listing pagination
- Paginated lists use shared helpers in `includes/pagination.php` and prepared PDO queries with integer-bound `LIMIT` and `OFFSET`.
- Questions, search results, answers, bookmarks, notifications, user activity, achievements, leaderboard, and the existing admin management/review lists preserve their active query filters while navigating pages.
- Default sizes follow each page's use: 10 for questions, answers, bookmarks, and activity; 15 for notifications; 20 for moderation and other review lists; 25 for member management and audit logs. The selector offers 10, 20, 50, and 100 records, plus the page-specific default.
- For a database that does not already contain these indexes, back it up and run `database_migration_pagination.sql` once in phpMyAdmin. Do not rerun it after the indexes have been created.
- To verify in XAMPP, start Apache and MySQL, open the listings, try filters/search and sort before changing pages, and verify first/last page and page-size navigation. Run `php tests/pagination_test.php` and `php tests/openai_moderation_test.php` from this folder for the local PHP tests.

## College Updates
- Students can browse and search published campus updates at `college_updates.php`; each item has a separate detail URL at `college_update.php?id=<id>`. The homepage shows the three newest published updates.
- Administrators manage drafts, published posts, and archived posts at `admin/college_updates.php`. The admin list supports title search and category/status filters; all management actions require an authenticated admin and CSRF token.
- Back up the database, then run `database_migration_college_updates.sql` in phpMyAdmin. It reuses `college_updates`, adds the media table if needed, enables the archived status, and maps the previous event categories to the new categories: Community, Academics, Events, Examinations, Placements, and Announcements.
- Publication date is stored in `published_at`; drafts and archived posts are never shown in public feeds, and scheduled posts appear once their publication date is reached.
- Uploaded JPG, JPEG, PNG, WebP, and MP4 media is validated for extension, MIME type, and file size on the server and stored in `uploads/college-updates`; the database stores each media path. The upload directory blocks script and HTML execution.
- The form accepts up to five images (8 MB each), one MP4 (30 MB), and 35 MB of new media per save. XAMPP should have `file_uploads=On`, `upload_max_filesize` at least `30M`, and `post_max_size` at least `40M`. Increase those PHP settings and restart Apache only if your installation uses lower limits.
- Run `php tests/college_updates_test.php` from the active project folder for validation tests.

## Multilingual Questions and Answers
- Supported language codes are `en`, `te`, `hi`, `ta`, `kn`, and `or`. New questions and community answers retain their original UTF-8 text and store a detected language code.
- The moderation, question-quality, and answer-relevance checks remain required. Their prompts explicitly support the six languages; language alone is not a reason to block a question. If a multilingual quality classification would otherwise block a question, it is held for moderator review instead.
- On a question page, signed-in users can request an AI answer in the question language or another supported language. Generated answers pass through the same safety and relevance checks as community answers and are labeled as AI-generated.
- Signed-in users can translate questions and answers. Translations are stored separately and never replace the original content. Translation output is also checked by the existing safety moderation.
- Back up the database, then import `database_migration_multilingual.sql` once in phpMyAdmin after the base schema has been installed. It adds language metadata, AI-answer attribution, and separate translation tables, and ensures Q&A and notification tables use `utf8mb4`.
- The existing `OPENAI_API_KEY`, `OPENAI_BASE_URL`, `OPENAI_QUALITY_MODEL`, and `OPENAI_RELEVANCE_MODEL` settings are reused. The API key remains server-side. If a check or generation call fails, the original question remains unchanged and submitted question/answer text is retained in the session for retry.
- Run `php tests/multilingual_test.php` and `php tests/openai_moderation_test.php` from the active project folder. The multilingual unit tests use mocked API responses; live OpenAI language quality still depends on the configured model and API account.

## Admin Report Management
- Student reports use the existing `reports` table and its `report_type`, `reported_item_id`, `subject`, and `description` columns. New reports start with `pending` status.
- Administrators manage reports at `admin/reports.php`. They can search and filter the paginated list, inspect details, mark a report under review or resolved, reject it with an explanation, save private investigation notes, or delete it.
- Back up the database, then run `database_migration_admin_reports.sql` once in phpMyAdmin. It adds only the report-review columns, preserves existing records, and normalizes older `open` and `dismissed` statuses to `pending` and `rejected`.
- The admin dashboard shows total, pending, under-review, resolved, and rejected report counts. Internal notes and rejection explanations are only displayed on the admin report page.
