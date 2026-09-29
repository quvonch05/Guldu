<?php
// unishare/api/buy_product.php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';

$data = json_decode(file_get_contents('php://input'), true);
$buyer_id   = (int)($data['buyer_id'] ?? 0);
$product_id = (int)($data['product_id'] ?? 0);

if (!$buyer_id || !$product_id) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Noto‘g‘ri so‘rov!']);
    exit;
}

try {
    $pdo->beginTransaction();

    // 1. Mahsulotni tekshirish
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ? FOR UPDATE");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();

    if (!$product || $product['status'] !== 'active') {
        throw new Exception("Mahsulot topilmadi yoki allaqachon sotilgan!");
    }

    if ($product['seller_id'] == $buyer_id) {
        throw new Exception("O‘zingiz joylagan mahsulotni sotib ololmaysiz!");
    }

    // 2. Xaridor balansini tekshirish
    $stmt = $pdo->prepare("SELECT balance FROM users WHERE id = ? FOR UPDATE");
    $stmt->execute([$buyer_id]);
    $buyer = $stmt->fetch();

    if (!$buyer || (float)$buyer['balance'] < (float)$product['price']) {
        throw new Exception("Hisobingizda mablag‘ yetarli emas! Iltimos, hisobingizni to‘ldiring.");
    }

    $price = (float)$product['price'];

    // 3. Xaridor balansidan pul yechish
    $pdo->prepare("UPDATE users SET balance = balance - ? WHERE id = ?")->execute([$price, $buyer_id]);

    // 4. Sotuvchi balansiga pul o'tkazish
    $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?")->execute([$price, $product['seller_id']]);

    // 5. Mahsulot statusini 'sold' qilish
    $pdo->prepare("UPDATE products SET status = 'sold' WHERE id = ?")->execute([$product_id]);

    // 6. Buyurtmalar tarixiga yozish
    $pdo->prepare("INSERT INTO orders (buyer_id, product_id, amount, status) VALUES (?, ?, ?, 'completed')")
        ->execute([$buyer_id, $product_id, $price]);

    $pdo->commit();

    // Yangilangan balansni qaytarish
    $newBal = $pdo->query("SELECT balance FROM users WHERE id = $buyer_id")->fetchColumn();
    echo json_encode(['status' => 'success', 'message' => 'Xarid muvaffaqiyatli amalga oshirildi!', 'new_balance' => $newBal]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}