<?php
// unishare/api/get_user_info.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/db.php';

$userId = (int)($_GET['user_id'] ?? 0);
if (!$userId) {
    echo json_encode(['status' => 'error', 'message' => 'User ID kiritilmadi']);
    exit;
}

$stmt = $pdo->prepare("SELECT id, full_name, student_id, phone, role, balance FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user) {
    echo json_encode(['status' => 'success', 'user' => $user]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Foydalanuvchi topilmadi']);
}
