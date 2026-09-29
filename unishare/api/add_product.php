<?php
// unishare/api/add_product.php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';

$data = json_decode(file_get_contents('php://input'), true);

$seller_id   = (int)($data['seller_id'] ?? 0);
$title       = trim($data['title'] ?? '');
$description = trim($data['description'] ?? '');
$price       = (float)($data['price'] ?? 0);
$category    = trim($data['category'] ?? 'Kitoblar');
$image_url   = trim($data['image_url'] ?? 'https://images.unsplash.com/photo-1544716278-ca5e3f4abd8c?w=500&q=80');

if (!$seller_id || !$title || $price <= 0) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Barcha majburiy maydonlarni to‘g‘ri to‘ldiring!']);
    exit;
}

try {
    $stmt = $pdo->prepare("INSERT INTO products (seller_id, title, description, price, category, status, image_url) VALUES (?, ?, ?, ?, ?, 'active', ?)");
    $stmt->execute([$seller_id, $title, $description, $price, $category, $image_url]);
    
    echo json_encode(['status' => 'success', 'message' => 'E’lon muvaffaqiyatli joylashtirildi!']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Baza xatosi: ' . $e->getMessage()]);
}