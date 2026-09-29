<?php
// api/admin.php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';

$data = json_decode(file_get_contents('php://input'), true);
$admin_id = (int)($data['admin_id'] ?? 0);
$action = $data['action'] ?? '';

// Adminlik huquqini tekshirish
$stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
$stmt->execute([$admin_id]);
$user = $stmt->fetch();

if (!$user || $user['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Ruxsat berilmadi: Faqat administratorlar uchun']);
    exit;
}

// 1. Dashboard statistikasi
if ($action === 'get_stats') {
    // Jami foydalanuvchilar
    $total_users = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    
    // Jami e'lonlar
    $total_products = $pdo->query("SELECT COUNT(*) FROM products WHERE status = 'active'")->fetchColumn();

    // Jami Click tushumlari (state = 2)
    $total_revenue = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM billing_transactions WHERE state = 2")->fetchColumn();

    // Jami platforma olgan komissiya daromadi
    $total_commission = $pdo->query("SELECT COALESCE(SUM(commission_fee), 0) FROM orders WHERE escrow_status = 'completed'")->fetchColumn();

    // Oxirgi 10 ta tranzaksiya
    $recent_tx = $pdo->query("SELECT bt.*, u.full_name FROM billing_transactions bt JOIN users u ON bt.user_id = u.id ORDER BY bt.id DESC LIMIT 10")->fetchAll();

    // Barcha foydalanuvchilar
    $users_list = $pdo->query("SELECT id, full_name, student_id, phone, balance, role, created_at FROM users ORDER BY id DESC LIMIT 20")->fetchAll();

    echo json_encode([
        'status' => 'success',
        'stats' => [
            'total_users' => (int)$total_users,
            'total_products' => (int)$total_products,
            'total_revenue' => (float)$total_revenue,
            'total_commission' => (float)$total_commission
        ],
        'recent_transactions' => $recent_tx,
        'users' => $users_list
    ]);
    exit;
}

// 2. Mahsulotni o'chirish / moderatsiya qilish
if ($action === 'delete_product') {
    $product_id = (int)($data['product_id'] ?? 0);
    $stmt = $pdo->prepare("UPDATE products SET status = 'inactive' WHERE id = ?");
    $stmt->execute([$product_id]);
    echo json_encode(['status' => 'success', 'message' => 'E’lon o‘chirildi']);
    exit;
}