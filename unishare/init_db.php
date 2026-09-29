<?php
require_once __DIR__ . '/config/db.php';

try {
    $sql = file_get_contents(__DIR__ . '/schema.sql');
    $pdo->exec($sql);
    echo "Muvaffaqiyatli: Barcha jadvallar bazaga yozildi!";
} catch (PDOException $e) {
    echo "Xatolik: " . $e->getMessage();
}