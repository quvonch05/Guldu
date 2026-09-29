<?php
// unishare/public/api/submit_topup.php
ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

// db.php faylini qidirish va ulash
$dbPath = null;
if (file_exists(__DIR__ . '/../../config/db.php')) {
    $dbPath = __DIR__ . '/../../config/db.php';
} elseif (file_exists(__DIR__ . '/../config/db.php')) {
    $dbPath = __DIR__ . '/../config/db.php';
} elseif (file_exists(__DIR__ . '/config/db.php')) {
    $dbPath = __DIR__ . '/config/db.php';
}

if (!$dbPath) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'db.php fayli topilmadi!']);
    exit;
}
require_once $dbPath;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Faqat POST so‘rov qabul qilinadi!']);
    exit;
}

try {
    $user_id = (int)($_POST['user_id'] ?? 0);
    $amount  = (float)($_POST['amount'] ?? 0);
    $system  = trim($_POST['system'] ?? 'click_p2p');

    if (!$user_id || $amount < 1000) {
        echo json_encode(['status' => 'error', 'message' => 'Summani to‘g‘ri kiriting (kamida 1,000 UZS)!']);
        exit;
    }

    if (!isset($_FILES['receipt_file']) || $_FILES['receipt_file']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['status' => 'error', 'message' => 'Iltimos, to‘lov cheki skrinshotini yuklang!']);
        exit;
    }

    $receiptTmp = $_FILES['receipt_file']['tmp_name'];
    $receiptSize = filesize($receiptTmp);
    
    if ($receiptSize > 8 * 1024 * 1024) {
        echo json_encode(['status' => 'error', 'message' => 'Fayl hajmi 8 MB dan oshmasligi kerak!']);
        exit;
    }

    $receiptData = file_get_contents($receiptTmp);
    $mime = 'image/jpeg';
    if (function_exists('mime_content_type')) {
        $detectedMime = @mime_content_type($receiptTmp);
        if ($detectedMime) $mime = $detectedMime;
    }
    $base64Image = 'data:' . $mime . ';base64,' . base64_encode($receiptData);

    // Foydalanuvchini tekshirish
    $uStmt = $pdo->prepare("SELECT full_name, student_id, phone FROM users WHERE id = ?");
    $uStmt->execute([$user_id]);
    $user = $uStmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo json_encode(['status' => 'error', 'message' => 'Foydalanuvchi profilingiz topilmadi!']);
        exit;
    }

    // So'rovni bazaga yozish
    $stmt = $pdo->prepare("INSERT INTO topup_requests (user_id, amount, `system`, receipt_image, status) VALUES (?, ?, ?, ?, 'pending')");
    $stmt->execute([$user_id, $amount, $system, $base64Image]);
    $requestId = $pdo->lastInsertId();

    $botToken  = '7463167789:AAGMcC9QihyYDfPiGqGnHWKJ4G5rXLiSpTw';
    $adminTgId = '1686406789';

    if ($botToken && $adminTgId) {
        $caption = "🔔 <b>Yangi hisob to‘ldirish so‘rovi!</b>\n\n"
                 . "🆔 <b>So‘rov ID:</b> #{$requestId}\n"
                 . "👤 <b>Talaba:</b> " . htmlspecialchars($user['full_name']) . " (" . $user['student_id'] . ")\n"
                 . "📞 <b>Telefon:</b> " . htmlspecialchars($user['phone']) . "\n"
                 . "💰 <b>Summa:</b> " . number_format($amount, 0, '', ' ') . " UZS\n"
                 . "💳 <b>To‘lov turi:</b> " . strtoupper($system) . "\n\n"
                 . "To‘lov tasdiqlansinmi?";

        $keyboard = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => '✅ Tasdiqlash', 'callback_data' => "approve_{$requestId}"],
                    ['text' => '❌ Rad etish', 'callback_data' => "reject_{$requestId}"]
                ]
            ]
        ]);

        $url = "https://api.telegram.org/bot{$botToken}/sendPhoto";
        $postData = [
            'chat_id' => $adminTgId,
            'photo' => new CURLFile($receiptTmp, $mime, 'receipt.jpg'),
            'caption' => $caption,
            'parse_mode' => 'HTML',
            'reply_markup' => $keyboard
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        $tgRes = curl_exec($ch);
        curl_close($ch);
    }

    echo json_encode([
        'status' => 'success', 
        'message' => 'To‘lov chekingiz adminga yuborildi! Tasdiqlanishi bilan hisobingiz to‘ldiriladi.'
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Xatolik: ' . $e->getMessage()]);
}
