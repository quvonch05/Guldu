<?php
// unishare/api/get_my_orders.php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';

$user_id = (int)($_GET['user_id'] ?? 0);

if (!$user_id) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Foydalanuvchi ID ko‘rsatilmadi!']);
    exit;
}

try {
    // Xaridor sotib olgan barcha mahsulotlar ro'yxati va sotuvchi ma'lumotlari
    $stmt = $pdo->prepare("
        SELECT 
            o.id as order_id,
            o.amount as paid_amount,
            o.created_at as purchase_date,
            p.id as product_id,
            p.title,
            p.category,
            p.image_url,
            p.description,
            u.full_name as seller_name,
            u.phone as seller_phone
        FROM orders o
        JOIN products p ON o.product_id = p.id
        JOIN users u ON p.seller_id = u.id
        WHERE o.buyer_id = ?
        ORDER BY o.id DESC
    ");
    $stmt->execute([$user_id]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['status' => 'success', 'data' => $orders]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}