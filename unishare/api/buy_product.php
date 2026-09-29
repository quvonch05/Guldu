<?php
// api/buy_product.php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';

$data = json_decode(file_get_contents('php://input'), true);
$buyer_id = (int)($data['buyer_id'] ?? 0);
$product_id = (int)($data['product_id'] ?? 0);

if (!$buyer_id || !$product_id) {
    echo json_encode(['status' => 'error', 'message' => 'Parametrlar yetarli emas']);
    exit;
}

try {
    $pdo->beginTransaction();

    // 1. Mahsulotni tekshirish
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ? AND status = 'active' FOR UPDATE");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();

    if (!$product) {
        throw new Exception("Mahsulot topilmadi yoki sotuvdan olingan");
    }

    if ($product['user_id'] == $buyer_id) {
        throw new Exception("O‘z mahsulotingizni xarid qila olmaysiz");
    }

    // 2. Xaridor balansini tekshirish
    $stmt = $pdo->prepare("SELECT balance FROM users WHERE id = ? FOR UPDATE");
    $stmt->execute([$buyer_id]);
    $buyer = $stmt->fetch();

    if ($buyer['balance'] < $product['price']) {
        throw new Exception("Balansingizda yetarli mablag‘ yo‘q. Iltimos, hisobni to‘ldiring.");
    }

    // 3. Pulni xaridordan yechish
    $stmt = $pdo->prepare("UPDATE users SET balance = balance - ? WHERE id = ?");
    $stmt->execute([$product['price'], $buyer_id]);

    // 4. Mahsulot turiga qarab o‘tkazish
    if ($product['type'] === 'digital_note') {
        // Raqamli konspekt bo‘lsa: darhol sotuvchiga tushadi (5% platforma xizmat haqi)
        $commission = $product['price'] * 0.05;
        $seller_amount = $product['price'] - $commission;

        $stmt = $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
        $stmt->execute([$seller_amount, $product['user_id']]);

        $stmt = $pdo->prepare("INSERT INTO orders (buyer_id, seller_id, product_id, amount, commission_fee, escrow_status) VALUES (?, ?, ?, ?, ?, 'completed')");
        $stmt->execute([$buyer_id, $product['user_id'], $product_id, $product['price'], $commission]);

        $pdo->commit();
        echo json_encode([
            'status' => 'success',
            'message' => 'Material muvaffaqiyatli xarid qilindi!',
            'download_url' => "/api/download.php?user_id={$buyer_id}&product_id={$product_id}"
        ]);
    } else {
        // Kitob yoki Print xizmati: Escrowda pul muzlatiladi
        $secret_code = strtoupper(bin2hex(random_bytes(4))); // 8 belgili kod
        $stmt = $pdo->prepare("INSERT INTO orders (buyer_id, seller_id, product_id, amount, escrow_status, secret_qr_code) VALUES (?, ?, ?, ?, 'frozen', ?)");
        $stmt->execute([$buyer_id, $product['user_id'], $product_id, $product['price'], $secret_code]);

        $pdo->commit();
        echo json_encode([
            'status' => 'success',
            'message' => "Mablag‘ muzlatildi. Buyumni/xizmatni olgach ushbu maxfiy kodni topshiring: {$secret_code}",
            'escrow_code' => $secret_code
        ]);
    }

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}