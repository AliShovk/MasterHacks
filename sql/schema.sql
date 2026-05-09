-- MasterHacks DB schema

CREATE TABLE authors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    telegram_id BIGINT UNIQUE NOT NULL,
    username VARCHAR(255) DEFAULT NULL,
    first_name VARCHAR(255),
    last_name VARCHAR(255) DEFAULT NULL,
    avatar_url VARCHAR(500) DEFAULT NULL,
    reputation_score INT DEFAULT 10,
    videos_count INT DEFAULT 0,
    total_views BIGINT DEFAULT 0,
    total_likes BIGINT DEFAULT 0,
    is_verified BOOLEAN DEFAULT FALSE,
    last_active TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_reputation (reputation_score DESC),
    INDEX idx_telegram_id (telegram_id),
    INDEX idx_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE videos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    telegram_id BIGINT NOT NULL,
    file_hash VARCHAR(64) UNIQUE NOT NULL,
    filename VARCHAR(255) NOT NULL,
    file_type ENUM('video', 'image') NOT NULL,
    description TEXT,
    duration INT DEFAULT NULL,
    thumbnail_url VARCHAR(500) DEFAULT NULL,
    views INT DEFAULT 0,
    likes INT DEFAULT 0,
    comments_count INT DEFAULT 0,
    shares INT DEFAULT 0,
    status ENUM('pending', 'approved', 'rejected', 'draft') DEFAULT 'pending',
    moderation_score FLOAT DEFAULT 1.0,
    tags JSON DEFAULT NULL,
    location VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    published_at TIMESTAMP DEFAULT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (telegram_id) REFERENCES authors(telegram_id) ON DELETE CASCADE,
    INDEX idx_status_published (status, published_at DESC),
    INDEX idx_telegram_status (telegram_id, status),
    INDEX idx_views_likes (views DESC, likes DESC),
    FULLTEXT idx_description (description)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE subscriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    subscriber_telegram_id BIGINT NOT NULL,
    author_telegram_id BIGINT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_subscription (subscriber_telegram_id, author_telegram_id),
    FOREIGN KEY (author_telegram_id) REFERENCES authors(telegram_id) ON DELETE CASCADE,
    INDEX idx_subscriber (subscriber_telegram_id),
    INDEX idx_author (author_telegram_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE user_actions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    telegram_id BIGINT NOT NULL,
    action_type ENUM('register','upload','like','view','share','report') NOT NULL,
    video_id INT DEFAULT NULL,
    target_telegram_id BIGINT DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent TEXT,
    fingerprint VARCHAR(64) DEFAULT NULL,
    points_earned INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_telegram_action (telegram_id, action_type),
    INDEX idx_fingerprint (fingerprint),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    telegram_id BIGINT NOT NULL,
    type ENUM('video_approved','video_rejected','new_follower','milestone','system') NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    data JSON DEFAULT NULL,
    is_sent BOOLEAN DEFAULT FALSE,
    is_read BOOLEAN DEFAULT FALSE,
    sent_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_telegram_status (telegram_id, is_sent, is_read),
    FOREIGN KEY (telegram_id) REFERENCES authors(telegram_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

 CREATE TABLE user_sessions (
     id INT AUTO_INCREMENT PRIMARY KEY,
     telegram_id BIGINT NOT NULL,
     token VARCHAR(64) NOT NULL,
     username VARCHAR(255) DEFAULT NULL,
     first_name VARCHAR(255) DEFAULT NULL,
     expires_at DATETIME NOT NULL,
     created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
     UNIQUE KEY uniq_telegram_id (telegram_id),
     UNIQUE KEY uniq_token (token),
     INDEX idx_expires_at (expires_at),
     FOREIGN KEY (telegram_id) REFERENCES authors(telegram_id) ON DELETE CASCADE
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
