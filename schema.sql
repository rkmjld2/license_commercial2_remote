CREATE DATABASE IF NOT EXISTS license_demo_v2;
USE license_demo_v2;

CREATE TABLE IF NOT EXISTS licenses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id VARCHAR(50) NOT NULL UNIQUE,
    status ENUM('ON','OFF') NOT NULL DEFAULT 'OFF',
    license_mode ENUM('CALENDAR','USAGE') NOT NULL DEFAULT 'CALENDAR',
    duration_seconds BIGINT NOT NULL DEFAULT 3600,
    started_at DATETIME NULL,
    expires_at DATETIME NULL,
    used_seconds BIGINT NOT NULL DEFAULT 0,
    last_seen_at DATETIME NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

INSERT INTO licenses (user_id,status,license_mode,duration_seconds)
VALUES ('USER001','OFF','CALENDAR',3600)
ON DUPLICATE KEY UPDATE user_id=VALUES(user_id);