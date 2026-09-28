-- Lottery System Database Schema
-- Database: lottery_example
--
-- Import manually with:
--   mysql -u YOUR_USER -p < sql/schema.sql
-- or from phpMyAdmin (Import tab).
-- Then edit includes/config.php with your DB credentials and BACKUP_DIR.

CREATE DATABASE IF NOT EXISTS lottery_example DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;

USE lottery_example;

-- Administrators table
-- (Includes a default test admin user at the end of this file.)
CREATE TABLE IF NOT EXISTS administrators (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Participants table (up to 3 numbers per participant)
-- Numbers are CHAR(4): 3-digit mode stores 000-999, 4-digit mode 0000-9999.
CREATE TABLE IF NOT EXISTS participants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    number CHAR(4) NOT NULL UNIQUE,
    number2 CHAR(4) DEFAULT NULL,
    number3 CHAR(4) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_name (name),
    INDEX idx_number (number),
    UNIQUE INDEX idx_number2 (number2),
    UNIQUE INDEX idx_number3 (number3)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Blacklist table
CREATE TABLE IF NOT EXISTS blacklist (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) UNIQUE NOT NULL,
    reason TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_blacklist_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- News table
CREATE TABLE IF NOT EXISTS news (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    content TEXT NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Custom texts table
CREATE TABLE IF NOT EXISTS custom_texts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    `key` VARCHAR(50) UNIQUE NOT NULL,
    content TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Prizes table
CREATE TABLE IF NOT EXISTS prizes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    position INT NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Winners table
CREATE TABLE IF NOT EXISTS winners (
    id INT AUTO_INCREMENT PRIMARY KEY,
    participant_id INT NOT NULL,
    prize_id INT NOT NULL,
    number CHAR(4) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (participant_id) REFERENCES participants(id) ON DELETE CASCADE,
    FOREIGN KEY (prize_id) REFERENCES prizes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Sponsors table
CREATE TABLE IF NOT EXISTS sponsors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    image_path VARCHAR(255) NOT NULL DEFAULT '',
    text VARCHAR(500) NOT NULL DEFAULT '',
    link VARCHAR(255) NOT NULL DEFAULT '',
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Login attempts table (brute-force protection)
CREATE TABLE IF NOT EXISTS login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL,
    attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ip (ip_address)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Draw audits table (verifiable proof of each generated/entered draw number).
-- No IP is stored (per project requirements).
CREATE TABLE IF NOT EXISTS draw_audits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    prize_id INT NOT NULL,
    mode VARCHAR(20) NOT NULL,
    drawn_number CHAR(4) NOT NULL,
    winning_number CHAR(4) DEFAULT NULL,
    participant_id INT DEFAULT NULL,
    provider VARCHAR(20) DEFAULT NULL,
    external_raw VARCHAR(255) DEFAULT NULL,
    proof_hash CHAR(64) NOT NULL,
    admin_id INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    confirmed_at TIMESTAMP NULL,
    FOREIGN KEY (prize_id) REFERENCES prizes(id) ON DELETE CASCADE,
    INDEX idx_draw_prize (prize_id),
    INDEX idx_draw_confirmed (confirmed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Default custom texts
INSERT IGNORE INTO custom_texts (`key`, content) VALUES
('site_title', 'Lottery System'),
('header_info', '<h2>Welcome to the Official Lottery</h2><p>Check your numbers and good luck!</p>'),
('footer_info', '<p>Lottery System &copy; 2026. All rights reserved.</p>'),
('lottery_status', 'open'),
('maintenance_mode', 'off'),
('maintenance_message', '<h2>Site under maintenance</h2><p>We will be back soon.</p>'),
('logo_path', ''),
('status_open_message', 'You can now register and choose your numbers.'),
('status_closed_message', 'Registration is currently closed. Check back later.'),
('participant_create_success', '✅ Participant {name} has been registered successfully!\nNumbers: {numbers}'),
('participant_update_success', '✅ Participant {name} has been updated successfully!\nNumbers: {numbers}'),
('participant_auto_assign', '⚠️ Number {requested} was taken. Auto-assigned {assigned} instead.'),
('participant_duplicate', '❌ The name ''{name}'' is already registered.'),
('participant_blacklisted', '❌ The name ''{name}'' is blacklisted and cannot be added.'),
('participant_error', '❌ {reason}'),
('draw_mode', 'manual'),
('number_digits', '3'),
('unique_winners', '0'),
('admin_lang_default', 'en');

-- Default test admin user (username: admin, password: admin2026).
-- Created automatically on both automatic and manual installs.
-- IMPORTANT: change this password right after logging in (admin panel
-- menu "Change Password") or delete the user and create your own.
INSERT IGNORE INTO administrators (username, password) VALUES
('admin', '$2b$10$tNAmmu3O7XIYgMV2iFQjgutQN0TLezTVLG4MJii6of9oWAjkjeIzC');
