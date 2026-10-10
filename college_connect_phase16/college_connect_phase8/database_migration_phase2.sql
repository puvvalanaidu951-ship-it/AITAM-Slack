-- Back up your database before running this migration.
USE college_connect;
ALTER TABLE users
  MODIFY COLUMN role ENUM('student','alumni','faculty','authority','prospective','admin') NOT NULL DEFAULT 'student',
  ADD COLUMN IF NOT EXISTS department VARCHAR(120) NULL,
  ADD COLUMN IF NOT EXISTS graduation_year YEAR NULL,
  ADD COLUMN IF NOT EXISTS affiliation_verified TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS status ENUM('active','suspended','pending') NOT NULL DEFAULT 'active';
-- Phase 1 database.sql already defines these columns. If they exist, no migration is required.
