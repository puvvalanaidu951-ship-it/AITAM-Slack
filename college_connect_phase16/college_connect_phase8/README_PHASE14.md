# AITAM SLACK — Phase 14: Answer from Every Question

## Features
- Adds an Answer button to every question in the main Questions list and Advanced Search results.
- The button opens question details and jumps to the answer form.
- Any signed-in member can submit an answer; existing CSRF and login protections remain in place.
- Answer submissions use existing heuristic checks: risk-flagged answers are blocked from public display and low-relevance answers are held for review.
- Question list and detail routes require published status and moderation_status = allowed, so blocked/rejected questions are not shown publicly.
- Keeps bookmark, voting, answer acceptance, and notification functionality.

## Install
1. Back up the project and database.
2. Extract this ZIP and copy files into your active AITAM SLACK folder, keeping your existing config.php.
3. No new database migration is required. Phase 6 and Phase 7 migrations must already be applied for answer review and appeals.
4. Open questions.php or search_questions.php and click Answer beside any question.

## Important limitation
The checker is a local keyword and lexical-overlap heuristic, not a connected large language model. It can miss harmful content or flag safe content. Flagged/uncertain answers are not shown publicly; continue to use administrator review.
