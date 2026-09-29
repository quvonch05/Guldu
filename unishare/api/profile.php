<?php
// api/profile.php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';

$data = json_decode(file_get_contents('php://input'), true);
$action = $data['action'] ?? '';
$user_id = (int)($data['user_id'] ?? 0);

if (!$user_id) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Avtorizatsiyadan o‘tilmagan']);
    exit;
}

// 1. Foydalanuvchi xaridlari
if ($action === 'get_purchases') {
    $stmt = $pdo->prepare("SELECT o.*, p.title, p.type, p.file_path, u.full_name as seller_name 
                           FROM orders o 
                           JOIN products p ON o.product_id = p.id 
                           JOIN users u ON o.seller_id = u.id 
                           WHERE o.buyer_id = ? 
                           ORDER BY o.id DESC");
    $stmt->execute([$user_id]);
    echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll()]);
    exit;
}

// 2. Foydalanuvchi joylagan e'lonlar
if ($action === 'get_my_products') {
    $stmt = $pdo->prepare("SELECT * FROM products WHERE user_id = ? ORDER BY id DESC");
    $stmt->execute([$user_id]);
    echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll()]);
    exit;
}

// 3. Yangi e'lon qo'shish
if ($action === 'create_product') {
    $title = trim($data['title'] ?? '');
    $description = trim($data['description'] ?? '');
    $price = (float)($data['price'] ?? 0);
    $type = $data['type'] ?? 'digital_note';

    if (!$title || $price <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Sarlavha va narx to‘g‘ri kiritilishi shart']);
        exit;
    }

    $stmt = $pdo->prepare("INSERT INTO products (user_id, title, description, price, type, status) VALUES (?, ?, ?, ?, ?, 'active')");
    $stmt->execute([$user_id, $title, $description, $price, $type]);

    echo json_encode(['status' => 'success', 'message' => 'E’lon muvaffaqiyatli joylashtirildi!']);
    exit;
}