<?php
require_once '../config.php';
header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['status' => 'error', 'message' => 'غير مسجل دخول']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$action = $data['action'] ?? '';
$user_id = $_SESSION['user_id'];
$user_type = $data['user_type'] ?? $_SESSION['user_type'];

// تحديد الجدول والحقل
if ($user_type === 'contractor') {
    $table = 'contractor_alerts';
    $id_field = 'contractor_id';
} elseif ($user_type === 'employer') {
    $table = 'employer_alerts';
    $id_field = 'employer_id';
} else {
    echo json_encode(['status' => 'error', 'message' => 'نوع مستخدم غير صالح']);
    exit;
}

try {
    if ($action === 'toggle') {
        $state = (int)($data['state'] ?? 1);
        $stmt = $pdo->prepare("UPDATE $table SET is_active = ? WHERE $id_field = ?");
        $stmt->execute([$state, $user_id]);
        echo json_encode(['status' => 'success']);
    } elseif ($action === 'settings') {
        $sound = (int)($data['sound'] ?? 1);
        $email = (int)($data['email'] ?? 1);
        $stmt = $pdo->prepare("UPDATE $table SET alert_sound = ?, alert_email = ? WHERE $id_field = ?");
        $stmt->execute([$sound, $email, $user_id]);
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'إجراء غير معروف']);
    }
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>