<?php
require_once '../config.php';
header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['error' => 'غير مسجل دخول']);
    exit;
}

$user_id = $_SESSION['user_id'];
$last_id = isset($_GET['last_id']) ? (int)$_GET['last_id'] : 0;

try {
    // جلب الإشعارات الجديدة غير المقروءة
    $stmt = $pdo->prepare("
        SELECT id, title, message, link, type, created_at 
        FROM notifications 
        WHERE user_id = ? AND id > ? AND is_read = 0
        ORDER BY id DESC
    ");
    $stmt->execute([$user_id, $last_id]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // جلب عدد الإشعارات غير المقروءة
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    $unread_count = $stmt->fetchColumn();
    
    echo json_encode([
        'success' => true,
        'notifications' => $notifications,
        'unread_count' => $unread_count
    ]);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>