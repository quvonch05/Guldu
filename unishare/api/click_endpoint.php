<?php
// api/click_endpoint.php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';

$secret_key = getenv('CLICK_SECRET_KEY') ?: 'CLICK_TEST_SECRET';
$service_id = (int)(getenv('CLICK_SERVICE_ID') ?: 12345);

$click_trans_id = $_POST['click_trans_id'] ?? null;
$service_id_in = (int)($_POST['service_id'] ?? 0);
$merchant_trans_id = (int)($_POST['merchant_trans_id'] ?? 0); // Talaba ID
$amount = (float)($_POST['amount'] ?? 0);
$action = (int)($_POST['action'] ?? -1);
$error = (int)($_POST['error'] ?? 0);
$sign_time = $_POST['sign_time'] ?? '';
$sign_string = $_POST['sign_string'] ?? '';

// MD5 tekshirish
$check_string = $click_trans_id . $service_id_in . $secret_key . $merchant_trans_id . $amount . $action . $sign_time;
$my_sign = md5($check_string);

if ($my_sign !== $sign_string) {
    echo json_encode(['error' => -1, 'error_note' => 'SIGN CHECK FAILED']);
    exit;
}

// 1. Prepare (Action = 0)
if ($action === 0) {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
    $stmt->execute([$merchant_trans_id]);
    $user = $stmt->fetch();

    if (!$user) {
        echo json_encode(['error' => -5, 'error_note' => 'User does not exist']);
        exit;
    }

    $stmt = $pdo->prepare("INSERT INTO billing_transactions (user_id, click_trans_id, amount, state, sign_time) VALUES (?, ?, ?, 1, ?)");
    $stmt->execute([$merchant_trans_id, $click_trans_id, $amount, $sign_time]);

    echo json_encode([
        'click_trans_id' => $click_trans_id,
        'merchant_trans_id' => $merchant_trans_id,
        'merchant_prepare_id' => (int)$pdo->lastInsertId(),
        'error' => 0,
        'error_note' => 'Success'
    ]);
    exit;
}

// 2. Complete (Action = 1)
if ($action === 1) {
    $merchant_prepare_id = (int)($_POST['merchant_prepare_id'] ?? 0);

    if ($error < 0) {
        $stmt = $pdo->prepare("UPDATE billing_transactions SET state = -1 WHERE id = ?");
        $stmt->execute([$merchant_prepare_id]);
        echo json_encode(['error' => -9, 'error_note' => 'Transaction cancelled']);
        exit;
    }

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
        $stmt->execute([$amount, $merchant_trans_id]);

        $stmt = $pdo->prepare("UPDATE billing_transactions SET state = 2 WHERE id = ?");
        $stmt->execute([$merchant_prepare_id]);

        $pdo->commit();

        echo json_encode([
            'click_trans_id' => $click_trans_id,
            'merchant_trans_id' => $merchant_trans_id,
            'merchant_confirm_id' => $merchant_prepare_id,
            'error' => 0,
            'error_note' => 'Success'
        ]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['error' => -7, 'error_note' => 'Balance update failed']);
    }
    exit;
}