# AITAM SLACK — Phase 9

## Features
- Save and remove question bookmarks.
- Browse saved questions at `bookmarks.php`.
- Mark other members' published answers as helpful (+1) or not helpful (-1).
- Each member has one vote per answer; voting again with the same choice removes the vote, and switching choices updates it.
- Shows total helpful score and the signed-in user's current vote.

## Installation
1. Back up the project and `college_connect` database.
2. Copy the Phase 9 files into the active project folder, preserving `config.php` and your database settings.
3. In phpMyAdmin select `college_connect` → Import → import `database_migration_phase9.sql`.
4. Sign in and open a question. Use **Save question** to bookmark it. Browse bookmarks at `/bookmarks.php`.
5. Signed-in users can vote on published answers other than their own.

Phase 9 migration adds only `question_bookmarks` and `answer_votes`; it does not drop or modify existing tables. It assumes Phase 1–8 tables/migrations are installed. Do not expose database credentials publicly.
