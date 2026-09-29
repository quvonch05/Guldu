<?php
// unishare/api/buy_product.php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';

// Faqat POST so'rovlarni qabul qilish
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Faqat POST so‘rovlar qabul qilinadi!']);
    exit;
}

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

$buyer_id   = (int)($data['buyer_id'] ?? 0);
$product_id = (int)($data['product_id'] ?? 0);

if (!$buyer_id || !$product_id) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Xaridor yoki mahsulot ID raqami kiritilmadi!']);
    exit;
}

try {
    // Tranzaksiyani boshlash
    $pdo->beginTransaction();

    // 1. Mahsulotni tekshirish va uni bloklash (FOR UPDATE - bir vaqtda 2 kishi ololmasligi uchun)
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ? FOR UPDATE");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        throw new Exception("Bunday mahsulot topilmadi!");
    }

    if ($product['status'] !== 'active') {
        throw new Exception("Ushbu mahsulot allaqachon sotilgan yoki faol emas!");
    }

    if ((int)$product['seller_id'] === $buyer_id) {
        throw new Exception("O‘zingiz joylashtirgan e'lonni o‘zingiz sotib ololmaysiz!");
    }

    // 2. Xaridorni tekshirish va balansini qulflash
    $stmt = $pdo->prepare("SELECT id, balance FROM users WHERE id = ? FOR UPDATE");
    $stmt->execute([$buyer_id]);
    $buyer = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$buyer) {
        throw new Exception("Xaridor hisobi topilmadi!");
    }

    $price = (float)$product['price'];
    $buyer_balance = (float)$buyer['balance'];

    if ($buyer_balance < $price) {
        throw new Exception("Hisobingizda mablag‘ yetarli emas! Talab etiladi: " . number_format($price, 0, '', ' ') . " UZS");
    }

    // 3. Sotuvchini tekshirish
    $seller_id = (int)$product['seller_id'];
    $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
    $stmt->execute([$seller_id]);
    $seller = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$seller) {
        throw new Exception("Mahsulot egasi (sotuvchi) bazadan topilmadi!");
    }

    // 4. Komissiya hisob-kitobi (Admin uchun 5%)
    $commission_percent = 5;
    $admin_fee = round(($price * $commission_percent) / 100, 2);
    $seller_earning = $price - $admin_fee;

    // 5. Xaridor balansidan to'liq summani yechish
    $stmt = $pdo->prepare("UPDATE users SET balance = balance - ? WHERE id = ?");
    $stmt->execute([$price, $buyer_id]);

    // 6. Sotuvchi hisobiga komissiyadan qolgan summani o'tkazish
    $stmt = $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
    $stmt->execute([$seller_earning, $seller_id]);

    // 7. Admin (role = 'admin') hisobiga komissiya (5%) ni o'tkazish
    $stmt = $pdo->prepare("UPDATE users SET balance = balance + ? WHERE role = 'admin' LIMIT 1");
    $stmt->execute([$admin_fee]);

    // 8. Mahsulot statusini 'sold' (sotildi) holatiga o'tkazish
    $stmt = $pdo->prepare("UPDATE products SET status = 'sold' WHERE id = ?");
    $stmt->execute([$product_id]);

    // 9. Buyurtmalar tarixiga yozish
    $stmt = $pdo->prepare("INSERT INTO orders (buyer_id, product_id, amount, status) VALUES (?, ?, ?, 'completed')");
    $stmt->execute([$buyer_id, $product_id, $price]);

    // Tranzaksiyani muvaffaqiyatli yakunlash
    $pdo->commit();

    // Xaridorning yangilangan yangi balansini qaytarish
    $stmt = $pdo->prepare("SELECT balance FROM users WHERE id = ?");
    $stmt->execute([$buyer_id]);
    $newBalance = (float)$stmt->fetchColumn();

    echo json_encode([
        'status' => 'success',
        'message' => 'Xarid muvaffaqiyatli amalga oshirildi!',
        'new_balance' => $newBalance,
        'commission' => $admin_fee,
        'seller_received' => $seller_earning
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
    exit;
}