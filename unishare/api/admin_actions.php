<?php
// unishare/api/admin_actions.php
ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Faqat POST so‘rovlar qabul qilinadi!']);
    exit;
}

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

$action = $data['action'] ?? '';
$admin_id = (int)($data['admin_id'] ?? 0);

try {
    // Admin huquqini tekshirish
    $stmt = $pdo->prepare("SELECT id, role, password FROM users WHERE id = ?");
    $stmt->execute([$admin_id]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$admin || $admin['role'] !== 'admin') {
        echo json_encode(['status' => 'error', 'message' => 'Ruxsat yo‘q! Faqat administratorlar uchun.']);
        exit;
    }

    // Sozlamalar jadvali mavjudligini ta'minlash
    $pdo->exec("CREATE TABLE IF NOT EXISTS system_settings (
        setting_key VARCHAR(50) PRIMARY KEY,
        setting_value TEXT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // 1. STATISTIKA VA RO'YXATLARNI REAL VAQTDA OLISH
    if ($action === 'get_stats') {
        // Talabalar soni
        $usersCount = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();

        // Faol e'lonlar soni
        $productsCount = (int)$pdo->query("SELECT COUNT(*) FROM products WHERE status = 'active'")->fetchColumn();

        // Umumiy savdo aylanmasi
        $totalTurnover = (float)$pdo->query("SELECT COALESCE(SUM(amount), 0) FROM orders WHERE status = 'completed'")->fetchColumn();

        // Admin hisobidagi sof foyda (komissiya)
        $adminBalStmt = $pdo->prepare("SELECT balance FROM users WHERE id = ?");
        $adminBalStmt->execute([$admin_id]);
        $adminBalance = (float)$adminBalStmt->fetchColumn();

        // Foydalanuvchilar ro'yxati
        $users = $pdo->query("SELECT id, student_id, full_name, phone, role, balance, created_at FROM users ORDER BY id DESC LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);

        // E'lonlar ro'yxati (xavfsiz JOIN faqat seller_id orqali)
        $products = $pdo->query("
            SELECT 
                p.id, 
                p.title, 
                p.category, 
                p.price, 
                p.status, 
                p.created_at, 
                COALESCE(u.full_name, 'Noma\'lum') AS author_name 
            FROM products p 
            LEFT JOIN users u ON p.seller_id = u.id 
            ORDER BY p.id DESC 
            LIMIT 100
        ")->fetchAll(PDO::FETCH_ASSOC);

        // Sozlamalar
        $settingsRows = $pdo->query("SELECT setting_key, setting_value FROM system_settings")->fetchAll(PDO::FETCH_ASSOC);
        $settings = [];
        foreach ($settingsRows as $row) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }

        echo json_encode([
            'status' => 'success',
            'stats' => [
                'users' => $usersCount,
                'products' => $productsCount,
                'turnover' => $totalTurnover,
                'admin_balance' => $adminBalance
            ],
            'users' => $users ?: [],
            'products' => $products ?: [],
            'settings' => $settings
        ]);
        exit;
    }

    // 2. ADMINNING O'Z PAROLINI XAVFSIZ O'ZGARTIRISHI
    if ($action === 'change_admin_password') {
        $current_password = $data['current_password'] ?? '';
        $new_password     = $data['new_password'] ?? '';

        if (!$current_password || !$new_password) {
            echo json_encode(['status' => 'error', 'message' => 'Barcha maydonlarni to‘ldiring!']);
            exit;
        }

        if (strlen($new_password) < 6) {
            echo json_encode(['status' => 'error', 'message' => 'Yangi parol kamida 6 ta belgidan iborat bo‘lishi lozim!']);
            exit;
        }

        if (!password_verify($current_password, $admin['password'])) {
            echo json_encode(['status' => 'error', 'message' => 'Joriy admin paroli noto‘g‘ri kiritildi!']);
            exit;
        }

        $newHash = password_hash($new_password, PASSWORD_BCRYPT);
        $updateStmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $updateStmt->execute([$newHash, $admin_id]);

        echo json_encode(['status' => 'success', 'message' => 'Admin paroli muvaffaqiyatli yangilandi!']);
        exit;
    }

    // 3. E'LONNI BUTUNLAY O'CHIRISH
    if ($action === 'delete_product') {
        $product_id = (int)($data['product_id'] ?? 0);
        $delStmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
        $delStmt->execute([$product_id]);

        echo json_encode(['status' => 'success', 'message' => "E'lon muvaffaqiyatli o‘chirildi!"]);
        exit;
    }

    // 4. ADMIN KARTASI VA SHLYUZ SOZLAMALARINI SAQLASH
    if ($action === 'save_admin_settings') {
        $settingsToSave = [
            'admin_card_number' => preg_replace('/\s+/', '', $data['admin_card_number'] ?? ''),
            'admin_card_holder' => trim($data['admin_card_holder'] ?? ''),
            'click_service_id'  => trim($data['click_service_id'] ?? ''),
            'click_merchant_id' => trim($data['click_merchant_id'] ?? ''),
            'click_secret_key'  => trim($data['click_secret_key'] ?? ''),
            'payme_merchant_id' => trim($data['payme_merchant_id'] ?? ''),
            'payme_secret_key'  => trim($data['payme_secret_key'] ?? '')
        ];

        $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        foreach ($settingsToSave as $key => $val) {
            $stmt->execute([$key, $val]);
        }

        echo json_encode(['status' => 'success', 'message' => 'Sozlamalar va karta ma’lumotlari saqlandi!']);
        exit;
    }

    // 5. TALABA PAROLINI TIKLASH / YANGILASH
    if ($action === 'reset_password') {
        $target_user_id = (int)($data['target_user_id'] ?? 0);
        $new_password   = $data['new_password'] ?? '';

        if (!$target_user_id || strlen($new_password) < 4) {
            echo json_encode(['status' => 'error', 'message' => 'Foydalanuvchi ID va kamida 4 belgili yangi parolni kiriting!']);
            exit;
        }

        $hash = password_hash($new_password, PASSWORD_BCRYPT);
        $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$hash, $target_user_id]);

        echo json_encode(['status' => 'success', 'message' => 'Talaba paroli muvaffaqiyatli yangilandi!']);
        exit;
    }

    // 6. ADMINGA TEGUVCHI FOYDANI KARTASIGA YECHIB OLISH
    if ($action === 'withdraw_admin_profit') {
        $amount = (float)($data['amount'] ?? 0);
        $adminBal = (float)$pdo->query("SELECT balance FROM users WHERE id = {$admin_id}")->fetchColumn();

        if ($amount <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'Yechish summasini to‘g‘ri kiriting!']);
            exit;
        }

        if ($amount > $adminBal) {
            echo json_encode(['status' => 'error', 'message' => 'Admin balansida buncha mablag‘ mavjud emas!']);
            exit;
        }

        $cardNum = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'admin_card_number'")->fetchColumn();
        if (!$cardNum || strlen($cardNum) < 16) {
            echo json_encode(['status' => 'error', 'message' => 'Avval sozlamalardan admin kartasini to‘liq kiriting va saqlang!']);
            exit;
        }

        $pdo->beginTransaction();
        $pdo->prepare("UPDATE users SET balance = balance - ? WHERE id = ?")->execute([$amount, $admin_id]);

        $payoutTxId = 'PAYOUT_' . time() . '_' . rand(100, 999);
        $pdo->prepare("INSERT INTO billing_transactions (user_id, `system`, transaction_id, amount, status) VALUES (?, 'click', ?, ?, 'completed')")
            ->execute([$admin_id, $payoutTxId, $amount]);
        $pdo->commit();

        echo json_encode([
            'status' => 'success',
            'message' => number_format($amount, 0, '', ' ') . " UZS mablag‘ {$cardNum} kartasiga muvaffaqiyatli yo‘naltirildi!"
        ]);
        exit;
    }

    echo json_encode(['status' => 'error', 'message' => 'Noma’lum amal!']);

} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['status' => 'error', 'message' => 'Server xatosi: ' . $e->getMessage()]);
}
