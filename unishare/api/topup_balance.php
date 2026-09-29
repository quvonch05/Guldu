<?php
// unishare/api/topup_balance.php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';

$data = json_decode(file_get_contents('php://input'), true);
$user_id = (int)($data['user_id'] ?? 0);
$amount  = (float)($data['amount'] ?? 0);
$system  = $data['system'] ?? 'click';

if (!$user_id || $amount <= 0) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'To‘ldirish summasini to‘g‘ri kiriting!']);
    exit;
}

try {
    $pdo->beginTransaction();

    // Balansni oshirish
    $stmt = $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
    $stmt->execute([$amount, $user_id]);

    // Tranzaksiyani yozish
    $txn_id = strtoupper($system) . '_' . time() . '_' . rand(1000, 9999);
    $stmt = $pdo->prepare("INSERT INTO billing_transactions (user_id, `system`, transaction_id, amount, status) VALUES (?, ?, ?, ?, 'completed')");
    $stmt->execute([$user_id, $system, $txn_id, $amount]);

    $pdo->commit();

    $newBal = $pdo->query("SELECT balance FROM users WHERE id = $user_id")->fetchColumn();
    echo json_encode(['status' => 'success', 'message' => "Hisobingizga {$amount} UZS qo‘shildi!", 'new_balance' => $newBal]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Xatolik: ' . $e->getMessage()]);
}