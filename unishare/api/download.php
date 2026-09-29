<?php
// api/download.php
require_once __DIR__ . '/../config/db.php';

$user_id = (int)($_GET['user_id'] ?? 0);
$product_id = (int)($_GET['product_id'] ?? 0);

if (!$user_id || !$product_id) {
    http_response_code(403);
    die("Ruxsat berilmadi");
}

$stmt = $pdo->prepare("SELECT o.id, p.file_path, p.title FROM orders o 
                       JOIN products p ON o.product_id = p.id 
                       WHERE o.buyer_id = ? AND o.product_id = ? AND o.escrow_status = 'completed'");
$stmt->execute([$user_id, $product_id]);
$order = $stmt->fetch();

if (!$order) {
    http_response_code(403);
    die("Ushbu fayl sotib olinmagan yoki to‘lov yakunlanmagan.");
}

$filepath = __DIR__ . '/../uploads/' . basename($order['file_path']);

if (!file_exists($filepath) || empty($order['file_path'])) {
    die("Fayl serverda mavjud emas yoki yuklanmagan.");
}

header('Content-Description: File Transfer');
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $order['title']) . '.pdf"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($filepath));
readfile($filepath);
exit;