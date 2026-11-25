USE college_connect;

CREATE TABLE IF NOT EXISTS college_updates (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(180) NOT NULL,
    description TEXT NOT NULL,
    category VARCHAR(60) NOT NULL,
    event_at DATETIME NOT NULL,
    venue VARCHAR(180) NULL,
    registration_url VARCHAR(2048) NULL,
    contact_info VARCHAR(255) NULL,
    admin_id BIGINT UNSIGNED NULL,
    status ENUM('draft', 'published', 'archived') NOT NULL DEFAULT 'draft',
    published_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_college_updates_feed (status, published_at, id),
    INDEX idx_college_updates_category (status, category, published_at, id),
    CONSTRAINT fk_college_updates_admin
        FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE college_updates
    MODIFY status ENUM('draft', 'published', 'archived') NOT NULL DEFAULT 'draft';

UPDATE college_updates
SET category = CASE
    WHEN category IN ('College Fest', 'Freshers Party', 'Farewell Party',
                      'Cultural Events', 'Sports Events', 'Technical Events')
        THEN 'Events'
    WHEN category = 'Workshops & Seminars' THEN 'Academics'
    WHEN category = 'Important Announcements' THEN 'Announcements'
    WHEN category = 'Other' THEN 'Community'
    ELSE category
END
WHERE category IN (
    'College Fest', 'Freshers Party', 'Farewell Party', 'Cultural Events',
    'Sports Events', 'Workshops & Seminars', 'Technical Events',
    'Important Announcements', 'Other'
);

CREATE TABLE IF NOT EXISTS college_update_media (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    update_id BIGINT UNSIGNED NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    media_type ENUM('image', 'video') NOT NULL,
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_college_update_media_order (update_id, sort_order, id),
    CONSTRAINT fk_college_update_media_update
        FOREIGN KEY (update_id) REFERENCES college_updates(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
