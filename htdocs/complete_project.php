<?php
require_once 'config.php';

// التأكد من تسجيل الدخول
if (!isLoggedIn()) {
    redirect('login.php');
}

if ($_SESSION['user_type'] !== 'employer') {
    $_SESSION['error'] = 'غير مسموح لك بهذه العملية';
    redirect('my_bids.php');
}

$project_id = isset($_GET['project_id']) ? (int)$_GET['project_id'] : 0;
$bid_id = isset($_GET['bid_id']) ? (int)$_GET['bid_id'] : 0;

if (!$project_id || !$bid_id) {
    $_SESSION['error'] = 'بيانات غير صحيحة';
    redirect('my_bids.php');
}

try {
    // التحقق من أن المشروع يخص صاحب العمل وأن العطاء مقبول
    $stmt = $pdo->prepare("
        SELECT p.*, b.contractor_id, b.status as bid_status
        FROM projects p
        JOIN bids b ON p.id = b.project_id
        WHERE p.id = ? AND b.id = ? AND p.employer_id = ?
    ");
    $stmt->execute([$project_id, $bid_id, $_SESSION['user_id']]);
    $data = $stmt->fetch();

    if (!$data) {
        $_SESSION['error'] = 'المشروع أو العطاء غير موجود';
        redirect('my_bids.php?filter=accepted');
    }

    if ($data['status'] !== 'in_progress') {
        $_SESSION['error'] = 'المشروع ليس قيد التنفيذ';
        redirect('my_bids.php?filter=accepted');
    }

    if ($data['bid_status'] !== 'accepted') {
        $_SESSION['error'] = 'العطاء غير مقبول';
        redirect('my_bids.php?filter=accepted');
    }

    // تحديث حالة المشروع إلى مكتمل
    $pdo->beginTransaction();
    
    $stmt = $pdo->prepare("UPDATE projects SET status = 'completed' WHERE id = ?");
    $stmt->execute([$project_id]);

    // إشعار للمقاول
    $stmt_notify = $pdo->prepare("
        INSERT INTO notifications (user_id, title, message, link, type, created_at) 
        VALUES (?, ?, ?, ?, 'success', NOW())
    ");
    $stmt_notify->execute([
        $data['contractor_id'],
        '✅ تم اكتمال المشروع',
        'صاحب العمل ' . $_SESSION['user_name'] . ' أعلن اكتمال المشروع: ' . $data['title'],
        SITE_URL . '/my_bids.php'
    ]);

    $pdo->commit();

    // رسالة نجاح
    $_SESSION['success'] = '✅ تم اكتمال المشروع بنجاح! يمكنك الآن تقييم المقاول.';

} catch (Exception $e) {
    $pdo->rollBack();
    $_SESSION['error'] = 'حدث خطأ: ' . $e->getMessage();
}

// إعادة التوجيه إلى نفس الصفحة (مع الحفاظ على فلتر accepted)
redirect('my_bids.php?filter=accepted');
?>