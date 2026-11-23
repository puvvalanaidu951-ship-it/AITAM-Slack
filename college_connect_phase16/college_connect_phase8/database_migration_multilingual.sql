USE college_connect;

ALTER TABLE questions
    CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
    ADD COLUMN language_code ENUM('en', 'te', 'hi', 'ta', 'kn', 'or')
        NOT NULL DEFAULT 'en' AFTER body;

ALTER TABLE answers
    CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
    ADD COLUMN language_code ENUM('en', 'te', 'hi', 'ta', 'kn', 'or')
        NOT NULL DEFAULT 'en' AFTER body,
    ADD COLUMN is_ai_generated TINYINT(1) NOT NULL DEFAULT 0 AFTER language_code;

ALTER TABLE notifications
    CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS question_translations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    question_id BIGINT UNSIGNED NOT NULL,
    language_code ENUM('en', 'te', 'hi', 'ta', 'kn', 'or') NOT NULL,
    title VARCHAR(220) NOT NULL,
    body TEXT NOT NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_question_translation_language (question_id, language_code),
    CONSTRAINT fk_question_translation_question
        FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE,
    CONSTRAINT fk_question_translation_user
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS answer_translations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    answer_id BIGINT UNSIGNED NOT NULL,
    language_code ENUM('en', 'te', 'hi', 'ta', 'kn', 'or') NOT NULL,
    translated_text TEXT NOT NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_answer_translation_language (answer_id, language_code),
    CONSTRAINT fk_answer_translation_answer
        FOREIGN KEY (answer_id) REFERENCES answers(id) ON DELETE CASCADE,
    CONSTRAINT fk_answer_translation_user
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
