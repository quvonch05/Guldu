<?php
// unishare/api/admin_actions.php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';

$data = json_decode(file_get_contents('php://input'), true);
$action = $data['action'] ?? '';
$admin_id = (int)($data['admin_id'] ?? 0);

// Admin huquqini tekshirish
$stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
$stmt->execute([$admin_id]);
$user = $stmt->fetch();
if (!$user || $user['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Ruxsat berilmagan! Faqat administratorlar uchun.']);
    exit;
}

try {
    // 1. Foydalanuvchilar va umumiy statistikani olish
    if ($action === 'get_stats') {
        $usersCount = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
        $productsCount = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
        $transactionsSum = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM billing_transactions WHERE status = 'completed'")->fetchColumn();
        
        $users = $pdo->query("SELECT id, student_id, full_name, phone, role, balance, created_at FROM users ORDER BY id DESC LIMIT 50")->fetchAll();
        $products = $pdo->query("SELECT p.*, u.full_name as author_name FROM products p JOIN users u ON p.seller_id = u.id ORDER BY p.id DESC LIMIT 50")->fetchAll();
        
        echo json_encode([
            'status' => 'success',
            'stats' => ['users' => $usersCount, 'products' => $productsCount, 'volume' => $transactionsSum],
            'users' => $users,
            'products' => $products
        ]);
        exit;
    }

    // 2. Foydalanuvchi parolini o'zgartirish
    if ($action === 'reset_password') {
        $target_user_id = (int)($data['target_user_id'] ?? 0);
        $new_password = $data['new_password'] ?? '';
        if (!$target_user_id || strlen($new_password) < 4) {
            throw new Exception("Yangi parol kamida 4 belgidan iborat bo‘lishi shart.");
        }
        $hash = password_hash($new_password, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([$hash, $target_user_id]);
        echo json_encode(['status' => 'success', 'message' => 'Foydalanuvchi paroli muvaffaqiyatli yangilandi!']);
        exit;
    }

    // 3. To'lov tizimlari kalitlarini saqlash
    if ($action === 'save_payment_config') {
        $pdo->exec("CREATE TABLE IF NOT EXISTS system_settings (
            setting_key VARCHAR(50) PRIMARY KEY,
            setting_value TEXT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        $configs = [
            'click_service_id' => $data['click_service_id'] ?? '',
            'click_merchant_id' => $data['click_merchant_id'] ?? '',
            'click_secret_key' => $data['click_secret_key'] ?? '',
            'payme_merchant_id' => $data['payme_merchant_id'] ?? '',
            'payme_secret_key'  => $data['payme_secret_key'] ?? ''
        ];

        $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        foreach ($configs as $k => $v) {
            $stmt->execute([$k, $v]);
        }
        echo json_encode(['status' => 'success', 'message' => 'To‘lov tizimi kalitlari muvaffaqiyatli saqlandi!']);
        exit;
    }

    // 4. E'lonni o'chirish
    if ($action === 'delete_product') {
        $product_id = (int)($data['product_id'] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
        $stmt->execute([$product_id]);
        echo json_encode(['status' => 'success', 'message' => 'Mahsulot o‘chirildi!']);
        exit;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}