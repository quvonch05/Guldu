-- ========================================================
-- UniShare Platformasi — To‘liq Ma’lumotlar Bazasi Sxemasi
-- ========================================================

-- Bazani yaratish (agar mavjud bo'lmasa)
CREATE DATABASE IF NOT EXISTS unishare_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE unishare_db;

-- --------------------------------------------------------
-- 1. Foydalanuvchilar (Talabalar va Adminlar)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    student_id VARCHAR(30) UNIQUE NOT NULL,
    phone VARCHAR(20) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    balance DECIMAL(12, 2) DEFAULT 0.00,
    role ENUM('student', 'admin') DEFAULT 'student',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- --------------------------------------------------------
-- 2. Materiallar va Xizmatlar (Konspekt, Kitob, Print)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    price DECIMAL(10, 2) NOT NULL,
    file_path VARCHAR(255) NULL,
    type ENUM('digital_note', 'print_service', 'book_escrow') NOT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- --------------------------------------------------------
-- 3. Buyurtmalar va Xavfsiz Bitimlar (Escrow)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    buyer_id INT NOT NULL,
    seller_id INT NOT NULL,
    product_id INT NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    commission_fee DECIMAL(10, 2) DEFAULT 0.00,
    escrow_status ENUM('pending', 'frozen', 'completed', 'refunded') DEFAULT 'pending',
    secret_qr_code VARCHAR(64) UNIQUE NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (buyer_id) REFERENCES users(id),
    FOREIGN KEY (seller_id) REFERENCES users(id),
    FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB;

-- --------------------------------------------------------
-- 4. To'lov shlyuzi tranzaksiyalari (Click Merchant Billing)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS billing_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    order_id INT NULL,
    click_trans_id BIGINT NULL,
    amount DECIMAL(12, 2) NOT NULL,
    state INT DEFAULT 0, -- 0: Kutilmoqda, 1: Tayyorlandi, 2: To‘landi, -1: Bekor qilindi
    sign_time VARCHAR(50) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB;

-- ========================================================
-- Dastlabki Sinov Ma’lumotlari (Seed Data)
-- ========================================================

-- A. Standart Foydalanuvchilar (Admin va Sinov Talabasi)
-- Eslatma: Hash parollar BCRYPT orqali yaratilgan:
-- 'admin123' paroli uchun: $2y$10$w8TKnPZ0tWkU4u34T/kRMezHwKq0g01.gq4tI.3tG/RjY6sPZ/gS2
-- 'student123' paroli uchun: $2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi

INSERT INTO users (id, full_name, student_id, phone, password_hash, balance, role) 
VALUES 
(1, 'Tizim Administratori', 'ADMIN01', '+998900000000', '$2y$10$w8TKnPZ0tWkU4u34T/kRMezHwKq0g01.gq4tI.3tG/RjY6sPZ/gS2', 0.00, 'admin'),
(2, 'Talaba Sinov', 'STU1001', '+998901234567', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 50000.00, 'student')
ON DUPLICATE KEY UPDATE id=id;

-- B. Standart Namunaviy E’lonlar
INSERT INTO products (id, user_id, title, description, price, file_path, type, status) 
VALUES 
(1, 1, 'Ma''lumotlar bazasi (MySQL/PostgreSQL) — Oraliq nazorat to‘liq konspekti', '5-semestr uchun tayyorlangan savol-javoblar, SQL so‘rov namunalari bilan birga.', 12000.00, 'db_note.pdf', 'digital_note', 'active'),
(2, 2, 'Tezkor A4 chop etish (3-TTJ, 312-xona)', 'LaserJet printer. 1 varaq oq-qora = 400 so‘m. Faylingizni yuklang va tayyor holda oling.', 400.00, NULL, 'print_service', 'active'),
(3, 2, 'Algoritmlar va ma''lumotlar tuzilmasi (C++)', 'Holati yangidek. 1-kurs talabalari uchun asosiy darslik. Universitet hududida qo‘lma-qo‘l topshiriladi.', 35000.00, NULL, 'book_escrow', 'active')
ON DUPLICATE KEY UPDATE id=id;