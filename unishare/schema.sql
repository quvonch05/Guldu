CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    student_id VARCHAR(30) UNIQUE NOT NULL,
    phone VARCHAR(20) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    balance DECIMAL(12, 2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

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

CREATE TABLE IF NOT EXISTS billing_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    order_id INT NULL,
    click_trans_id BIGINT NULL,
    amount DECIMAL(12, 2) NOT NULL,
    state INT DEFAULT 0,
    sign_time VARCHAR(50) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB;

-- Test uchun dastlabki talaba va mahsulotlar (Dastlabki ma'lumot)
INSERT INTO users (id, full_name, student_id, phone, password_hash, balance) 
VALUES (1, 'Talaba Sinov', 'STU1001', '+998901234567', '$2y$10$w8TKnPZ0tWkU4u34T/kRMezHwKq0g01.gq4tI.3tG/RjY6sPZ/gS2', 50000.00)
ON DUPLICATE KEY UPDATE id=id;

INSERT INTO products (user_id, title, description, price, file_path, type) 
VALUES 
(1, 'Ma''lumotlar bazasi (MySQL/PostgreSQL) — Oraliq nazorat to‘liq konspekti', '5-semestr uchun tayyorlangan savol-javoblar va amaliy kodlar.', 12000.00, 'db_note.pdf', 'digital_note'),
(1, 'Tezkor A4 chop etish (3-TTJ, 312-xona)', 'LaserJet printer. Oq-qora chop etish. Narxi bitta varaq uchun.', 400.00, NULL, 'print_service'),
(1, 'Algoritmlar va ma''lumotlar tuzilmasi (C++) — Darslik kitob', 'Holati yangidek. O''tgan yili olingan.', 35000.00, NULL, 'book_escrow')
ON DUPLICATE KEY UPDATE id=id;