<?php
// unishare/api/add_product.php
ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Faqat POST so‘rov qabul qilinadi!']);
    exit;
}

try {
    $seller_id   = (int)($_POST['seller_id'] ?? 0);
    $title       = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price       = (float)($_POST['price'] ?? 0);
    $category    = trim($_POST['category'] ?? 'Kitoblar');
    $image_url   = trim($_POST['image_url'] ?? '');
    $file_url    = trim($_POST['file_url'] ?? '');

    if (!$seller_id || !$title || $price <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Sarlavha va narxni to‘g‘ri kiriting!']);
        exit;
    }

    // Papka yo'lini aniq aniqlash va ruxsat berish
    $uploadDir = realpath(__DIR__ . '/../public');
    if ($uploadDir) {
        $uploadDir = $uploadDir . '/uploads/';
    } else {
        $uploadDir = __DIR__ . '/../public/uploads/';
    }

    // Papkani majburiy 0777 ruxsati bilan yaratish
    if (!file_exists($uploadDir)) {
        @mkdir($uploadDir, 0777, true);
    }
    @chmod($uploadDir, 0777);

    // 1. Muqova rasmi
    if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] === UPLOAD_ERR_OK) {
        $imgFile = $_FILES['image_file'];
        $imgExt = strtolower(pathinfo($imgFile['name'], PATHINFO_EXTENSION));
        $allowedImg = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

        if (in_array($imgExt, $allowedImg)) {
            $newImgName = 'img_' . time() . '_' . mt_rand(1000, 9999) . '.' . $imgExt;
            $targetImg = $uploadDir . $newImgName;
            if (@move_uploaded_file($imgFile['tmp_name'], $targetImg) || @copy($imgFile['tmp_name'], $targetImg)) {
                $image_url = '/uploads/' . $newImgName;
            }
        }
    }

    if (!$image_url) {
        $image_url = 'https://images.unsplash.com/photo-1544716278-ca5e3f4abd8c?w=500&q=80';
    }

    // 2. Kitob yoki konspekt fayli
    if (isset($_FILES['product_file']) && $_FILES['product_file']['error'] === UPLOAD_ERR_OK) {
        $docFile = $_FILES['product_file'];
        $docExt = strtolower(pathinfo($docFile['name'], PATHINFO_EXTENSION));
        $allowedDocs = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'txt', 'zip', 'rar', 'jpg', 'png'];

        if (!in_array($docExt, $allowedDocs)) {
            echo json_encode(['status' => 'error', 'message' => 'Ruxsat etilmagan fayl turi! PDF, Word yoki Zip formatda yuklang.']);
            exit;
        }

        $newDocName = 'file_' . time() . '_' . mt_rand(1000, 9999) . '.' . $docExt;
        $targetDoc = $uploadDir . $newDocName;

        // move_uploaded_file yoki copy orqali kafolatli saqlash
        $saved = @move_uploaded_file($docFile['tmp_name'], $targetDoc);
        if (!$saved) {
            $saved = @copy($docFile['tmp_name'], $targetDoc);
        }

        if ($saved) {
            $file_url = '/uploads/' . $newDocName;
        } else {
            // Agar server fayl tizimi cheklangan bo'lsa
            echo json_encode(['status' => 'error', 'message' => 'Serverga saqlashda xato: Papka ruxsati yetishmadi. Fayl havolasini (link) kiritib sinab ko‘ring.']);
            exit;
        }
    } elseif (isset($_FILES['product_file']) && $_FILES['product_file']['error'] === UPLOAD_ERR_INI_SIZE) {
        echo json_encode(['status' => 'error', 'message' => 'Fayl hajmi juda katta!']);
        exit;
    }

    // Bazaga yozish
    $stmt = $pdo->prepare("INSERT INTO products (seller_id, title, description, price, category, status, image_url, file_url) VALUES (?, ?, ?, ?, ?, 'active', ?, ?)");
    $stmt->execute([$seller_id, $title, $description, $price, $category, $image_url, $file_url]);

    echo json_encode(['status' => 'success', 'message' => 'E’lon muvaffaqiyatli joylashtirildi!']);
} catch (Throwable $e) {
    echo json_encode(['status' => 'error', 'message' => 'Xatolik: ' . $e->getMessage()]);
}
