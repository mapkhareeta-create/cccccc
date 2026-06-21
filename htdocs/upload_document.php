<?php
require_once 'config.php';

// التأكد من تسجيل الدخول
if (!isLoggedIn()) {
    redirect('login.php');
}

$user_id = $_SESSION['user_id'];
$user_type = getUserType();

// فقط المقاولون يمكنهم رفع مستندات
if ($user_type !== 'contractor') {
    $_SESSION['error'] = 'غير مصرح لك برفع مستندات';
    redirect('profile.php');
}

// جلب رابط العودة
$redirect = $_GET['redirect'] ?? 'profile.php?id=' . $user_id;

// معالجة رفع الملف
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $document_type = clean($_POST['document_type'] ?? '');
    $document_name = clean($_POST['document_name'] ?? '');
    
    // التحقق من صحة المدخلات
    if (empty($document_type) || empty($document_name)) {
        $_SESSION['error'] = 'يرجى إدخال نوع واسم المستند';
        redirect($redirect);
    }
    
    if (!isset($_FILES['document_file']) || $_FILES['document_file']['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['error'] = 'يرجى اختيار ملف صحيح';
        redirect($redirect);
    }
    
    // التحقق من نوع الملف
    $allowed = ['pdf', 'jpg', 'jpeg', 'png'];
    $ext = strtolower(pathinfo($_FILES['document_file']['name'], PATHINFO_EXTENSION));
    
    if (!in_array($ext, $allowed)) {
        $_SESSION['error'] = 'الملف غير مسموح. الأنواع المسموحة: PDF, JPG, PNG';
        redirect($redirect);
    }
    
    // الحد الأقصى لحجم الملف (5 ميجابايت)
    $max_size = 5 * 1024 * 1024; // 5MB
    if ($_FILES['document_file']['size'] > $max_size) {
        $_SESSION['error'] = 'حجم الملف كبير جداً. الحد الأقصى 5 ميجابايت';
        redirect($redirect);
    }
    
    // إنشاء مجلد المستندات إذا لم يكن موجوداً
    $upload_dir = 'assets/uploads/documents/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    // إنشاء اسم فريد للملف
    $filename = time() . '_' . $user_id . '_' . rand(1000, 9999) . '.' . $ext;
    $filepath = $upload_dir . $filename;
    
    // رفع الملف
    if (move_uploaded_file($_FILES['document_file']['tmp_name'], $filepath)) {
        // حفظ في قاعدة البيانات
        $stmt = $pdo->prepare("
            INSERT INTO contractor_documents (contractor_id, document_type, document_name, document_path, status) 
            VALUES (?, ?, ?, ?, 'pending')
        ");
        
        if ($stmt->execute([$user_id, $document_type, $document_name, $filepath])) {
            $_SESSION['success'] = 'تم رفع المستند بنجاح! سيتم مراجعته من قبل الإدارة.';
            
            // إشعار للأدمن بوجود مستند جديد للمراجعة
            $stmt = $pdo->prepare("SELECT id FROM users WHERE user_type = 'admin' LIMIT 1");
            $stmt->execute();
            $admin = $stmt->fetch();
            
            if ($admin) {
                createNotification(
                    $admin['id'],
                    'مستند جديد للمراجعة',
                    'المقاول ' . $_SESSION['user_name'] . ' قام برفع مستند جديد للمراجعة',
                    'info',
                    SITE_URL . '/admin_documents.php'
                );
            }
        } else {
            $_SESSION['error'] = 'حدث خطأ أثناء حفظ بيانات المستند في قاعدة البيانات';
            // حذف الملف إذا فشل الحفظ في قاعدة البيانات
            if (file_exists($filepath)) {
                unlink($filepath);
            }
        }
    } else {
        $_SESSION['error'] = 'حدث خطأ أثناء رفع الملف. يرجى المحاولة مرة أخرى.';
    }
    
    redirect($redirect);
}

// إذا لم يكن هناك ملف مرفوع
redirect($redirect);
?>