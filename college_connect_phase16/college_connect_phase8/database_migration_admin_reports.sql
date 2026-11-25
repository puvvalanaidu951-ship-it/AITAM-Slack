USE college_connect;

ALTER TABLE reports
    ADD COLUMN IF NOT EXISTS admin_notes TEXT NULL,
    ADD COLUMN IF NOT EXISTS rejection_reason TEXT NULL,
    ADD COLUMN IF NOT EXISTS reviewed_by BIGINT UNSIGNED NULL,
    ADD COLUMN IF NOT EXISTS reviewed_at TIMESTAMP NULL,
    ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP NOT NULL
        DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

ALTER TABLE reports
    MODIFY COLUMN status ENUM(
        'pending',
        'open',
        'reviewing',
        'resolved',
        'rejected',
        'dismissed'
    ) NULL DEFAULT 'pending';

UPDATE reports
SET status = 'pending'
WHERE status IS NULL OR status = 'open';

UPDATE reports
SET status = 'rejected'
WHERE status = 'dismissed';

ALTER TABLE reports
    MODIFY COLUMN status ENUM(
        'pending',
        'reviewing',
        'resolved',
        'rejected'
    ) NOT NULL DEFAULT 'pending';
