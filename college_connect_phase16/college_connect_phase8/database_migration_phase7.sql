USE college_connect;
CREATE TABLE IF NOT EXISTS content_appeals (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id BIGINT UNSIGNED NOT NULL,
 content_type ENUM('question','answer') NOT NULL,
 content_id BIGINT UNSIGNED NOT NULL,
 reason VARCHAR(1000) NOT NULL,
 status ENUM('open','approved','rejected') NOT NULL DEFAULT 'open',
 reviewed_by BIGINT UNSIGNED NULL,
 reviewer_note VARCHAR(1000) NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 reviewed_at TIMESTAMP NULL,
 CONSTRAINT fk_content_appeal_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
 CONSTRAINT fk_content_appeal_reviewer FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL,
 INDEX idx_content_appeals_status (status,created_at),
 INDEX idx_content_appeals_content (content_type,content_id)
) ENGINE=InnoDB;
