<?php
// unishare/api/tg_webhook.php
require_once __DIR__ . '/../config/db.php';

$content = file_get_contents('php://input');
$update = json_decode($content, true);

if (!$update || !isset($update['callback_query'])) {
    exit;
}

$callback = $update['callback_query'];
$callbackId = $callback['id'];
$data = $callback['data'] ?? '';
$messageId = $callback['message']['message_id'];
$chatId = $callback['message']['chat']['id'];

$botToken = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'tg_bot_token'")->fetchColumn() 
            ?: '7463167789:AAGMcC9QihyYDfPiGqGnHWKJ4G5rXLiSpTw';

function answerCallback($botToken, $callbackId, $text) {
    @file_get_contents("https://api.telegram.org/bot{$botToken}/answerCallbackQuery?callback_query_id={$callbackId}&text=" . urlencode($text) . "&show_alert=true");
}

function updateMessage($botToken, $chatId, $messageId, $newCaption) {
    @file_get_contents("https://api.telegram.org/bot{$botToken}/editMessageCaption?chat_id={$chatId}&message_id={$messageId}&caption=" . urlencode($newCaption) . "&parse_mode=HTML");
}

// 1. TASDIQLASH (APPROVE)
if (strpos($data, 'approve_') === 0) {
    $requestId = (int)str_replace('approve_', '', $data);

    $stmt = $pdo->prepare("SELECT * FROM topup_requests WHERE id = ?");
    $stmt->execute([$requestId]);
    $req = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$req) {
        answerCallback($botToken, $callbackId, "So‘rov topilmadi!");
        exit;
    }

    if ($req['status'] !== 'pending') {
        answerCallback($botToken, $callbackId, "Bu so‘rov avvalroq ko‘rib chiqilgan ({$req['status']})!");
        exit;
    }

    try {
        $pdo->beginTransaction();

        // Foydalanuvchi balansiga mablag' qo'shish
        $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?")
            ->execute([$req['amount'], $req['user_id']]);

        // So'rovni tasdiqlangan deb belgilash
        $pdo->prepare("UPDATE topup_requests SET status = 'approved' WHERE id = ?")
            ->execute([$requestId]);

        // Tranzaksiyalar tarixiga yozish
        $txId = 'TG_TOPUP_' . $requestId . '_' . time();
        $pdo->prepare("INSERT INTO billing_transactions (user_id, `system`, transaction_id, amount, status) VALUES (?, ?, ?, ?, 'completed')")
            ->execute([$req['user_id'], $req['system'], $txId, $req['amount']]);

        $pdo->commit();

        answerCallback($botToken, $callbackId, "✅ To‘lov tasdiqlandi va talaba balansiga qo‘shildi!");
        updateMessage($botToken, $chatId, $messageId, "✅ <b>#{$requestId} — TASDIQLANDI!</b>\nTalaba hisobiga " . number_format($req['amount'], 0, '', ' ') . " UZS muvaffaqiyatli o‘tkazildi.");
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        answerCallback($botToken, $callbackId, "Xato: " . $e->getMessage());
    }
    exit;
}

// 2. RAD ETISH (REJECT)
if (strpos($data, 'reject_') === 0) {
    $requestId = (int)str_replace('reject_', '', $data);

    $stmt = $pdo->prepare("SELECT * FROM topup_requests WHERE id = ?");
    $stmt->execute([$requestId]);
    $req = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($req && $req['status'] === 'pending') {
        $pdo->prepare("UPDATE topup_requests SET status = 'rejected' WHERE id = ?")->execute([$requestId]);
        answerCallback($botToken, $callbackId, "❌ So‘rov rad etildi!");
        updateMessage($botToken, $chatId, $messageId, "❌ <b>#{$requestId} — RAD ETILDI!</b>\nTo‘lov qabul qilinmadi.");
    }
    exit;
}
