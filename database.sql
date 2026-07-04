CREATE DATABASE IF NOT EXISTS mathskh_db;
USE mathskh_db;

-- Users Table
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100),
    role ENUM('student', 'admin') DEFAULT 'student',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tests Table
CREATE TABLE tests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    level VARCHAR(50),
    exam_time_limit INT DEFAULT 60,
    created_by INT,
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- Exercises Table
CREATE TABLE exercises (
    id INT AUTO_INCREMENT PRIMARY KEY,
    test_id INT,
    question TEXT NOT NULL,
    opt_a TEXT,
    opt_b TEXT,
    opt_c TEXT,
    opt_d TEXT,
    correct_answer CHAR(1),
    score INT DEFAULT 10,
    answer_explanation TEXT,
    ans_url VARCHAR(255),
    FOREIGN KEY (test_id) REFERENCES tests(id) ON DELETE CASCADE
);

-- Certificates Table
CREATE TABLE certificates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    test_id INT,
    grade CHAR(1),
    earned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (test_id) REFERENCES tests(id)
);
