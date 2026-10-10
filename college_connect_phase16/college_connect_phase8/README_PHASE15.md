AITAM SLACK Phase 15 — UI and Migration Recovery Fixes

This update fixes the question-card Answer button being clipped by the grid layout.

1. Copy the contents of this package into your active project folder, preserving config.php.
2. The stylesheet now allocates an auto-sized actions column on desktop and moves actions onto their own row on narrow screens.
3. If admin/appeals.php still reports that content_appeals is missing, import database_migration_phase7.sql from phpMyAdmin into the college_connect database. This migration is included in the project.
4. Hard-refresh the browser with Ctrl+F5.

Test pages:
- Questions: questions.php
- Individual question and answer form: question.php?id=YOUR_QUESTION_ID
- Appeals admin page: admin/appeals.php

No new database migration is required for the layout fix. This package does not alter AI moderation logic.
