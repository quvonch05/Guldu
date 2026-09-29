<?php
$host = getenv('DB_HOST') ?: 'mysql-303a80d9-baratovquvonchbek569-2874.g.aivencloud.com';
$port = getenv('DB_PORT') ?: 10885;
$db   = getenv('DB_NAME') ?: 'defaultdb';
$user = getenv('DB_USER') ?: 'avnadmin';
$pass = getenv('DB_PASS') ?: 'AVNS_w7t5ZTocttKYDF4Z44w'; // Agar getenv ishlamasa, shu yerga Aiven parolingizni qo'ying

try {
    $dsn = "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_SSL_CA       => true,
        PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false, // Aiven ulanishi uchun shart
    ];
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['error' => true, 'message' => 'DB xatosi: ' . $e->getMessage()]);
    exit;
}