USE college_connect;
-- The Phase 2 schema already includes notifications. This safely creates it only if missing.
CREATE TABLE IF NOT EXISTS notifications (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id BIGINT UNSIGNED NOT NULL,
 type VARCHAR(50) NOT NULL,
 title VARCHAR(180) NOT NULL,
 message VARCHAR(500) NOT NULL,
 related_type VARCHAR(50) NULL,
 related_id BIGINT UNSIGNED NULL,
 is_read TINYINT(1) NOT NULL DEFAULT 0,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
 INDEX idx_notifications_user_read (user_id,is_read,created_at)
) ENGINE=InnoDB;
