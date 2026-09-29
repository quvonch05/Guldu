<?php
// api/get_products.php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';

try {
    $stmt = $pdo->query("SELECT p.*, u.full_name as author_name FROM products p JOIN users u ON p.user_id = u.id WHERE p.status = 'active' ORDER BY p.id DESC");
    $products = $stmt->fetchAll();
    echo json_encode(['status' => 'success', 'data' => $products]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}