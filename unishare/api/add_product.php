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

    // 1. Qurilmadan muqova rasmini Base64 shaklida olish
    if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] === UPLOAD_ERR_OK) {
        $imgTmp = $_FILES['image_file']['tmp_name'];
        $imgData = file_get_contents($imgTmp);
        $mime = 'image/jpeg';
        if (function_exists('mime_content_type')) {
            $mime = mime_content_type($imgTmp) ?: 'image/jpeg';
        }
        $image_url = 'data:' . $mime . ';base64,' . base64_encode($imgData);
    }

    if (!$image_url) {
        $image_url = 'https://images.unsplash.com/photo-1544716278-ca5e3f4abd8c?w=500&q=80';
    }

    // 2. Qurilmadan kitob/konspekt faylini Base64 shaklida olish (Render diski cheklovini aylanib o'tish)
    if (isset($_FILES['product_file']) && $_FILES['product_file']['error'] === UPLOAD_ERR_OK) {
        $doc = $_FILES['product_file'];

        // Hajm tekshiruvi: 15 MB
        if ($doc['size'] > 15 * 1024 * 1024) {
            echo json_encode(['status' => 'error', 'message' => 'Fayl hajmi 15 MB dan oshmasligi kerak! Katta hajmdagi fayllar uchun havola (link) maydonidan foydalaning.']);
            exit;
        }

        $fileData = file_get_contents($doc['tmp_name']);
        
        $mimeType = 'application/octet-stream';
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $doc['tmp_name']) ?: 'application/octet-stream';
            finfo_close($finfo);
        }

        $file_url = 'data:' . $mimeType . ';name=' . rawurlencode($doc['name']) . ';base64,' . base64_encode($fileData);
    }

    if (!$file_url) {
        $file_url = $image_url;
    }

    // Bazaga xavfsiz saqlash
    $stmt = $pdo->prepare("INSERT INTO products (seller_id, title, description, price, category, status, image_url, file_url) VALUES (?, ?, ?, ?, ?, 'active', ?, ?)");
    $stmt->execute([$seller_id, $title, $description, $price, $category, $image_url, $file_url]);

    echo json_encode(['status' => 'success', 'message' => 'E’lon muvaffaqiyatli joylashtirildi!']);
} catch (Throwable $e) {
    echo json_encode(['status' => 'error', 'message' => 'Baza xatosi: ' . $e->getMessage()]);
}
