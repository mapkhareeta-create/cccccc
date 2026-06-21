<?php
require_once 'config.php';

try {
    // التحقق من وجود عمود last_login
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'last_login'");
    $exists = $stmt->fetch();
    
    if (!$exists) {
        echo "<p>عمود last_login غير موجود. جاري إضافته...</p>";
        $pdo->exec("ALTER TABLE users ADD COLUMN last_login DATETIME NULL");
        echo "<p style='color:green'>✓ تم إضافة عمود last_login بنجاح</p>";
    } else {
        echo "<p style='color:green'>✓ عمود last_login موجود بالفعل</p>";
    }
    
    echo "<p>الآن يمكنك تفعيل تحديث last_login في ملف login.php</p>";
    echo "<a href='login.php'>الذهاب إلى تسجيل الدخول</a>";
    
} catch (Exception $e) {
    echo "<p style='color:red'>خطأ: " . $e->getMessage() . "</p>";
}
?>