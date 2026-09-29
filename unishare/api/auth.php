<?php
// api/auth.php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';

$data = json_decode(file_get_contents('php://input'), true);
$action = $data['action'] ?? '';

if ($action === 'register') {
    $full_name = trim($data['full_name'] ?? '');
    $student_id = trim($data['student_id'] ?? '');
    $phone = trim($data['phone'] ?? '');
    $password = $data['password'] ?? '';

    if (!$full_name || !$student_id || !$phone || !$password) {
        echo json_encode(['status' => 'error', 'message' => 'Barcha maydonlarni to‘ldiring']);
        exit;
    }

    $hash = password_hash($password, PASSWORD_BCRYPT);

    try {
        $stmt = $pdo->prepare("INSERT INTO users (full_name, student_id, phone, password_hash) VALUES (?, ?, ?, ?)");
        $stmt->execute([$full_name, $student_id, $phone, $hash]);
        echo json_encode(['status' => 'success', 'message' => 'Ro‘yxatdan o‘tish muvaffaqiyatli yakunlandi']);
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => 'Bunday talaba ID yoki telefon raqam allaqachon mavjud']);
    }
    exit;
}

if ($action === 'login') {
    $student_id = trim($data['student_id'] ?? '');
    $password = $data['password'] ?? '';

    $stmt = $pdo->prepare("SELECT * FROM users WHERE student_id = ?");
    $stmt->execute([$student_id]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        unset($user['password_hash']);
        echo json_encode(['status' => 'success', 'user' => $user]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Talaba ID yoki parol noto‘g‘ri']);
    }
    exit;
}

if ($action === 'get_user') {
    $user_id = (int)($data['user_id'] ?? 0);
    $stmt = $pdo->prepare("SELECT id, full_name, student_id, phone, balance FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    if ($user) {
        echo json_encode(['status' => 'success', 'user' => $user]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Foydalanuvchi topilmadi']);
    }
    exit;
}