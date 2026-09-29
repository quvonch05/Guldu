<?php
// api/confirm_order.php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';

$data = json_decode(file_get_contents('php://input'), true);
$seller_id = (int)($data['seller_id'] ?? 0);
$secret_code = trim($data['secret_code'] ?? '');

if (!$seller_id || !$secret_code) {
    echo json_encode(['status' => 'error', 'message' => 'Sotuvchi ID yoki kod berilmadi']);
    exit;
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("SELECT * FROM orders WHERE secret_qr_code = ? AND seller_id = ? AND escrow_status = 'frozen' FOR UPDATE");
    $stmt->execute([$secret_code, $seller_id]);
    $order = $stmt->fetch();

    if (!$order) {
        throw new Exception("Yaroqsiz kod yoki bu bitim allaqachon yakunlangan");
    }

    $commission = $order['amount'] * 0.05;
    $seller_earning = $order['amount'] - $commission;

    // Sotuvchi balansiga o‘tkazish
    $stmt = $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
    $stmt->execute([$seller_earning, $seller_id]);

    // Buyurtmani yakunlangan holatga o‘tkazish
    $stmt = $pdo->prepare("UPDATE orders SET escrow_status = 'completed', commission_fee = ? WHERE id = ?");
    $stmt->execute([$commission, $order['id']]);

    $pdo->commit();
    echo json_encode(['status' => 'success', 'message' => "Bitim tasdiqlandi! Balansingizga {$seller_earning} so‘m o‘tkazildi."]);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}