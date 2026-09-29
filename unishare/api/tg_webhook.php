<?php
// unishare/api/tg_webhook.php
ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/db.php';

$content = file_get_contents('php://input');
$update = json_decode($content, true);

if (!$update || !isset($update['callback_query'])) {
    http_response_code(200);
    echo "OK";
    exit;
}

$callback   = $update['callback_query'];
$callbackId = $callback['id'];
$data       = $callback['data'] ?? '';
$messageId  = $callback['message']['message_id'] ?? 0;
$chatId     = $callback['message']['chat']['id'] ?? 0;

$botToken = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'tg_bot_token'")->fetchColumn() 
            ?: '7463167789:AAGMcC9QihyYDfPiGqGnHWKJ4G5rXLiSpTw';

// Telegram API ga xavfsiz so'rov yuborish funksiyasi (cURL)
function tgRequest($method, $parameters, $botToken) {
    $url = "https://api.telegram.org/bot{$botToken}/" . $method;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($parameters));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $result = curl_exec($ch);
    curl_close($ch);
    return $result;
}

// 1. TASDIQLASH (APPROVE)
if (strpos($data, 'approve_') === 0) {
    $requestId = (int)str_replace('approve_', '', $data);

    $stmt = $pdo->prepare("SELECT * FROM topup_requests WHERE id = ?");
    $stmt->execute([$requestId]);
    $req = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$req) {
        tgRequest('answerCallbackQuery', [
            'callback_query_id' => $callbackId,
            'text' => 'So‘rov topilmadi!',
            'show_alert' => true
        ], $botToken);
        exit;
    }

    if ($req['status'] !== 'pending') {
        tgRequest('answerCallbackQuery', [
            'callback_query_id' => $callbackId,
            'text' => "Bu so‘rov allaqachon ko‘rib chiqilgan ({$req['status']})!",
            'show_alert' => true
        ], $botToken);
        exit;
    }

    try {
        $pdo->beginTransaction();

        // Foydalanuvchi balansiga mablag' qo'shish
        $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?")
            ->execute([$req['amount'], $req['user_id']]);

        // So'rov holatini approved qilish
        $pdo->prepare("UPDATE topup_requests SET status = 'approved' WHERE id = ?")
            ->execute([$requestId]);

        // Billing jadvaliga xavfsiz kiritish (`system` tirnoq ichida)
        $txId = 'TG_TOPUP_' . $requestId . '_' . time();
        $pdo->prepare("INSERT INTO billing_transactions (user_id, `system`, transaction_id, amount, status) VALUES (?, ?, ?, ?, 'completed')")
            ->execute([$req['user_id'], $req['system'], $txId, $req['amount']]);

        $pdo->commit();

        tgRequest('answerCallbackQuery', [
            'callback_query_id' => $callbackId,
            'text' => "✅ To‘lov tasdiqlandi! Talaba hisobiga +" . number_format($req['amount'], 0, '', ' ') . " UZS qo‘shildi.",
            'show_alert' => true
        ], $botToken);

        $newCaption = "✅ <b>#{$requestId} — TASDIQLANDI!</b>\n\n"
                    . "💰 <b>O‘tkazilgan summa:</b> " . number_format($req['amount'], 0, '', ' ') . " UZS\n"
                    . "👤 <b>Foydalanuvchi ID:</b> #{$req['user_id']}\n"
                    . "⏱ <b>Vaqti:</b> " . date('Y-m-d H:i:s');

        tgRequest('editMessageCaption', [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'caption' => $newCaption,
            'parse_mode' => 'HTML',
            'reply_markup' => json_encode(['inline_keyboard' => []])
        ], $botToken);

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        tgRequest('answerCallbackQuery', [
            'callback_query_id' => $callbackId,
            'text' => 'Baza xatosi: ' . $e->getMessage(),
            'show_alert' => true
        ], $botToken);
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
        
        tgRequest('answerCallbackQuery', [
            'callback_query_id' => $callbackId,
            'text' => '❌ To‘lov so‘rovi rad etildi!',
            'show_alert' => true
        ], $botToken);

        $newCaption = "❌ <b>#{$requestId} — RAD ETILDI!</b>\n\n"
                    . "💰 <b>Summa:</b> " . number_format($req['amount'], 0, '', ' ') . " UZS\n"
                    . "Holat: Bekor qilingan.";

        tgRequest('editMessageCaption', [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'caption' => $newCaption,
            'parse_mode' => 'HTML',
            'reply_markup' => json_encode(['inline_keyboard' => []])
        ], $botToken);
    }
    exit;
}

http_response_code(200);
echo "OK";
