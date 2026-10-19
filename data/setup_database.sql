-- LifeTrack 360 - Database Setup Script
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS health_logs;
DROP TABLE IF EXISTS tasks;
DROP TABLE IF EXISTS schedules;
DROP TABLE IF EXISTS finance_transactions;
DROP TABLE IF EXISTS users;
SET FOREIGN_KEY_CHECKS = 1;

-- 1. ตารางผู้ใช้งาน (Users)
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role VARCHAR(20) DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. ตารางธุรกรรมการเงิน (Finance Transactions)
CREATE TABLE finance_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type ENUM('income', 'expense') NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    category VARCHAR(100) NOT NULL,
    description TEXT NULL,
    transaction_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. ตารางกำหนดการและนัดหมาย (Schedules)
CREATE TABLE schedules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    start_time DATETIME NOT NULL,
    end_time DATETIME NOT NULL,
    location VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. ตารางงานที่ต้องทำ (Tasks)
CREATE TABLE tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NULL,
    priority ENUM('low', 'medium', 'high') DEFAULT 'medium',
    due_date DATE NULL,
    status ENUM('pending', 'completed') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. ตารางสุขภาพและกิจวัตร (Health Logs)
CREATE TABLE health_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    water_intake_ml INT DEFAULT 0,
    sleep_hours DECIMAL(3, 1) DEFAULT 0,
    steps INT DEFAULT 0,
    weight_kg DECIMAL(4, 1) NULL,
    exercise_min INT DEFAULT 0,
    reading_pages INT DEFAULT 0,
    mood VARCHAR(20) DEFAULT 'happy',
    log_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================================
-- ข้อมูลตัวอย่างเริ่มต้น (Mockup Data)
-- ========================================================

-- บัญชีผู้ใช้ตัวอย่าง: รหัสผ่านคือ pass1234
INSERT INTO users (id, username, email, password_hash, role) VALUES
(1, 'folk', 'folk@example.com', '$2y$10$wE1VfW2rY6g.g9u0Xk4OhegB2qK.s3A5uKkJ7M0E1lD.z5uGkUe7K', 'admin');

-- ข้อมูลการเงินเริ่มต้น
INSERT INTO finance_transactions (user_id, type, amount, category, description, transaction_date) VALUES
(1, 'income', 25000.00, 'เงินเดือน', 'เงินเดือนประจำเดือน', CURDATE()),
(1, 'expense', 150.00, 'อาหารและเครื่องดื่ม', 'อาหารกลางวันและกาแฟ', CURDATE()),
(1, 'expense', 450.00, 'เดินทาง/ค่าน้ำมัน', 'เติมน้ำมันรถ', CURDATE());

-- ข้อมูลนัดหมายเริ่มต้น
INSERT INTO schedules (user_id, title, start_time, end_time, location) VALUES
(1, 'ประชุมโปรเจกต์ Web App', DATE_ADD(NOW(), INTERVAL 2 HOUR), DATE_ADD(NOW(), INTERVAL 3 HOUR), 'ห้อง Zoom ออนไลน์'),
(1, 'ส่งงาน Final Project', DATE_ADD(NOW(), INTERVAL 1 DAY), DATE_ADD(NOW(), INTERVAL 2 DAY), 'มหาวิทยาลัย');

-- ข้อมูลงานที่ต้องทำเริ่มต้น
INSERT INTO tasks (user_id, title, description, priority, due_date, status) VALUES
(1, 'ทำการบ้าน Web Development', 'เขียนรายงานและเตรียมส่ง Source Code', 'high', DATE_ADD(CURDATE(), INTERVAL 3 DAY), 'pending'),
(1, 'ตรวจเช็คความปลอดภัย Login', 'เปลี่ยนหน้า Login ให้เป็น UI สไตล์คลีน', 'medium', CURDATE(), 'completed');

-- ข้อมูลสุขภาพประจำวัน
INSERT INTO health_logs (user_id, water_intake_ml, sleep_hours, steps, weight_kg, exercise_min, reading_pages, mood, log_date) VALUES
(1, 2000, 7.5, 8500, 65.0, 30, 20, 'great', CURDATE());