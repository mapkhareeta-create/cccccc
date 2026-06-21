<?php
require_once '../config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['has_new' => false]);
    exit;
}

$user_id = $_SESSION['user_id'];
$user_type = getUserType();

if ($user_type !== 'contractor') {
    echo json_encode(['has_new' => false]);
    exit;
}

// التحقق من وجود تنبيهات غير مقروءة
$stmt = $pdo->prepare("
    SELECT COUNT(*) as count, GROUP_CONCAT(p.title SEPARATOR '|') as titles
    FROM contractor_alerts_log al
    JOIN projects p ON al.project_id = p.id
    WHERE al.contractor_id = ? AND al.is_read = 0
");
$stmt->execute([$user_id]);
$result = $stmt->fetch();

if ($result && $result['count'] > 0) {
    $titles = explode('|', $result['titles']);
    $first_title = $titles[0];
    $message = $result['count'] > 1 
        ? "لديك {$result['count']} مشاريع جديدة في دولتك" 
        : "مشروع جديد: {$first_title}";
    
    echo json_encode([
        'has_new' => true,
        'count' => (int)$result['count'],
        'message' => $message,
        'first_title' => $first_title
    ]);
} else {
    echo json_encode(['has_new' => false]);
}