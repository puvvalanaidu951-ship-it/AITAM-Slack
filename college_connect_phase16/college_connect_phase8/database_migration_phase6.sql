USE college_connect;

CREATE TABLE IF NOT EXISTS ai_answer_reviews (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 answer_id BIGINT UNSIGNED NOT NULL UNIQUE,
 question_id BIGINT UNSIGNED NOT NULL,
 relevance_score DECIMAL(5,4) NOT NULL DEFAULT 0,
 relevance_status ENUM('likely_relevant','needs_review') NOT NULL DEFAULT 'needs_review',
 matched_terms_json JSON NULL,
 risk_level ENUM('low','review') NOT NULL DEFAULT 'low',
 risk_flags_json JSON NULL,
 reason VARCHAR(1000) NULL,
 decision ENUM('published','pending_review','blocked','dismissed') NOT NULL DEFAULT 'pending_review',
 reviewed_by BIGINT UNSIGNED NULL,
 reviewed_at TIMESTAMP NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_ai_answer_review_answer FOREIGN KEY (answer_id) REFERENCES answers(id) ON DELETE CASCADE,
 CONSTRAINT fk_ai_answer_review_question FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE,
 CONSTRAINT fk_ai_answer_review_user FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL,
 INDEX idx_ai_answer_review_queue (decision,risk_level,created_at)
) ENGINE=InnoDB;
