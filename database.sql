-- ============================================================
-- Maths KH - Database Schema
-- Mirrors the data currently stored in the browser's localStorage
-- (mathskh_users, mathskh_tests, mathskh_ledSettings,
--  mathskh_certSettings, mathskh_appDetails)
-- ============================================================

SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE DATABASE IF NOT EXISTS `mathskh`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE `mathskh`;

-- ------------------------------------------------------------
-- Table: users
-- Mirrors: mathskh_users -> { username, password, role, fullName, certificates }
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
    `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `username`   VARCHAR(100) NOT NULL,
    `password`   VARCHAR(255) NOT NULL,       -- store a hashed password (password_hash)
    `full_name`  VARCHAR(255) NOT NULL,
    `role`       ENUM('admin', 'student') NOT NULL DEFAULT 'student',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_users_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Table: tests
-- Mirrors: mathskh_tests -> { id, level, title, examTimeLimit, exercises: [...] }
-- Note: `id` is BIGINT because the front-end currently generates
-- ids with JS Date.now(); kept compatible if that data is migrated.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `tests` (
    `id`                BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `level`             VARCHAR(50) NOT NULL,      -- e.g. 'Diploma' or 'Bac2'
    `title`             VARCHAR(255) NOT NULL,
    `exam_time_limit`   INT UNSIGNED NOT NULL DEFAULT 60, -- minutes
    `created_at`        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY `idx_tests_level` (`level`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Table: exercises
-- Mirrors each item in a test's `exercises` array:
-- { question, options: {A,B,C,D}, correct, score, answer, ansUrl }
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `exercises` (
    `id`              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `test_id`         BIGINT UNSIGNED NOT NULL,
    `sort_order`      INT UNSIGNED NOT NULL DEFAULT 0,
    `question`        TEXT NOT NULL,
    `option_a`        TEXT NOT NULL,
    `option_b`        TEXT NOT NULL,
    `option_c`        TEXT NOT NULL,
    `option_d`        TEXT NOT NULL,
    `correct_option`  ENUM('A','B','C','D') NOT NULL,
    `score`           DECIMAL(6,2) NOT NULL DEFAULT 10,
    `answer`          TEXT NULL,        -- text/KaTeX explanation
    `ans_url`         VARCHAR(1000) NULL, -- optional image/link for the solution
    `created_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_exercises_test_id` (`test_id`),
    CONSTRAINT `fk_exercises_test`
        FOREIGN KEY (`test_id`) REFERENCES `tests` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Table: certificates
-- Mirrors: currentUser.certificates -> [{ testId, testTitle, grade, date }]
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `certificates` (
    `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id`     INT UNSIGNED NOT NULL,
    `test_id`     BIGINT UNSIGNED NOT NULL,
    `test_title`  VARCHAR(255) NOT NULL,
    `grade`       CHAR(1) NOT NULL,           -- A, B, C, D, E, F
    `awarded_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_user_test` (`user_id`, `test_id`), -- prevents duplicate certs for same test
    CONSTRAINT `fk_certificates_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_certificates_test`
        FOREIGN KEY (`test_id`) REFERENCES `tests` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Table: led_settings (single row)
-- Mirrors: mathskh_ledSettings -> { text, textColor, bgColor }
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `led_settings` (
    `id`         TINYINT UNSIGNED PRIMARY KEY DEFAULT 1,
    `text`       TEXT NOT NULL,
    `text_color` VARCHAR(20) NOT NULL DEFAULT '#ffffff',
    `bg_color`   VARCHAR(20) NOT NULL DEFAULT '#ff0000',
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `chk_led_settings_single_row` CHECK (`id` = 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Table: cert_settings (single row)
-- Mirrors: mathskh_certSettings ->
-- { logoUrl, logoData, borderData, sigData, descriptionTemplate, signature }
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `cert_settings` (
    `id`                    TINYINT UNSIGNED PRIMARY KEY DEFAULT 1,
    `logo_url`              VARCHAR(1000) NULL,
    `logo_data`             LONGTEXT NULL,   -- base64 data URL
    `border_data`           LONGTEXT NULL,   -- base64 data URL
    `sig_data`              LONGTEXT NULL,   -- base64 data URL
    `description_template`  TEXT NULL,
    `signature`             VARCHAR(255) NULL,
    `updated_at`            TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `chk_cert_settings_single_row` CHECK (`id` = 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Table: app_details (single row)
-- Mirrors: mathskh_appDetails -> { app, features, levels, version }
-- Also stores the selected theme color (mathskh_color).
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `app_details` (
    `id`           TINYINT UNSIGNED PRIMARY KEY DEFAULT 1,
    `app_name`     VARCHAR(255) NOT NULL DEFAULT 'Maths KH',
    `features`     TEXT NULL,
    `levels`       VARCHAR(255) NULL,
    `version`      VARCHAR(50) NOT NULL DEFAULT '1.0.0',
    `theme_color`  VARCHAR(50) NOT NULL DEFAULT 'navy',
    `updated_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `chk_app_details_single_row` CHECK (`id` = 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Seed data (matches the JS default values in index.html)
-- ============================================================

-- Default admin account (username: admin / password: 1234)
-- NOTE: the plaintext '1234' below is only a placeholder so the
-- table isn't empty. database.php hashes passwords with
-- password_hash() on registration/update, so replace this seed
-- row's password via the app rather than relying on this hash.
INSERT INTO `users` (`username`, `password`, `full_name`, `role`)
VALUES ('admin', '$2y$10$C7QeQY2S1lvvNfW4qxOe2eN9Z1E1zHJmS8yGqUZ8gvXwGdG0uAV0e', 'អ្នកគ្រប់គ្រង (Admin)', 'admin')
ON DUPLICATE KEY UPDATE `username` = `username`;
-- The hash above corresponds to the password: 1234

INSERT INTO `led_settings` (`id`, `text`, `text_color`, `bg_color`)
VALUES (1,
    'Maths KH សូមស្វាគមន៍! សូមចូលទៅពង្រឹងចំណេះដឹង និងប្រលងសាកល្បងដើម្បីរង្វាយតម្លៃសមត្ថភាពខ្លួនដែលបានរៀនកន្លងមក។ សូមអរគុណ!',
    '#ffffff',
    '#ff0000'
) ON DUPLICATE KEY UPDATE `id` = `id`;

INSERT INTO `cert_settings` (`id`, `logo_url`, `logo_data`, `border_data`, `sig_data`, `description_template`, `signature`)
VALUES (1,
    '', '', '', '',
    'សូមអរគុណចំពោះការខិតខំប្រឹងប្រែងប្រឡងគណិតវិទ្យាសាកល្បងនៅប្រព័ន្ធជំនួយការសិក្សា ដែលទទួលបានលទ្ធផលជាទីគាប់ចិត្ត ជាមួយនឹងនិទ្ទេសខ្ពស់សាកសមនឹងសមត្ថភាពពិតប្រាកដ។',
    'គណៈគ្រប់គ្រង Maths KH'
) ON DUPLICATE KEY UPDATE `id` = `id`;

INSERT INTO `app_details` (`id`, `app_name`, `features`, `levels`, `version`, `theme_color`)
VALUES (1,
    'Maths KH',
    'ធ្វើវិញ្ញាសាគណិតវិទ្យា (Math Test Practice)',
    'ឌីប្លូម (ថ្នាក់ទី ៩) & បាក់ឌុប (ថ្នាក់ទី ១២)',
    '1.0.0',
    'navy'
) ON DUPLICATE KEY UPDATE `id` = `id`;
