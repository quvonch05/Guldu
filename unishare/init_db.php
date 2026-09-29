<?php
// unishare/init_db.php
header('Content-Type: text/html; charset=utf-8');
require_once __DIR__ . '/config/db.php';

try {
    // 1. users jadvali
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        full_name VARCHAR(100) NOT NULL,
        student_id VARCHAR(50) UNIQUE NOT NULL,
        phone VARCHAR(30) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        role ENUM('student', 'admin') DEFAULT 'student',
        balance DECIMAL(12, 2) DEFAULT 0.00,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // 2. products jadvali
    $pdo->exec("CREATE TABLE IF NOT EXISTS products (
        id INT AUTO_INCREMENT PRIMARY KEY,
        seller_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        price DECIMAL(12, 2) NOT NULL,
        category VARCHAR(100) DEFAULT 'Kitoblar',
        image_url VARCHAR(500) NULL,
        file_url VARCHAR(500) NULL,
        status ENUM('active', 'sold', 'deleted') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Agar jadval avval yaratilgan bo'lsa, file_url ustunini xavfsiz qo'shish
    try {
        $pdo->exec("ALTER TABLE products ADD COLUMN IF NOT EXISTS file_url VARCHAR(500) NULL AFTER image_url;");
    } catch (Exception $e) {}

    // 3. orders jadvali
    $pdo->exec("CREATE TABLE IF NOT EXISTS orders (
        id INT AUTO_INCREMENT PRIMARY KEY,
        buyer_id INT NOT NULL,
        product_id INT NOT NULL,
        amount DECIMAL(12, 2) NOT NULL,
        status ENUM('pending', 'completed', 'cancelled') DEFAULT 'completed',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (buyer_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // 4. billing_transactions jadvali (`system` himoyalangan so'zi bilan)
    $pdo->exec("CREATE TABLE IF NOT EXISTS billing_transactions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        `system` ENUM('click', 'payme') NOT NULL,
        transaction_id VARCHAR(100) UNIQUE NOT NULL,
        amount DECIMAL(12, 2) NOT NULL,
        status ENUM('pending', 'completed', 'cancelled') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // 5. system_settings jadvali (Admin kartasi va billing sozlamalari)
    $pdo->exec("CREATE TABLE IF NOT EXISTS system_settings (
        setting_key VARCHAR(50) PRIMARY KEY,
        setting_value TEXT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // ADMIN01 profilini tekshirish va kiritish
    $checkAdmin =$pdo->prepare("SELECT id FROM users WHERE student_id = ?");
    $checkAdmin->execute(['ADMIN01']);
    
    if (!$checkAdmin->fetch()) {$adminPass = password_hash('admin123', PASSWORD_BCRYPT);
        $insertAdmin =$pdo->prepare("INSERT INTO users (full_name, student_id, phone, password, role, balance) VALUES (?, ?, ?, ?, 'admin', 0.00)");
        $insertAdmin->execute(['Bosh Administrator', 'ADMIN01', '+998900000000',$adminPass]);
    }

    echo "<h2 style='color: green; font-family: sans-serif; text-align: center; margin-top: 50px;'>
            Barcha jadvallar (users, products, orders, billing, settings) muvaffaqiyatli tayyorlandi va ADMIN01 profili sozlandi!
          </h2>";

} catch (PDOException $e) {
    echo "<h2 style='color: red; font-family: sans-serif; text-align: center; margin-top: 50px;'>
            Xatolik yuz berdi: " . htmlspecialchars($e->getMessage()) . "
          </h2>";
}