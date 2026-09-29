<?php
// index.php
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// API so'rovlarini to'g'ri faylga yo'naltirish
if (strpos($uri, '/api/') === 0) {
    $file = __DIR__ . $uri;
    if (file_exists($file)) {
        require $file;
        exit;
    } else {
        header("HTTP/1.1 404 Not Found");
        echo json_encode(['status' => 'error', 'message' => 'API endpoint topilmadi']);
        exit;
    }
}

// Bosh sahifa va statik sahifalar uchun
require __DIR__ . '/public/index.html';