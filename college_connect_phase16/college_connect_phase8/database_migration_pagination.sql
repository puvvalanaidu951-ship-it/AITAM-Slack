USE college_connect;

CREATE INDEX idx_questions_public_created
    ON questions (status, moderation_status, created_at);

CREATE INDEX idx_questions_category_public_created
    ON questions (category_id, status, moderation_status, created_at);

CREATE INDEX idx_questions_user_created
    ON questions (user_id, created_at);

CREATE INDEX idx_answers_user_created
    ON answers (user_id, created_at);

CREATE INDEX idx_answers_question_status_rank
    ON answers (question_id, status, is_accepted, created_at);

CREATE INDEX idx_answers_published_user
    ON answers (status, user_id, is_accepted);

CREATE INDEX idx_notifications_user_created
    ON notifications (user_id, created_at);

CREATE INDEX idx_users_created
    ON users (created_at);

CREATE INDEX idx_announcements_created
    ON announcements (created_at);
