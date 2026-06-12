-- Thoughts Timeline database schema
-- Target: MySQL 8.0+ with InnoDB, utf8mb4, and utf8mb4_0900_ai_ci.
SET NAMES utf8mb4 COLLATE utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    event_date DATE NOT NULL,
    event_text TEXT NOT NULL,
    thoughts TEXT NOT NULL,
    physical_effect TEXT NOT NULL,
    feeling_rate DECIMAL(4, 2) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_events_event_date_created_at (event_date, created_at),
    CONSTRAINT chk_events_event_text_length CHECK (CHAR_LENGTH(event_text) <= 1024),
    CONSTRAINT chk_events_thoughts_length CHECK (CHAR_LENGTH(thoughts) <= 1024),
    CONSTRAINT chk_events_physical_effect_length CHECK (CHAR_LENGTH(physical_effect) <= 1024),
    CONSTRAINT chk_events_feeling_rate CHECK (feeling_rate >= 0 AND feeling_rate <= 10)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS comments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    event_id BIGINT UNSIGNED NOT NULL,
    comment_text TEXT NOT NULL,
    is_read_by_admin TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    read_at TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (id),
    INDEX idx_comments_event_id_created_at (event_id, created_at),
    INDEX idx_comments_is_read_by_admin (is_read_by_admin),
    CONSTRAINT fk_comments_event_id
        FOREIGN KEY (event_id)
        REFERENCES events (id)
        ON DELETE CASCADE,
    CONSTRAINT chk_comments_text_length CHECK (CHAR_LENGTH(comment_text) <= 1024),
    CONSTRAINT chk_comments_read_flag CHECK (is_read_by_admin IN (0, 1)),
    CONSTRAINT chk_comments_read_at CHECK (
        (is_read_by_admin = 0 AND read_at IS NULL)
        OR (is_read_by_admin = 1)
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS settings (
    setting_key VARCHAR(100) NOT NULL,
    setting_value TEXT NOT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO settings (setting_key, setting_value)
VALUES
    ('theme_mode', 'system'),
    ('accent_color', '#2563eb'),
    ('base_font_size', '16'),
    ('font_family', 'system'),
    ('density', 'comfortable')
ON DUPLICATE KEY UPDATE setting_value = setting_value;
