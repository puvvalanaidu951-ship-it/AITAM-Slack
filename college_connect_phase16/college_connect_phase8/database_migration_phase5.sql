USE college_connect;
CREATE TABLE IF NOT EXISTS ai_question_insights (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 question_id BIGINT UNSIGNED NOT NULL UNIQUE,
 suggested_category_id INT UNSIGNED NULL,
 classification_confidence DECIMAL(5,4) NOT NULL DEFAULT 0,
 duplicate_question_id BIGINT UNSIGNED NULL,
 duplicate_score DECIMAL(5,4) NULL,
 risk_level ENUM('low','review') NOT NULL DEFAULT 'low',
 risk_flags_json JSON NULL,
 risk_reason VARCHAR(500) NULL,
 suggested_answer TEXT NULL,
 review_status ENUM('pending','accepted','dismissed') NOT NULL DEFAULT 'pending',
 reviewed_by BIGINT UNSIGNED NULL,
 reviewed_at TIMESTAMP NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_ai_insight_question FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE,
 CONSTRAINT fk_ai_insight_category FOREIGN KEY (suggested_category_id) REFERENCES categories(id) ON DELETE SET NULL,
 CONSTRAINT fk_ai_insight_duplicate FOREIGN KEY (duplicate_question_id) REFERENCES questions(id) ON DELETE SET NULL,
 CONSTRAINT fk_ai_insight_reviewer FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL,
 INDEX idx_ai_insights_review (review_status,risk_level,created_at)
) ENGINE=InnoDB;
