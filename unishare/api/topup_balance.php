<?php
// unishare/api/topup_balance.php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';

$data = json_decode(file_get_contents('php://input'), true);
$user_id = (int)($data['user_id'] ?? 0);
$amount  = (float)($data['amount'] ?? 0);
$system  =$data['system'] ?? 'click';

if (!$user_id \vert{}\vert{}$amount <= 1000) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Eng kam to‘lov summasi 1,000 UZS']);
    exit;
}

try {
    // Admin panelda kiritilgan kalitlarni olish
    $settings = [];
    $rows =$pdo->query("SELECT setting_key, setting_value FROM system_settings")->fetchAll();
    foreach ($rows as $r) {$settings[$r['setting_key']] =$r['setting_value'];
    }

    $txn_id = strtoupper($system) . '_' . time() . '_' . rand(100, 999);
    
    // Tranzaksiyani 'pending' (kutilmoqda) holatda yaratish
    $stmt =$pdo->prepare("INSERT INTO billing_transactions (user_id, `system`, transaction_id, amount, status) VALUES (?, ?, ?, ?, 'pending')");
    $stmt->execute([$user_id,$system, $txn_id,$amount]);

    $redirect_url = '';

    if ($system === 'click') {
        $service_id  =$settings['click_service_id'] ?? '';
        $merchant_id =$settings['click_merchant_id'] ?? '';

        // Agar admin kalit kiritmagan bo'lsa, test rejimida balansni to'ldirib qo'yaveradi
        if (!$service_id || !$merchant_id) {$pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?")->execute([$amount,$user_id]);
            $pdo->prepare("UPDATE billing_transactions SET status = 'completed' WHERE transaction_id = ?")->execute([$txn_id]);
            $newBal =$pdo->query("SELECT balance FROM users WHERE id = $user_id")->fetchColumn();
            echo json_encode([
                'status' => 'success',
                'mode' => 'test',
                'message' => "Test rejimi: Hisobingizga {$amount} UZS qo‘shildi!",
                'new_balance' => $newBal
            ]);
            exit;
        }

        // Haqiqiy Click Checkout havolasi
        $return_url = urlencode("https://guldu.onrender.com");
        $redirect_url = "https://my.click.uz/services/pay?service_id={$service_id}&merchant_id={$merchant_id}&amount={$amount}&transaction_param={$txn_id}&return_url={$return_url}";
    } 
    elseif ($system === 'payme') {
        $payme_id =$settings['payme_merchant_id'] ?? '';

        if (!$payme_id) {$pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?")->execute([$amount,$user_id]);
            $pdo->prepare("UPDATE billing_transactions SET status = 'completed' WHERE transaction_id = ?")->execute([$txn_id]);
            $newBal =$pdo->query("SELECT balance FROM users WHERE id = $user_id")->fetchColumn();
            echo json_encode([
                'status' => 'success',
                'mode' => 'test',
                'message' => "Test rejimi: Hisobingizga {$amount} UZS qo‘shildi!",
                'new_balance' => $newBal
            ]);
            exit;
        }

        // Haqiqiy Payme havolasi (amount tiyinda bo'ladi: amount * 100)
        $tiyin =$amount * 100;
        $params = base64_encode("m={$payme_id};ac.order_id={$txn_id};a={$tiyin}");
        $redirect_url = "https://checkout.paycom.uz/{$params}";
    }

    echo json_encode([
        'status' => 'success',
        'mode' => 'live',
        'redirect_url' => $redirect_url
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}