<?php
require_once 'config.php';
try {
    $pdo->exec("ALTER TABLE users ADD COLUMN remember_token VARCHAR(255) NULL");
    echo "✓ تم إضافة عمود remember_token بنجاح";
} catch (Exception $e) {
    echo "العمود موجود مسبقاً أو خطأ: " . $e->getMessage();
}
?>