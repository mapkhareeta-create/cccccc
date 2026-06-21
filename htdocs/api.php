<?php
// إزالة أي مخرجات سابقة
ob_clean();

error_reporting(0);
ini_set('display_errors', 0);

require_once 'config.php';

// تنظيف أي مخرجات بعد config.php
ob_clean();

header('Content-Type: application/json; charset=utf-8');

$action = clean($_GET['action'] ?? '');

// ============================================
// جلب المدن
// ============================================
if ($action === 'get_cities') {
    $country_id = (int)($_GET['country_id'] ?? 0);
    
    if ($country_id > 0) {
        $cities = getCities($country_id);
        echo json_encode(['status' => 'success', 'data' => $cities], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode(['status' => 'error', 'data' => []], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

// ============================================
// جلب العملة
// ============================================
if ($action === 'get_currency') {
    $country_id = (int)($_GET['country_id'] ?? 0);
    if ($country_id > 0) {
        $currency = getCurrency($country_id);
        echo json_encode(['status' => 'success', 'data' => $currency], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode(['status' => 'error', 'data' => null], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

// ============================================
// تحديث الإشعارات (قراءة / قراءة الكل)
// ============================================
if ($action === 'mark_read') {
    $notif_id = (int)($_GET['notif_id'] ?? 0);
    if ($notif_id > 0 && isLoggedIn()) {
        $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?")->execute([$notif_id, $_SESSION['user_id']]);
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error']);
    }
    exit;
}

if ($action === 'mark_all_read') {
    if (isLoggedIn()) {
        $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?")->execute([$_SESSION['user_id']]);
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error']);
    }
    exit;
}

// ============================================
// دوال المحادثات (CHAT)
// ============================================

// إرسال رسالة
if ($action === 'send_message' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isLoggedIn()) {
        echo json_encode(['status' => 'error', 'message' => 'يجب تسجيل الدخول']);
        exit;
    }
    
    $conversation_id = (int)($_POST['conversation_id'] ?? 0);
    $message = clean($_POST['message'] ?? '');
    $user_id = $_SESSION['user_id'];
    
    if (empty($message)) {
        echo json_encode(['status' => 'error', 'message' => 'الرسالة فارغة']);
        exit;
    }
    
    // التحقق من أن المستخدم مشارك في المحادثة
    $stmt = $pdo->prepare("SELECT * FROM conversations WHERE id = ? AND (employer_id = ? OR contractor_id = ?)");
    $stmt->execute([$conversation_id, $user_id, $user_id]);
    $conv = $stmt->fetch();
    
    if (!$conv) {
        echo json_encode(['status' => 'error', 'message' => 'غير مصرح لك']);
        exit;
    }
    
    // إضافة الرسالة
    $stmt = $pdo->prepare("INSERT INTO messages (conversation_id, sender_id, message) VALUES (?, ?, ?)");
    $stmt->execute([$conversation_id, $user_id, $message]);
    
    // تحديث وقت آخر تحديث للمحادثة
    $pdo->prepare("UPDATE conversations SET updated_at = NOW() WHERE id = ?")->execute([$conversation_id]);
    
    // تحديث عدد الرسائل غير المقروءة للطرف الآخر
    if ($user_id == $conv['employer_id']) {
        $pdo->prepare("UPDATE conversations SET contractor_unread = contractor_unread + 1 WHERE id = ?")->execute([$conversation_id]);
    } else {
        $pdo->prepare("UPDATE conversations SET employer_unread = employer_unread + 1 WHERE id = ?")->execute([$conversation_id]);
    }
    
    echo json_encode(['status' => 'success', 'message' => 'تم الإرسال']);
    exit;
}

// جلب الرسائل
if ($action === 'get_messages') {
    if (!isLoggedIn()) {
        echo json_encode(['status' => 'error', 'message' => 'يجب تسجيل الدخول']);
        exit;
    }
    
    $conversation_id = (int)($_GET['conversation_id'] ?? 0);
    $user_id = $_SESSION['user_id'];
    
    // التحقق من أن المستخدم مشارك في المحادثة
    $stmt = $pdo->prepare("SELECT * FROM conversations WHERE id = ? AND (employer_id = ? OR contractor_id = ?)");
    $stmt->execute([$conversation_id, $user_id, $user_id]);
    $conv = $stmt->fetch();
    
    if (!$conv) {
        echo json_encode(['status' => 'error', 'message' => 'غير مصرح لك']);
        exit;
    }
    
    // جلب الرسائل
    $stmt = $pdo->prepare("
        SELECT m.*, u.name as sender_name, u.user_type as sender_type 
        FROM messages m
        JOIN users u ON m.sender_id = u.id
        WHERE m.conversation_id = ?
        ORDER BY m.created_at ASC
    ");
    $stmt->execute([$conversation_id]);
    $messages = $stmt->fetchAll();
    
    // تحديث حالة القراءة للرسائل التي لم تقرأ بعد
    $stmt = $pdo->prepare("
        UPDATE messages SET is_read = 1 
        WHERE conversation_id = ? AND sender_id != ? AND is_read = 0
    ");
    $stmt->execute([$conversation_id, $user_id]);
    
    $data = [];
    foreach ($messages as $msg) {
        $data[] = [
            'id' => $msg['id'],
            'sender_id' => $msg['sender_id'],
            'sender_name' => $msg['sender_name'],
            'sender_type' => $msg['sender_type'],
            'message' => $msg['message'],
            'is_read' => (bool)$msg['is_read'],
            'created_at' => $msg['created_at'],
            'time_ago' => timeAgo($msg['created_at'])
        ];
    }
    
    echo json_encode(['status' => 'success', 'data' => $data], JSON_UNESCAPED_UNICODE);
    exit;
}

// جلب عدد الرسائل غير المقروءة للمستخدم الحالي
if ($action === 'get_unread_count') {
    if (!isLoggedIn()) {
        echo json_encode(['status' => 'error', 'count' => 0]);
        exit;
    }
    
    $user_id = $_SESSION['user_id'];
    $user_type = getUserType();
    
    if ($user_type === 'employer') {
        $stmt = $pdo->prepare("SELECT SUM(employer_unread) as total FROM conversations WHERE employer_id = ?");
    } elseif ($user_type === 'contractor') {
        $stmt = $pdo->prepare("SELECT SUM(contractor_unread) as total FROM conversations WHERE contractor_id = ?");
    } else {
        echo json_encode(['status' => 'success', 'count' => 0]);
        exit;
    }
    
    $stmt->execute([$user_id]);
    $result = $stmt->fetch();
    $count = (int)($result['total'] ?? 0);
    
    echo json_encode(['status' => 'success', 'count' => $count]);
    exit;
}

// تعيين محادثة كمقروءة بالكامل
if ($action === 'mark_conversation_read') {
    if (!isLoggedIn()) {
        echo json_encode(['status' => 'error']);
        exit;
    }
    
    $conversation_id = (int)($_GET['conversation_id'] ?? 0);
    $user_id = $_SESSION['user_id'];
    $user_type = getUserType();
    
    if ($user_type === 'employer') {
        $pdo->prepare("UPDATE conversations SET employer_unread = 0 WHERE id = ? AND employer_id = ?")->execute([$conversation_id, $user_id]);
    } elseif ($user_type === 'contractor') {
        $pdo->prepare("UPDATE conversations SET contractor_unread = 0 WHERE id = ? AND contractor_id = ?")->execute([$conversation_id, $user_id]);
    }
    
    echo json_encode(['status' => 'success']);
    exit;
}

// إنشاء محادثة جديدة
if ($action === 'create_conversation') {
    if (!isLoggedIn()) {
        echo json_encode(['status' => 'error', 'message' => 'يجب تسجيل الدخول']);
        exit;
    }
    
    $project_id = (int)($_POST['project_id'] ?? 0);
    $contractor_id = (int)($_POST['contractor_id'] ?? 0);
    $user_id = $_SESSION['user_id'];
    $user_type = getUserType();
    
    if ($user_type !== 'employer') {
        echo json_encode(['status' => 'error', 'message' => 'غير مصرح لك']);
        exit;
    }
    
    // التحقق من وجود المحادثة
    $stmt = $pdo->prepare("SELECT id FROM conversations WHERE project_id = ? AND employer_id = ? AND contractor_id = ?");
    $stmt->execute([$project_id, $user_id, $contractor_id]);
    $existing = $stmt->fetch();
    
    if ($existing) {
        echo json_encode(['status' => 'success', 'conversation_id' => $existing['id']]);
        exit;
    }
    
    $stmt = $pdo->prepare("INSERT INTO conversations (project_id, employer_id, contractor_id) VALUES (?, ?, ?)");
    $stmt->execute([$project_id, $user_id, $contractor_id]);
    $conversation_id = $pdo->lastInsertId();
    
    echo json_encode(['status' => 'success', 'conversation_id' => $conversation_id]);
    exit;
}

// جلب قائمة محادثات المستخدم
if ($action === 'get_conversations') {
    if (!isLoggedIn()) {
        echo json_encode(['status' => 'error', 'data' => []]);
        exit;
    }
    
    $user_id = $_SESSION['user_id'];
    $user_type = getUserType();
    
    if ($user_type === 'employer') {
        $stmt = $pdo->prepare("
            SELECT c.*, p.title as project_title, u.name as other_party_name,
                   c.contractor_unread as unread_count,
                   (SELECT message FROM messages WHERE conversation_id = c.id ORDER BY created_at DESC LIMIT 1) as last_message
            FROM conversations c
            JOIN projects p ON c.project_id = p.id
            JOIN users u ON c.contractor_id = u.id
            WHERE c.employer_id = ?
            ORDER BY c.updated_at DESC
        ");
    } elseif ($user_type === 'contractor') {
        $stmt = $pdo->prepare("
            SELECT c.*, p.title as project_title, u.name as other_party_name,
                   c.employer_unread as unread_count,
                   (SELECT message FROM messages WHERE conversation_id = c.id ORDER BY created_at DESC LIMIT 1) as last_message
            FROM conversations c
            JOIN projects p ON c.project_id = p.id
            JOIN users u ON c.employer_id = u.id
            WHERE c.contractor_id = ?
            ORDER BY c.updated_at DESC
        ");
    } else {
        echo json_encode(['status' => 'success', 'data' => []]);
        exit;
    }
    
    $stmt->execute([$user_id]);
    $conversations = $stmt->fetchAll();
    
    $data = [];
    foreach ($conversations as $conv) {
        $data[] = [
            'id' => $conv['id'],
            'project_id' => $conv['project_id'],
            'project_title' => $conv['project_title'],
            'other_party_name' => $conv['other_party_name'],
            'unread_count' => (int)($conv['unread_count'] ?? 0),
            'last_message' => $conv['last_message'] ?? '',
            'updated_at' => $conv['updated_at'],
            'time_ago' => timeAgo($conv['updated_at'])
        ];
    }
    
    echo json_encode(['status' => 'success', 'data' => $data], JSON_UNESCAPED_UNICODE);
    exit;
}

// ============================================
// دوال إدارة المستندات والمقاولين (للأدمن)
// ============================================

// تحديث تصنيف المقاول
if ($action === 'update_contractor_class' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isLoggedIn() || getUserType() !== 'admin') {
        echo json_encode(['status' => 'error', 'message' => 'غير مصرح']);
        exit;
    }
    
    $contractor_id = (int)($_POST['contractor_id'] ?? 0);
    $class = clean($_POST['class'] ?? 'pending');
    
    $allowed_classes = ['pending', 'A', 'B', 'C', 'D'];
    if (!in_array($class, $allowed_classes)) {
        $class = 'pending';
    }
    
    $stmt = $pdo->prepare("UPDATE users SET contractor_class = ? WHERE id = ? AND user_type = 'contractor'");
    $stmt->execute([$class, $contractor_id]);
    
    // إشعار للمقاول بتحديث تصنيفه
    if ($class !== 'pending') {
        createNotification(
            $contractor_id,
            'تم تحديث تصنيفك',
            'تم تحديث تصنيفك إلى ' . $class . ' على المنصة',
            'success',
            SITE_URL . '/profile.php?id=' . $contractor_id
        );
    }
    
    echo json_encode(['status' => 'success']);
    exit;
}

// تحديث حالة المميز للمقاول
if ($action === 'toggle_featured' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isLoggedIn() || getUserType() !== 'admin') {
        echo json_encode(['status' => 'error', 'message' => 'غير مصرح']);
        exit;
    }
    
    $contractor_id = (int)($_POST['contractor_id'] ?? 0);
    $featured = (int)($_POST['featured'] ?? 0);
    $featured_until = $_POST['featured_until'] ?? null;
    
    $stmt = $pdo->prepare("UPDATE users SET is_featured = ?, featured_until = ? WHERE id = ? AND user_type = 'contractor'");
    $stmt->execute([$featured, $featured_until, $contractor_id]);
    
    if ($featured) {
        createNotification(
            $contractor_id,
            'تهانينا! أصبحت مقاولاً مميزاً',
            'تم اختيارك كمقاول مميز على المنصة',
            'success',
            SITE_URL . '/profile.php?id=' . $contractor_id
        );
    }
    
    echo json_encode(['status' => 'success']);
    exit;
}

// قبول أو رفض مستند
if ($action === 'review_document' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isLoggedIn() || getUserType() !== 'admin') {
        echo json_encode(['status' => 'error', 'message' => 'غير مصرح']);
        exit;
    }
    
    $document_id = (int)($_POST['document_id'] ?? 0);
    $status = clean($_POST['status'] ?? '');
    $reject_reason = clean($_POST['reject_reason'] ?? '');
    
    if (!in_array($status, ['approved', 'rejected'])) {
        echo json_encode(['status' => 'error', 'message' => 'حالة غير صالحة']);
        exit;
    }
    
    // جلب معلومات المستند
    $stmt = $pdo->prepare("
        SELECT cd.*, u.name as contractor_name, u.id as contractor_id 
        FROM contractor_documents cd
        JOIN users u ON cd.contractor_id = u.id
        WHERE cd.id = ?
    ");
    $stmt->execute([$document_id]);
    $doc = $stmt->fetch();
    
    if (!$doc) {
        echo json_encode(['status' => 'error', 'message' => 'المستند غير موجود']);
        exit;
    }
    
    // تحديث حالة المستند
    $stmt = $pdo->prepare("UPDATE contractor_documents SET status = ? WHERE id = ?");
    $stmt->execute([$status, $document_id]);
    
    // إشعار للمقاول
    if ($status === 'approved') {
        createNotification(
            $doc['contractor_id'],
            'تم قبول مستندك',
            'تم قبول المستند: ' . $doc['document_name'],
            'success',
            SITE_URL . '/profile.php?id=' . $doc['contractor_id']
        );
        
        // التحقق من جميع مستندات المقاول
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as total, 
                   SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved
            FROM contractor_documents 
            WHERE contractor_id = ?
        ");
        $stmt->execute([$doc['contractor_id']]);
        $stats = $stmt->fetch();
        
        // إذا كان عنده 3 مستندات معتمدة على الأقل، وثق الحساب تلقائياً
        if ($stats['approved'] >= 3 && $stats['total'] >= 3) {
            $stmt = $pdo->prepare("
                UPDATE users 
                SET verification_status = 'approved', 
                    verified_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$doc['contractor_id']]);
            
            createNotification(
                $doc['contractor_id'],
                'تهانينا! حسابك موثق الآن',
                'تم توثيق حسابك بناءً على المستندات المعتمدة',
                'success',
                SITE_URL . '/profile.php?id=' . $doc['contractor_id']
            );
        }
        
    } else {
        createNotification(
            $doc['contractor_id'],
            'تم رفض مستندك',
            'تم رفض المستند: ' . $doc['document_name'] . ($reject_reason ? ' - السبب: ' . $reject_reason : ''),
            'error',
            SITE_URL . '/profile.php?id=' . $doc['contractor_id']
        );
    }
    
    echo json_encode(['status' => 'success']);
    exit;
}

// توثيق مقاول يدوياً
if ($action === 'verify_contractor' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isLoggedIn() || getUserType() !== 'admin') {
        echo json_encode(['status' => 'error', 'message' => 'غير مصرح']);
        exit;
    }
    
    $contractor_id = (int)($_POST['contractor_id'] ?? 0);
    $status = clean($_POST['status'] ?? '');
    
    if (!in_array($status, ['approved', 'rejected'])) {
        echo json_encode(['status' => 'error', 'message' => 'حالة غير صالحة']);
        exit;
    }
    
    $stmt = $pdo->prepare("UPDATE users SET verification_status = ?, verified_at = NOW() WHERE id = ? AND user_type = 'contractor'");
    $stmt->execute([$status, $contractor_id]);
    
    createNotification(
        $contractor_id,
        $status === 'approved' ? 'تم توثيق حسابك' : 'تم رفض توثيق حسابك',
        $status === 'approved' ? 'تم اعتماد حسابك كمقاول موثق على المنصة' : 'لم تتم الموافقة على طلب التوثيق الخاص بك',
        $status === 'approved' ? 'success' : 'error',
        SITE_URL . '/profile.php?id=' . $contractor_id
    );
    
    echo json_encode(['status' => 'success']);
    exit;
}

// ============================================
// نهاية الدوال
// ============================================

echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
?>