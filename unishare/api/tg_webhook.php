<?php
// unishare/api/tg_webhook.php
ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/db.php';

$raw = file_get_contents('php://input');
$update = json_decode($raw, true);

// Har qanday holatda Telegramga 200 OK qaytarish lozim
if (!$update \vert{}\vert{} !isset($update['callback_query'])) {
    http_response_code(200);
    echo json_encode(['ok' => true]);
    exit;
}

$callback   =$update['callback_query'];
$callbackId =$callback['id'];
$data       =$callback['data'] ?? '';
$messageId  =$callback['message']['message_id'] ?? 0;
$chatId     =$callback['message']['chat']['id'] ?? 0;

$botToken = '7463167789:AAGMcC9QihyYDfPiGqGnHWKJ4G5rXLiSpTw';

function sendTgPost($url, $data) {$ch = curl_init();
    curl_setopt($ch, CURLOPT_URL,$url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS,$data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $res = curl_exec($ch);
    curl_close($ch);
    return $res;
}

// 1. TASDIQLASH (APPROVE)
if (strpos($data, 'approve_') === 0) {
    $requestId = (int)str_replace('approve_', '',$data);

    $stmt =$pdo->prepare("SELECT * FROM topup_requests WHERE id = ?");
    $stmt->execute([$requestId]);
    $req =$stmt->fetch(PDO::FETCH_ASSOC);

    if (!$req) {
        sendTgPost("https://api.telegram.org/bot{$botToken}/answerCallbackQuery", [
            'callback_query_id' => $callbackId,
            'text' => 'So‘rov topilmadi!',
            'show_alert' => true
        ]);
        exit;
    }

    if ($req['status'] !== 'pending') {
        sendTgPost("https://api.telegram.org/bot{$botToken}/answerCallbackQuery", [
            'callback_query_id' => $callbackId,
            'text' => "Bu so‘rov avvalroq ko‘rib chiqilgan ({$req['status']})!",
            'show_alert' => true
        ]);
        exit;
    }

    try {
        $pdo->beginTransaction();

        // 1. Foydalanuvchi balansini oshirish
        $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?")
            ->execute([$req['amount'],$req['user_id']]);

        // 2. So'rov maqomini 'approved' qilish
        $pdo->prepare("UPDATE topup_requests SET status = 'approved' WHERE id = ?")
            ->execute([$requestId]);

        // 3. Tranzaksiyalar tarixiga yozish
        $txId = 'TG_TOPUP_' .$requestId . '_' . time();
        $pdo->prepare("INSERT INTO billing_transactions (user_id, `system`, transaction_id, amount, status) VALUES (?, ?, ?, ?, 'completed')")
            ->execute([$req['user_id'],$req['system'], $txId,$req['amount']]);

        $pdo->commit();

        sendTgPost("https://api.telegram.org/bot{$botToken}/answerCallbackQuery", [
            'callback_query_id' => $callbackId,
            'text' => "✅ To‘lov tasdiqlandi! Hisobga +" . number_format($req['amount'], 0, '', ' ') . " UZS qo‘shildi.",
            'show_alert' => true
        ]);

        $newCaption = "✅ <b>#{$requestId} — TASDIQLANDI!</b>\n\n"
                    . "💰 <b>Summa:</b> " . number_format($req['amount'], 0, '', ' ') . " UZS\n"
                    . "👤 <b>Foydalanuvchi ID:</b> #{$req['user_id']}\n"
                    . "Holat: Muvaffaqiyatli yakunlandi.";

        sendTgPost("https://api.telegram.org/bot{$botToken}/editMessageCaption", [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'caption' => $newCaption,
            'parse_mode' => 'HTML',
            'reply_markup' => json_encode(['inline_keyboard' => []])
        ]);

    } catch (Exception $e) {
        if ($pdo->inTransaction())$pdo->rollBack();
        sendTgPost("https://api.telegram.org/bot{$botToken}/answerCallbackQuery", [
            'callback_query_id' => $callbackId,
            'text' => "Baza xatosi: " . $e->getMessage(),
            'show_alert' => true
        ]);
    }
    exit;
}

// 2. RAD ETISH (REJECT)
if (strpos($data, 'reject_') === 0) {
    $requestId = (int)str_replace('reject_', '',$data);

    $stmt =$pdo->prepare("SELECT * FROM topup_requests WHERE id = ?");
    $stmt->execute([$requestId]);
    $req =$stmt->fetch(PDO::FETCH_ASSOC);

    if ($req &&$req['status'] === 'pending') {
        $pdo->prepare("UPDATE topup_requests SET status = 'rejected' WHERE id = ?")->execute([$requestId]);

        sendTgPost("https://api.telegram.org/bot{$botToken}/answerCallbackQuery", [
            'callback_query_id' => $callbackId,
            'text' => '❌ To‘lov so‘rovi rad etildi!',
            'show_alert' => true
        ]);

        $newCaption = "❌ <b>#{$requestId} — RAD ETILDI!</b>\n\n"
                    . "💰 <b>Summa:</b> " . number_format($req['amount'], 0, '', ' ') . " UZS\n"
                    . "Holat: To‘lov bekor qilingan.";

        sendTgPost("https://api.telegram.org/bot{$botToken}/editMessageCaption", [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'caption' => $newCaption,
            'parse_mode' => 'HTML',
            'reply_markup' => json_encode(['inline_keyboard' => []])
        ]);
    }
    exit;
}

http_response_code(200);
echo json_encode(['ok' => true]);
