<?php
require_once __DIR__ . '/config/db.php';

try {
    // 1. users jadvali
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id VARCHAR(50) UNIQUE NOT NULL,
        full_name VARCHAR(100) NOT NULL,
        email VARCHAR(100),
        phone VARCHAR(30),
        role ENUM('student', 'admin') DEFAULT 'student',
        balance DECIMAL(12, 2) DEFAULT 0.00,
        password VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // 2. products jadvali
    $pdo->exec("CREATE TABLE IF NOT EXISTS products (
        id INT AUTO_INCREMENT PRIMARY KEY,
        seller_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        price DECIMAL(12, 2) NOT NULL,
        category VARCHAR(50) DEFAULT 'general',
        status ENUM('active', 'sold', 'pending') DEFAULT 'active',
        image_url VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // 3. orders jadvali
    $pdo->exec("CREATE TABLE IF NOT EXISTS orders (
        id INT AUTO_INCREMENT PRIMARY KEY,
        buyer_id INT NOT NULL,
        product_id INT NOT NULL,
        amount DECIMAL(12, 2) NOT NULL,
        status ENUM('completed', 'cancelled') DEFAULT 'completed',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (buyer_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // 4. billing_transactions jadvali (Click / Payme uchun)
    $pdo->exec("CREATE TABLE IF NOT EXISTS billing_transactions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        system ENUM('click', 'payme') NOT NULL,
        transaction_id VARCHAR(100) UNIQUE NOT NULL,
        amount DECIMAL(12, 2) NOT NULL,
        status ENUM('pending', 'completed', 'cancelled') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Standart admin profilini qo'shish (agar yo'q bo'lsa)
    $adminPassword = password_hash('admin123', PASSWORD_DEFAULT);
    $checkAdmin = $pdo->prepare("SELECT id FROM users WHERE student_id = 'ADMIN01'");
    $checkAdmin->execute();
    if (!$checkAdmin->fetch()) {
        $pdo->prepare("INSERT INTO users (student_id, full_name, email, role, password) VALUES ('ADMIN01', 'Administrator', 'admin@guldu.uz', 'admin', ?)")
            ->execute([$adminPassword]);
    }

    echo "<h3>Barcha jadvallar (users, products, orders, billing) muvaffaqiyatli yaratildi va ADMIN01 profili qo'shildi!</h3>";

} catch (Exception $e) {
    echo "Xatolik: " . $e->getMessage();
}