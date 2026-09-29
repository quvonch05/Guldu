<?php
// unishare/api/add_product.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Faqat POST so‘rov qabul qilinadi!']);
    exit;
}

$seller_id   = (int)($_POST['seller_id'] ?? 0);
$title       = trim($_POST['title'] ?? '');
$description = trim($_POST['description'] ?? '');
$price       = (float)($_POST['price'] ?? 0);
$category    = trim($_POST['category'] ?? 'Kitoblar');
$image_url   = trim($_POST['image_url'] ?? '');
$file_url    = trim($_POST['file_url'] ?? '');

if (!$seller_id || !$title || $price <= 0) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Sarlavha va narxni to‘g‘ri kiriting!']);
    exit;
}

// Fayllar saqlanadigan server papkasi
$uploadDir = __DIR__ . '/../public/uploads/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// 1. Qurilmadan muqova rasmini qabul qilish
if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] === UPLOAD_ERR_OK) {
    $imgFile = $_FILES['image_file'];
    $imgExt = strtolower(pathinfo($imgFile['name'], PATHINFO_EXTENSION));
    $allowedImg = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    
    if (in_array($imgExt, $allowedImg)) {
        $newImgName = 'img_' . time() . '_' . rand(1000, 9999) . '.' . $imgExt;
        $destPath = $uploadDir . $newImgName;
        if (move_uploaded_file($imgFile['tmp_name'], $destPath)) {
            $image_url = '/uploads/' . $newImgName;
        }
    }
}

// Agar rasm yuklanmagan va URL ham kiritilmagan bo'lsa, standart muqova qo'yiladi
if (!$image_url) {
    $image_url = 'https://images.unsplash.com/photo-1544716278-ca5e3f4abd8c?w=500&q=80';
}

// 2. Qurilmadan sotilayotgan kitob/konspekt faylini qabul qilish (PDF, Word, Zip va boshqalar)
if (isset($_FILES['product_file']) && $_FILES['product_file']['error'] === UPLOAD_ERR_OK) {
    $docFile = $_FILES['product_file'];
    $docExt = strtolower(pathinfo($docFile['name'], PATHINFO_EXTENSION));
    $allowedDocs = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'txt', 'zip', 'rar', 'jpg', 'png'];

    if (in_array($docExt, $allowedDocs)) {
        // Fayl hajmi chegarasi: 50 MB
        if ($docFile['size'] <= 50 * 1024 * 1024) {
            $newDocName = 'file_' . time() . '_' . rand(1000, 9999) . '.' . $docExt;
            $docDestPath = $uploadDir . $newDocName;
            if (move_uploaded_file($docFile['tmp_name'], $docDestPath)) {
                $file_url = '/uploads/' . $newDocName;
            }
        } else {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Yuklanayotgan fayl hajmi 50 MB dan oshmasligi lozim!']);
            exit;
        }
    } else {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Ruxsat berilmagan fayl turi! Faqat PDF, Word, PPT yoki Zip formatlarini yuklang.']);
        exit;
    }
}

try {
    $stmt = $pdo->prepare("INSERT INTO products (seller_id, title, description, price, category, status, image_url, file_url) VALUES (?, ?, ?, ?, ?, 'active', ?, ?)");
    $stmt->execute([$seller_id, $title, $description, $price, $category, $image_url, $file_url]);

    echo json_encode(['status' => 'success', 'message' => 'E’lon va mahsulot fayli muvaffaqiyatli joylashtirildi!']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Baza xatosi: ' . $e->getMessage()]);
}