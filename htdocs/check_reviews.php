<?php
require_once 'config.php';

try {
    // جلب هيكل جدول reviews
    $stmt = $pdo->query("DESCRIBE reviews");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<h2>📋 أسماء الأعمدة في جدول reviews:</h2>";
    echo "<ul>";
    foreach ($columns as $col) {
        echo "<li><strong>$col</strong></li>";
    }
    echo "</ul>";
    
    echo "<h3>🔍 العمود المناسب للنص هو:</h3>";
    // ابحث عن أسماء شائعة
    $possible = ['comment', 'review_text', 'content', 'feedback', 'review'];
    foreach ($possible as $name) {
        if (in_array($name, $columns)) {
            echo "<p style='color:green; font-size:18px;'>✅ العمود المطلوب هو: <strong>$name</strong></p>";
            break;
        }
    }
    
} catch (Exception $e) {
    echo "❌ خطأ: " . $e->getMessage();
}
?>