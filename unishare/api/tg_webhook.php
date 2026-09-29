<?php
// unishare/public/api/tg_webhook.php
ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

// Bazani ulash
$dbPath1 = __DIR__ . '/../../config/db.php';
$dbPath2 = __DIR__ . '/../config/db.php';

if (file_exists($dbPath1)) {
    require_once $dbPath1;
} elseif (file_exists($dbPath2)) {
    require_once $dbPath2;
}

$rawInput = file_get_contents('php://input');
$update = json_decode($rawInput, true);

// Har qanday so'rovda Telegramga darhol 200 OK qaytarish
if (!$update || !isset($update['callback_query'])) {
    http_response_code(200);
    echo json_encode(['ok' => true]);
    exit;
}

$callback   = $update['callback_query'];
$callbackId = $callback['id'];
$data       = $callback['data'] ?? '';
$messageId  = $callback['message']['message_id'] ?? 0;
$chatId     = $callback['message']['chat']['id'] ?? 0;

$botToken = '7463167789:AAGMcC9QihyYDfPiGqGnHWKJ4G5rXLiSpTw';

function tgPost($method, $payload, $token) {
    $url = "https://api.telegram.org/bot" . $token . "/" . $method;
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 8);
    $res = curl_exec($ch);
    curl_close($ch);
    return $res;
}

// 1. TASDIQLASH (APPROVE)
if (strpos($data, 'approve_') === 0) {
    $reqId = (int)str_replace('approve_', '', $data);

    $stmt = $pdo->prepare("SELECT * FROM topup_requests WHERE id = ?");
    $stmt->execute([$reqId]);
    $req = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$req) {
        tgPost('answerCallbackQuery', [
            'callback_query_id' => $callbackId,
            'text' => 'So‘rov bazadan topilmadi!',
            'show_alert' => true
        ], $botToken);
        exit;
    }

    if ($req['status'] !== 'pending') {
        tgPost('answerCallbackQuery', [
            'callback_query_id' => $callbackId,
            'text' => 'Bu so‘rov avvalroq ko‘rib chiqilgan (' . $req['status'] . ')!',
            'show_alert' => true
        ], $botToken);
        exit;
    }

    try {
        $pdo->beginTransaction();

        // Talaba balansini oshirish
        $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?")
            ->execute([$req['amount'], $req['user_id']]);

        // So'rov holatini approved qilish
        $pdo->prepare("UPDATE topup_requests SET status = 'approved' WHERE id = ?")
            ->execute([$reqId]);

        // Billing tranzaksiyaga yozish
        $tx = 'TG_' . $reqId . '_' . time();
        $pdo->prepare("INSERT INTO billing_transactions (user_id, `system`, transaction_id, amount, status) VALUES (?, 'click', ?, ?, 'completed')")
            ->execute([$req['user_id'], $tx, $req['amount']]);

        $pdo->commit();

        tgPost('answerCallbackQuery', [
            'callback_query_id' => $callbackId,
            'text' => '✅ Balansga +' . number_format($req['amount'], 0, '', ' ') . ' UZS muvaffaqiyatli qo‘shildi!',
            'show_alert' => true
        ], $botToken);

        $caption = "✅ <b>#" . $reqId . " — TASDIQLANDI!</b>\n\n"
                 . "💰 <b>Summa:</b> " . number_format($req['amount'], 0, '', ' ') . " UZS\n"
                 . "👤 <b>Talaba ID:</b> #" . $req['user_id'] . "\n"
                 . "Holat: Mablag‘ hisobga biriktirildi.";

        tgPost('editMessageCaption', [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'caption' => $caption,
            'parse_mode' => 'HTML',
            'reply_markup' => json_encode(['inline_keyboard' => []])
        ], $botToken);

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        tgPost('answerCallbackQuery', [
            'callback_query_id' => $callbackId,
            'text' => 'Baza xatosi: ' . $e->getMessage(),
            'show_alert' => true
        ], $botToken);
    }
    exit;
}

// 2. RAD ETISH (REJECT)
if (strpos($data, 'reject_') === 0) {
    $reqId = (int)str_replace('reject_', '', $data);

    $pdo->prepare("UPDATE topup_requests SET status = 'rejected' WHERE id = ?")->execute([$reqId]);

    tgPost('answerCallbackQuery', [
        'callback_query_id' => $callbackId,
        'text' => '❌ To‘lov so‘rovi rad etildi!',
        'show_alert' => true
    ], $botToken);

    $caption = "❌ <b>#" . $reqId . " — RAD ETILDI!</b>\nHolat: To‘lov bekor qilindi.";

    tgPost('editMessageCaption', [
        'chat_id' => $chatId,
        'message_id' => $messageId,
        'caption' => $caption,
        'parse_mode' => 'HTML',
        'reply_markup' => json_encode(['inline_keyboard' => []])
    ], $botToken);
    exit;
}

http_response_code(200);
echo json_encode(['ok' => true]);
