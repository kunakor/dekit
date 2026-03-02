-- สร้าง Database และเลือกใช้งาน
CREATE DATABASE IF NOT EXISTS school_asset DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE school_asset;

-- 1. ตาราง Users (เก็บข้อมูลครู/นักเรียน)
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    fullname VARCHAR(100) NOT NULL,
    role ENUM('admin', 'staff') DEFAULT 'staff',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 2. ตาราง Assets (เก็บข้อมูลครุภัณฑ์)
CREATE TABLE assets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    asset_code VARCHAR(50) UNIQUE NOT NULL,
    asset_name VARCHAR(100) NOT NULL,
    category VARCHAR(50) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) DEFAULT 0.00,
    received_date DATE,
    status ENUM('available', 'in_use', 'repair', 'disposed') DEFAULT 'available',
    image VARCHAR(255) DEFAULT NULL,
    
    -- เพิ่ม 2 คอลัมน์นี้เพื่อระบบยืม-คืน
    borrowed_by VARCHAR(100) DEFAULT NULL,   -- เก็บชื่อคนยืม (เพื่อโชว์ในตารางง่ายๆ)
    current_user_id INT DEFAULT NULL,        -- เก็บ ID คนยืม (เพื่อเช็คสิทธิ์ตอนคืน)
    
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- 3. ตาราง Login Logs (เก็บประวัติการเข้าสู่ระบบ)
CREATE TABLE login_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id VARCHAR(50),
    ip_address VARCHAR(50),
    login_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 4. ตาราง Borrow Logs (เก็บประวัติการยืม-คืน อย่างละเอียด)
CREATE TABLE borrow_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    asset_id INT NOT NULL,
    user_id INT NOT NULL,
    action ENUM('borrow', 'return') NOT NULL, -- ยืม หรือ คืน
    log_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- --- ข้อมูลตัวอย่าง (Dummy Data) ---

-- 1. สร้าง Admin (User: admin / Pass: 123456)
INSERT INTO users (teacher_id, password, fullname, role) 
VALUES ('admin', '$2y$10$K.XyV.h9/8C5X.u.W/X.pO/X.u.W/X.pO/X.u.W/X.pO', 'Admin Teacher', 'admin');

-- 2. สร้าง User ครูทั่วไป (User: T001 / Pass: 123456)
INSERT INTO users (teacher_id, password, fullname, role) 
VALUES ('T001', '$2y$10$K.XyV.h9/8C5X.u.W/X.pO/X.u.W/X.pO/X.u.W/X.pO', 'ครูสมชาย ใจดี', 'staff');

-- 3. ข้อมูลครุภัณฑ์ตัวอย่าง
INSERT INTO assets (asset_code, asset_name, category, price, status) VALUES 
('NB-001', 'Notebook Acer', 'คอมพิวเตอร์', 25000, 'available'),
('PJ-001', 'Projector Sony', 'อุปกรณ์นำเสนอ', 15000, 'available'),
('PC-001', 'PC Dell Optiplex', 'คอมพิวเตอร์', 18000, 'available');