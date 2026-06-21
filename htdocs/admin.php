<?php
// ============================================
// تفعيل عرض الأخطاء للتشخيص
// ============================================
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ============================================
// تضمين ملف الإعدادات
// ============================================
require_once 'config.php';

// ============================================
// التحقق من وجود الدوال الأساسية
// ============================================
if (!function_exists('clean')) {
    function clean($input) {
        return htmlspecialchars(trim($input ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('isLoggedIn')) {
    function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }
}

if (!function_exists('getUserType')) {
    function getUserType() {
        return $_SESSION['user_type'] ?? null;
    }
}

if (!function_exists('redirect')) {
    function redirect($url) {
        header("Location: " . SITE_URL . "/" . ltrim($url, '/'));
        exit;
    }
}

if (!function_exists('createNotification')) {
    function createNotification($user_id, $title, $message, $type = 'info', $link = '') {
        global $pdo;
        try {
            $tableExists = $pdo->query("SHOW TABLES LIKE 'notifications'")->rowCount() > 0;
            if (!$tableExists) {
                return false;
            }
            $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type, link, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
            return $stmt->execute([$user_id, $title, $message, $type, $link]);
        } catch(Exception $e) {
            error_log("Notification error: " . $e->getMessage());
            return false;
        }
    }
}

// ============================================
// تعريف SITE_EMAIL إذا لم يكن معرفاً
// ============================================
if (!defined('SITE_EMAIL')) {
    define('SITE_EMAIL', 'info@' . $_SERVER['HTTP_HOST']);
}

// ============================================
// دوال إرسال الإيميلات (جميع الدوال موجودة)
// ============================================

if (!function_exists('sendEmail')) {
    function sendEmail($to, $subject, $body) {
        if (empty($to) || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= "From: " . SITE_EMAIL . "\r\n";
        $headers .= "Reply-To: " . SITE_EMAIL . "\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";
        return @mail($to, $subject, $body, $headers);
    }
}

// دوال الإشعارات البريدية (مختصرة للاختصار)
if (!function_exists('sendEmployerVerificationApprovedEmail')) { function sendEmployerVerificationApprovedEmail($to, $name) { return true; } }
if (!function_exists('sendEmployerVerificationRejectedEmail')) { function sendEmployerVerificationRejectedEmail($to, $name) { return true; } }
if (!function_exists('sendProjectApprovedEmail')) { function sendProjectApprovedEmail($to, $name, $project) { return true; } }
if (!function_exists('sendAlertEmail')) { function sendAlertEmail($to, $name, $project) { return true; } }
if (!function_exists('sendBidApprovalEmail')) { function sendBidApprovalEmail($to, $name, $bid) { return true; } }
if (!function_exists('sendBidToEmployerEmail')) { function sendBidToEmployerEmail($to, $name, $bid) { return true; } }
if (!function_exists('sendBidRejectedEmail')) { function sendBidRejectedEmail($to, $name, $bid) { return true; } }
if (!function_exists('sendVerificationApprovedEmail')) { function sendVerificationApprovedEmail($to, $name) { return true; } }
if (!function_exists('sendVerificationRejectedEmail')) { function sendVerificationRejectedEmail($to, $name) { return true; } }

// ============================================
// التأكد من أن المستخدم أدمن
// ============================================
if (!isLoggedIn() || getUserType() !== 'admin') {
    redirect('login.php');
}

// ============================================
// متغيرات الصفحة
// ============================================
$section = clean($_GET['section'] ?? 'dashboard');
$action = clean($_GET['action'] ?? '');
$id = (int)($_GET['id'] ?? 0);
$filter_status = clean($_GET['filter_status'] ?? '');
$filter_type = clean($_GET['filter_type'] ?? '');
$search = clean($_GET['search'] ?? '');

// ============================================
// معالجة POST (تصنيفات، ملاحظات، توثيق)
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // تحديث تصنيف المقاول
    if (isset($_POST['update_class']) && isset($_POST['user_id']) && isset($_POST['contractor_class'])) {
        $userId = (int)$_POST['user_id'];
        $class = clean($_POST['contractor_class']);
        $pdo->prepare("UPDATE users SET contractor_class = ? WHERE id = ?")->execute([$class, $userId]);
        $_SESSION['success'] = '✅ تم تحديث تصنيف المقاول بنجاح';
        redirect('admin.php?section=verification');
    }
    
    // حفظ ملاحظات
    if (isset($_POST['update_notes']) && isset($_POST['user_id']) && isset($_POST['admin_notes'])) {
        $userId = (int)$_POST['user_id'];
        $notes = clean($_POST['admin_notes']);
        $pdo->prepare("UPDATE users SET admin_notes = ? WHERE id = ?")->execute([$notes, $userId]);
        $_SESSION['success'] = '✅ تم حفظ الملاحظات بنجاح';
        redirect('admin.php?section=verification');
    }
    
    // توثيق صاحب العمل
    if (isset($_POST['update_employer_verification']) && isset($_POST['user_id']) && isset($_POST['employer_verification_status'])) {
        $userId = (int)$_POST['user_id'];
        $status = clean($_POST['employer_verification_status']);
        $stmt = $pdo->prepare("SELECT name, email FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        if ($user) {
            $pdo->prepare("UPDATE users SET employer_verification_status = ?, employer_verification_date = NOW() WHERE id = ?")->execute([$status, $userId]);
            $msg = $status === 'approved' ? '🎉 تم توثيق حسابك كصاحب عمل معتمد!' : '❌ لم يتم قبول طلب التوثيق الخاص بك.';
            $type = $status === 'approved' ? 'success' : 'error';
            createNotification($userId, 'تحديث حالة توثيق صاحب العمل', $msg, $type);
            $_SESSION['success'] = '✅ تم تحديث حالة توثيق صاحب العمل بنجاح';
        }
        redirect('admin.php?section=employer_verification');
    }
    
    // إضافة تصنيف جديد
    if (isset($_POST['add_category']) && !empty($_POST['category_name'])) {
        $name = clean($_POST['category_name']);
        $icon = clean($_POST['category_icon'] ?? '🏗️');
        $stmt = $pdo->prepare("INSERT INTO project_categories (name_ar, icon) VALUES (?, ?)");
        $stmt->execute([$name, $icon]);
        $_SESSION['success'] = '✅ تم إضافة التصنيف بنجاح';
        redirect('admin.php?section=settings');
    }
    
    // حذف تصنيف
    if (isset($_POST['delete_category']) && isset($_POST['category_id'])) {
        $catId = (int)$_POST['category_id'];
        $stmt = $pdo->prepare("DELETE FROM project_categories WHERE id = ?");
        $stmt->execute([$catId]);
        $_SESSION['success'] = '✅ تم حذف التصنيف';
        redirect('admin.php?section=settings');
    }
    
    // إضافة دولة
    if (isset($_POST['add_country']) && !empty($_POST['country_name'])) {
        $name = clean($_POST['country_name']);
        $flag = clean($_POST['country_flag'] ?? '🏳️');
        $currency = clean($_POST['country_currency'] ?? '');
        $stmt = $pdo->prepare("INSERT INTO countries (name_ar, flag, currency_code) VALUES (?, ?, ?)");
        $stmt->execute([$name, $flag, $currency]);
        $_SESSION['success'] = '✅ تم إضافة الدولة بنجاح';
        redirect('admin.php?section=settings');
    }
    
    // إرسال إشعار جماعي
    if (isset($_POST['send_bulk_notification'])) {
        $title = clean($_POST['notification_title']);
        $message = clean($_POST['notification_message']);
        $user_type = clean($_POST['notification_user_type'] ?? 'all');
        
        if (!empty($title) && !empty($message)) {
            $query = "SELECT id FROM users WHERE user_type != 'admin'";
            if ($user_type !== 'all') {
                $query .= " AND user_type = '$user_type'";
            }
            $users = $pdo->query($query)->fetchAll();
            
            foreach ($users as $user) {
                createNotification($user['id'], $title, $message, 'info', SITE_URL);
            }
            $_SESSION['success'] = '✅ تم إرسال الإشعار لـ ' . count($users) . ' مستخدم';
        }
        redirect('admin.php?section=notifications');
    }
}

// ============================================
// معالجة إجراءات GET (الإجراءات السريعة)
// ============================================
if ($action && $id) {
    switch ($action) {
        // إدارة المستخدمين
        case 'approve_user':
            $pdo->prepare("UPDATE users SET status = 'active', is_verified = 1 WHERE id = ?")->execute([$id]);
            createNotification($id, 'تم تفعيل حسابك', 'تم تفعيل حسابك بنجاح في مزاد البناء', 'success');
            break;
        case 'suspend_user':
            $pdo->prepare("UPDATE users SET status = 'suspended' WHERE id = ?")->execute([$id]);
            createNotification($id, 'تم إيقاف حسابك', 'تم إيقاف حسابك مؤقتاً من قبل الإدارة', 'warning');
            break;
        case 'activate_user':
            $pdo->prepare("UPDATE users SET status = 'active' WHERE id = ?")->execute([$id]);
            createNotification($id, 'تم تنشيط حسابك', 'تم إعادة تنشيط حسابك بنجاح', 'success');
            break;
        case 'delete_user':
            $pdo->prepare("DELETE FROM users WHERE id = ? AND user_type != 'admin'")->execute([$id]);
            break;
            
        // توثيق المقاولين
        case 'verify_contractor':
            $status = clean($_GET['status'] ?? '');
            if ($status) {
                $stmt = $pdo->prepare("SELECT name, email FROM users WHERE id = ?");
                $stmt->execute([$id]);
                $user = $stmt->fetch();
                if ($user) {
                    $pdo->prepare("UPDATE users SET verification_status = ?, verification_date = NOW(), verification_expiry = DATE_ADD(NOW(), INTERVAL 1 YEAR) WHERE id = ?")->execute([$status, $id]);
                    $msg = $status === 'approved' ? '🎉 تم توثيق حسابك كمقاول معتمد!' : '❌ لم يتم قبول طلب التوثيق الخاص بك.';
                    $type = $status === 'approved' ? 'success' : 'error';
                    createNotification($id, 'تحديث حالة التوثيق', $msg, $type);
                    if (!empty($user['email'])) {
                        if ($status === 'approved') { sendVerificationApprovedEmail($user['email'], $user['name']); }
                        else { sendVerificationRejectedEmail($user['email'], $user['name']); }
                    }
                }
            }
            break;
            
        // توثيق أصحاب العمل
        case 'verify_employer':
            $status = clean($_GET['status'] ?? '');
            if ($status) {
                $stmt = $pdo->prepare("SELECT name, email FROM users WHERE id = ?");
                $stmt->execute([$id]);
                $user = $stmt->fetch();
                if ($user) {
                    $pdo->prepare("UPDATE users SET employer_verification_status = ?, employer_verification_date = NOW() WHERE id = ?")->execute([$status, $id]);
                    $msg = $status === 'approved' ? '🎉 تم توثيق حسابك كصاحب عمل معتمد!' : '❌ لم يتم قبول طلب التوثيق الخاص بك.';
                    $type = $status === 'approved' ? 'success' : 'error';
                    createNotification($id, 'تحديث حالة توثيق صاحب العمل', $msg, $type);
                }
            }
            break;
            
        // تمييز المستخدم
        case 'toggle_featured':
            $current = $pdo->prepare("SELECT is_featured FROM users WHERE id = ?");
            $current->execute([$id]);
            $new = $current->fetchColumn() ? 0 : 1;
            $pdo->prepare("UPDATE users SET is_featured = ? WHERE id = ?")->execute([$new, $id]);
            createNotification($id, 'تحديث حالة التمييز', $new ? '⭐ تم ترقية حسابك إلى مميز' : 'تم إلغاء التميز عن حسابك', 'info');
            break;
            
        // إدارة المشاريع
        case 'approve_project':
            $stmt_proj = $pdo->prepare("SELECT employer_id, title, description, category, country_id FROM projects WHERE id = ?");
            $stmt_proj->execute([$id]);
            $proj_data = $stmt_proj->fetch();
            if ($proj_data) {
                $pdo->prepare("UPDATE projects SET status = 'open' WHERE id = ?")->execute([$id]);
                createNotification($proj_data['employer_id'], '✅ تمت الموافقة على مشروعك!', 'تمت الموافقة الإدارية على مشروعك: ' . $proj_data['title'], 'success', SITE_URL . '/project_detail.php?id=' . $id);
                // إشعار للمقاولين في نفس الدولة
                if ($proj_data['country_id'] > 0) {
                    try {
                        $stmt = $pdo->prepare("SELECT u.id, u.name, u.email FROM users u WHERE u.user_type = 'contractor' AND u.status = 'active' AND u.country_id = ? AND u.verification_status = 'approved'");
                        $stmt->execute([$proj_data['country_id']]);
                        $contractors = $stmt->fetchAll();
                        foreach ($contractors as $contractor) {
                            createNotification($contractor['id'], '🔔 مشروع جديد في منطقتك', 'تم نشر مشروع جديد: ' . $proj_data['title'], 'info', SITE_URL . '/project_detail.php?id=' . $id);
                        }
                    } catch (Exception $e) {}
                }
            }
            break;
            
        case 'reject_project':
            $stmt_proj = $pdo->prepare("SELECT employer_id, title FROM projects WHERE id = ?");
            $stmt_proj->execute([$id]);
            $proj_data = $stmt_proj->fetch();
            if ($proj_data) {
                $pdo->prepare("UPDATE projects SET status = 'cancelled' WHERE id = ?")->execute([$id]);
                createNotification($proj_data['employer_id'], 'تم رفض مشروعك', 'تم رفض مشروعك: ' . $proj_data['title'] . ' من قبل الإدارة.', 'error', SITE_URL . '/profile.php');
            }
            break;
            
        case 'delete_project':
            $pdo->prepare("DELETE FROM projects WHERE id = ?")->execute([$id]);
            break;
            
        // إدارة العطاءات - الموافقة الإدارية (تحويل إلى pending_employer)
        case 'admin_approve_bid':
            $stmt_bid = $pdo->prepare("
                SELECT b.contractor_id, b.project_id, b.amount, b.proposal, b.duration_days,
                       p.employer_id, p.title as project_title, 
                       u.name as contractor_name, u.email as contractor_email
                FROM bids b 
                JOIN projects p ON b.project_id = p.id 
                JOIN users u ON b.contractor_id = u.id
                WHERE b.id = ?
            ");
            $stmt_bid->execute([$id]);
            $bid_data = $stmt_bid->fetch();
            
            if ($bid_data) {
                // ✅ تغيير الحالة إلى pending_employer (بدلاً من مجرد admin_approved)
                $pdo->prepare("UPDATE bids SET status = 'pending_employer', admin_approved = 1 WHERE id = ?")->execute([$id]);
                
                // إشعار للمقاول
                createNotification(
                    $bid_data['contractor_id'], 
                    'تمت الموافقة الإدارية على عطائك', 
                    'تمت الموافقة المبدئية على عطائك في مشروع: ' . $bid_data['project_title'], 
                    'info', 
                    SITE_URL . '/project_detail.php?id=' . $bid_data['project_id']
                );
                
                // ✅ إشعار لصاحب العمل (لإعلامه بوجود عطاء ينتظر موافقته)
                createNotification(
                    $bid_data['employer_id'], 
                    '📩 عطاء جديد يحتاج موافقتك', 
                    'هناك عطاء جديد من المقاول ' . $bid_data['contractor_name'] . ' على مشروعك: ' . $bid_data['project_title'], 
                    'warning', 
                    SITE_URL . '/project_detail.php?id=' . $bid_data['project_id']
                );
            }
            break;
            
        case 'reject_bid':
            $stmt_bid = $pdo->prepare("
                SELECT b.contractor_id, b.project_id, p.title as project_title,
                       u.name as contractor_name, u.email as contractor_email
                FROM bids b 
                JOIN projects p ON b.project_id = p.id 
                JOIN users u ON b.contractor_id = u.id
                WHERE b.id = ?
            ");
            $stmt_bid->execute([$id]);
            $bid_data = $stmt_bid->fetch();
            if ($bid_data) {
                $pdo->prepare("UPDATE bids SET status = 'rejected', admin_approved = 0 WHERE id = ?")->execute([$id]);
                createNotification($bid_data['contractor_id'], 'تم رفض عطاءك', 'تم رفض عطائك في مشروع: ' . $bid_data['project_title'], 'error', SITE_URL . '/project_detail.php?id=' . $bid_data['project_id']);
            }
            break;
            
        case 'delete_bid':
            $pdo->prepare("DELETE FROM bids WHERE id = ?")->execute([$id]);
            break;
            
        case 'reset_bid':
            $pdo->prepare("UPDATE bids SET status = 'pending', admin_approved = 0 WHERE id = ?")->execute([$id]);
            break;
            
        // إدارة المحلات
        case 'delete_shop':
            $pdo->prepare("DELETE FROM shops WHERE id = ?")->execute([$id]);
            break;
        case 'delete_product':
            $pdo->prepare("DELETE FROM products WHERE id = ?")->execute([$id]);
            break;
        case 'suspend_shop':
            $pdo->prepare("UPDATE shops SET status = 'suspended' WHERE id = ?")->execute([$id]);
            $_SESSION['success'] = '⛔ تم إيقاف المحل بنجاح';
            break;
        case 'activate_shop':
            $pdo->prepare("UPDATE shops SET status = 'active' WHERE id = ?")->execute([$id]);
            $_SESSION['success'] = '✅ تم تنشيط المحل بنجاح';
            break;
            
        // ============================================
        // ✅ جـديد: موافقة المحلات (Approve/Reject Shop)
        // ============================================
        case 'approve_shop':
            // جلب بيانات المحل
            $stmt = $pdo->prepare("SELECT user_id, shop_name FROM shops WHERE id = ?");
            $stmt->execute([$id]);
            $shop_data = $stmt->fetch();
            
            if ($shop_data) {
                // تحديث حالة المحل إلى active
                $pdo->prepare("UPDATE shops SET status = 'active' WHERE id = ?")->execute([$id]);
                
                // إشعار لصاحب المحل
                createNotification(
                    $shop_data['user_id'],
                    '✅ تم الموافقة على محلك!',
                    'تم قبول طلب إضافة محل "' . $shop_data['shop_name'] . '". يمكنك الآن عرض منتجاتك في منصة مزاد البناء.',
                    'success',
                    SITE_URL . '/edit_shop.php'
                );
                
                $_SESSION['success'] = '✅ تم تفعيل المحل "' . $shop_data['shop_name'] . '" بنجاح';
            }
            break;

        case 'reject_shop':
            // جلب بيانات المحل
            $stmt = $pdo->prepare("SELECT user_id, shop_name FROM shops WHERE id = ?");
            $stmt->execute([$id]);
            $shop_data = $stmt->fetch();
            
            if ($shop_data) {
                // إشعار لصاحب المحل قبل الحذف
                createNotification(
                    $shop_data['user_id'],
                    '❌ تم رفض محلك',
                    'نأسف لإعلامك بأن طلب إضافة محل "' . $shop_data['shop_name'] . '" لم يتم قبوله. يمكنك التواصل مع الدعم للحصول على مزيد من المعلومات.',
                    'error',
                    SITE_URL . '/contact.php'
                );
                
                // حذف المحل
                $pdo->prepare("DELETE FROM shops WHERE id = ?")->execute([$id]);
                
                $_SESSION['success'] = '❌ تم رفض المحل "' . $shop_data['shop_name'] . '" وحذفه';
            }
            break;
    }
    
    // تسجيل في سجل العمليات
    try {
        $table_check = $pdo->query("SHOW TABLES LIKE 'admin_logs'")->rowCount();
        if ($table_check > 0) {
            $pdo->prepare("INSERT INTO admin_logs (admin_id, action, details) VALUES (?, ?, ?)")
                ->execute([$_SESSION['user_id'], $action, "ID: $id"]);
        }
    } catch (Exception $e) {}
    
    redirect('admin.php?section=' . $section);
}

// ============================================
// الإحصائيات المتقدمة
// ============================================
$stats = [
    'users' => $pdo->query("SELECT COUNT(*) FROM users WHERE user_type != 'admin'")->fetchColumn(),
    'employers' => $pdo->query("SELECT COUNT(*) FROM users WHERE user_type = 'employer'")->fetchColumn(),
    'contractors' => $pdo->query("SELECT COUNT(*) FROM users WHERE user_type = 'contractor'")->fetchColumn(),
    'shops' => $pdo->query("SELECT COUNT(*) FROM users WHERE user_type = 'shop'")->fetchColumn(),
    'projects' => $pdo->query("SELECT COUNT(*) FROM projects")->fetchColumn(),
    'open_projects' => $pdo->query("SELECT COUNT(*) FROM projects WHERE status = 'open'")->fetchColumn(),
    'pending_projects' => $pdo->query("SELECT COUNT(*) FROM projects WHERE status = 'pending'")->fetchColumn(),
    'in_progress_projects' => $pdo->query("SELECT COUNT(*) FROM projects WHERE status = 'in_progress'")->fetchColumn(),
    'completed_projects' => $pdo->query("SELECT COUNT(*) FROM projects WHERE status = 'completed'")->fetchColumn(),
    'bids' => $pdo->query("SELECT COUNT(*) FROM bids")->fetchColumn(),
    'pending_bids' => $pdo->query("SELECT COUNT(*) FROM bids WHERE status = 'pending'")->fetchColumn(),
    'pending_employer_bids' => $pdo->query("SELECT COUNT(*) FROM bids WHERE status = 'pending_employer'")->fetchColumn(),
    'accepted_bids' => $pdo->query("SELECT COUNT(*) FROM bids WHERE status = 'accepted'")->fetchColumn(),
    'rejected_bids' => $pdo->query("SELECT COUNT(*) FROM bids WHERE status = 'rejected'")->fetchColumn(),
    'products' => $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn(),
    'pending_users' => $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'pending'")->fetchColumn(),
    'verified_contractors' => $pdo->query("SELECT COUNT(*) FROM users WHERE user_type = 'contractor' AND verification_status = 'approved'")->fetchColumn(),
    'pending_verification' => $pdo->query("SELECT COUNT(*) FROM users WHERE user_type = 'contractor' AND verification_status = 'pending'")->fetchColumn(),
    'verified_employers' => $pdo->query("SELECT COUNT(*) FROM users WHERE user_type = 'employer' AND employer_verification_status = 'approved'")->fetchColumn(),
    'pending_employer_verification' => $pdo->query("SELECT COUNT(*) FROM users WHERE user_type = 'employer' AND employer_verification_status = 'pending'")->fetchColumn(),
    // ✅ جديد: المحلات المعلقة للموافقة
    'pending_shops' => $pdo->query("SELECT COUNT(*) FROM shops WHERE status = 'pending'")->fetchColumn(),
];

// جلب آخر النشاطات
$recent_activities = [];
try {
    $stmt = $pdo->prepare("
        SELECT * FROM admin_logs ORDER BY created_at DESC LIMIT 10
    ");
    $stmt->execute();
    $recent_activities = $stmt->fetchAll();
} catch (Exception $e) {}

$page_title = 'لوحة التحكم - الأدمن';
include 'includes/header.php';
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        /* ===== تنسيقات عامة محسنة ===== */
        * { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; box-sizing: border-box; }
        body { background: #f1f5f9; margin: 0; padding: 0; }
        
        .admin-layout { display: flex; min-height: 100vh; }
        .admin-sidebar { width: 250px; background: #0f172a; color: #94a3b8; padding: 20px 0; flex-shrink: 0; position: sticky; top: 0; height: 100vh; overflow-y: auto; }
        .admin-sidebar .logo { color: white; font-weight: 800; font-size: 20px; padding: 0 20px 20px; border-bottom: 1px solid #1e293b; text-align: center; }
        .admin-sidebar .logo span { color: #3b82f6; }
        .admin-sidebar nav a { display: flex; align-items: center; gap: 12px; padding: 10px 20px; color: #94a3b8; text-decoration: none; transition: 0.3s; border-radius: 8px; margin: 2px 10px; }
        .admin-sidebar nav a:hover { background: #1e293b; color: white; }
        .admin-sidebar nav a.active { background: #2563eb; color: white; }
        .admin-sidebar nav a .badge { background: #ef4444; color: white; font-size: 10px; padding: 1px 8px; border-radius: 20px; margin-right: auto; }
        .admin-sidebar nav a .badge.yellow { background: #f59e0b; }
        .admin-sidebar nav a .badge.blue { background: #3b82f6; }
        .admin-sidebar nav a .badge.green { background: #22c55e; }
        
        .admin-content { flex: 1; padding: 24px; }
        
        /* ===== كروت الإحصائيات ===== */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 16px; margin-bottom: 24px; }
        .stat-card { background: white; padding: 18px; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.04); transition: all 0.3s; }
        .stat-card:hover { transform: translateY(-3px); box-shadow: 0 8px 24px rgba(0,0,0,0.08); }
        .stat-card .num { font-size: 28px; font-weight: 900; color: #0f172a; }
        .stat-card .label { color: #64748b; font-size: 13px; margin-top: 4px; }
        .stat-card .icon { font-size: 22px; margin-bottom: 4px; }
        .stat-card.pending .num { color: #f59e0b; }
        .stat-card.success .num { color: #22c55e; }
        .stat-card.danger .num { color: #ef4444; }
        .stat-card.info .num { color: #3b82f6; }
        .stat-card.purple .num { color: #8b5cf6; }
        
        /* ===== الجداول ===== */
        .table-wrap { background: white; border-radius: 12px; border: 1px solid #e2e8f0; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.04); margin-top: 16px; overflow-x: auto; }
        .table-wrap table { width: 100%; font-size: 13px; border-collapse: collapse; }
        .table-wrap th { text-align: right; padding: 12px 16px; background: #f8fafc; font-weight: 600; color: #475569; border-bottom: 1px solid #e2e8f0; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; white-space: nowrap; }
        .table-wrap td { padding: 10px 16px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
        .table-wrap tr:hover { background: #f8fafc; }
        .table-wrap .empty-state { text-align: center; padding: 40px; color: #94a3b8; }
        
        /* ===== الأزرار ===== */
        .btn-xs { display: inline-block; padding: 4px 12px; border-radius: 6px; font-size: 11px; font-weight: 600; text-decoration: none; margin: 2px; border: none; cursor: pointer; transition: all 0.2s; }
        .btn-xs:hover { transform: translateY(-1px); opacity: 0.9; }
        .btn-xs.green { background: #dcfce7; color: #166534; }
        .btn-xs.red { background: #fee2e2; color: #991b1b; }
        .btn-xs.blue { background: #dbeafe; color: #1e40af; }
        .btn-xs.yellow { background: #fef3c7; color: #92400e; }
        .btn-xs.purple { background: #f3e8ff; color: #6b21a5; }
        .btn-xs.gray { background: #f1f5f9; color: #475569; }
        .btn-xs.cyan { background: #cffafe; color: #0e7490; }
        
        /* ===== شارات الحالة ===== */
        .badge-status { padding: 3px 12px; border-radius: 20px; font-size: 11px; font-weight: 600; display: inline-block; }
        .badge-status.pending { background: #fef3c7; color: #92400e; }
        .badge-status.open { background: #dcfce7; color: #166534; }
        .badge-status.in_progress { background: #fef3c7; color: #92400e; }
        .badge-status.completed { background: #dbeafe; color: #1e40af; }
        .badge-status.cancelled { background: #fee2e2; color: #991b1b; }
        .badge-status.accepted { background: #dcfce7; color: #166534; }
        .badge-status.rejected { background: #fee2e2; color: #991b1b; }
        .badge-status.admin_approved { background: #dbeafe; color: #1e40af; }
        .badge-status.approved { background: #dcfce7; color: #166534; }
        .badge-status.active { background: #dcfce7; color: #166534; }
        .badge-status.suspended { background: #fee2e2; color: #991b1b; }
        .badge-status.pending_employer { background: #fef3c7; color: #92400e; }
        .badge-status.employer { background: #dbeafe; color: #1e40af; }
        .badge-status.contractor { background: #fef3c7; color: #92400e; }
        .badge-status.shop { background: #d1fae5; color: #065f46; }
        
        /* ===== الفلتر ===== */
        .flex-between { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; margin-bottom: 16px; }
        .filter-links { display: flex; gap: 6px; flex-wrap: wrap; }
        .filter-links a { padding: 5px 16px; border-radius: 20px; font-size: 12px; font-weight: 600; text-decoration: none; background: #f1f5f9; color: #475569; transition: all 0.2s; }
        .filter-links a:hover { background: #e2e8f0; }
        .filter-links a.active { background: #2563eb; color: white; }
        
        .search-box { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
        .search-box input, .search-box select { padding: 8px 14px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 13px; min-width: 160px; transition: all 0.2s; background: white; color: #0f172a; }
        .search-box input:focus, .search-box select:focus { outline: none; border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,0.1); }
        .search-box .btn-search { background: #2563eb; color: white; border: none; padding: 8px 20px; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.2s; }
        .search-box .btn-search:hover { background: #1d4ed8; }
        .search-box .btn-reset { background: #f1f5f9; color: #475569; border: none; padding: 8px 16px; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.2s; text-decoration: none; display: inline-flex; align-items: center; gap: 4px; }
        .search-box .btn-reset:hover { background: #e2e8f0; }
        
        /* ===== الأدوات ===== */
        .section-title { font-size: 22px; font-weight: 700; color: #0f172a; margin-bottom: 4px; }
        .section-subtitle { color: #64748b; font-size: 14px; margin-bottom: 20px; }
        .bg-yellow-50 { background: #fefce8; }
        .bg-green-50 { background: #f0fdf4; }
        .bg-blue-50 { background: #eff6ff; }
        .bg-red-50 { background: #fef2f2; }
        
        /* ===== مودال ===== */
        .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center; backdrop-filter: blur(4px); padding: 20px; }
        .modal-overlay.open { display: flex; }
        .modal-box { background: white; padding: 30px; border-radius: 16px; max-width: 500px; width: 100%; max-height: 90vh; overflow-y: auto; box-shadow: 0 20px 60px rgba(0,0,0,0.15); }
        .modal-box h3 { font-size: 18px; font-weight: 700; color: #0f172a; margin-bottom: 6px; }
        .modal-box .sub { color: #64748b; font-size: 14px; margin-bottom: 16px; }
        .modal-box label { display: block; font-weight: 600; font-size: 13px; color: #1e293b; margin-bottom: 4px; }
        .modal-box select, .modal-box textarea, .modal-box input[type="text"], .modal-box input[type="number"] { width: 100%; padding: 10px 14px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 14px; font-family: inherit; }
        .modal-box select:focus, .modal-box textarea:focus, .modal-box input:focus { outline: none; border-color: #3b82f6; }
        .modal-box textarea { min-height: 100px; resize: vertical; }
        .modal-actions { display: flex; gap: 10px; margin-top: 16px; }
        .modal-actions .btn-save { background: #22c55e; color: white; border: none; padding: 10px 24px; border-radius: 8px; font-weight: 600; cursor: pointer; }
        .modal-actions .btn-save:hover { background: #16a34a; }
        .modal-actions .btn-cancel { background: #f1f5f9; color: #475569; border: none; padding: 10px 24px; border-radius: 8px; font-weight: 600; cursor: pointer; }
        .modal-actions .btn-cancel:hover { background: #e2e8f0; }
        
        /* ===== روابط سريعة ===== */
        .quick-actions { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 12px; margin-bottom: 24px; }
        .quick-action { background: white; padding: 16px; border-radius: 12px; border: 1px solid #e2e8f0; text-align: center; text-decoration: none; color: #0f172a; transition: all 0.3s; display: flex; flex-direction: column; align-items: center; gap: 6px; }
        .quick-action:hover { transform: translateY(-3px); box-shadow: 0 8px 24px rgba(0,0,0,0.08); border-color: #3b82f6; }
        .quick-action .icon { font-size: 28px; }
        .quick-action .label { font-size: 13px; font-weight: 600; }
        .quick-action .count { font-size: 11px; color: #94a3b8; }
        
        /* ===== التجاوب ===== */
        @media (max-width: 768px) { 
            .admin-sidebar { display: none; } 
            .stats-grid { grid-template-columns: 1fr 1fr; } 
            .search-box { flex-direction: column; align-items: stretch; } 
            .search-box input { min-width: auto; }
            .quick-actions { grid-template-columns: 1fr 1fr; }
            .filter-links { flex-wrap: wrap; }
        }
        @media (max-width: 480px) { 
            .stats-grid { grid-template-columns: 1fr; } 
            .quick-actions { grid-template-columns: 1fr; }
        }
        
        /* ===== إضافات ===== */
        .class-badge { padding: 2px 12px; border-radius: 12px; font-weight: 700; font-size: 12px; color: white; display: inline-block; }
        .class-badge.A { background: #22c55e; }
        .class-badge.B { background: #3b82f6; }
        .class-badge.C { background: #f59e0b; }
        .class-badge.D { background: #ef4444; }
        
        .toast-container { position: fixed; top: 20px; left: 50%; transform: translateX(-50%); z-index: 10000; width: 100%; max-width: 500px; padding: 0 20px; pointer-events: none; }
        .toast { background: white; border-radius: 12px; padding: 14px 20px; margin-bottom: 10px; box-shadow: 0 10px 40px rgba(0,0,0,0.15); display: flex; align-items: center; gap: 12px; pointer-events: auto; animation: slideDown 0.3s ease; border-right: 4px solid #64748b; }
        .toast.success { border-right-color: #22c55e; }
        .toast.error { border-right-color: #ef4444; }
        .toast.warning { border-right-color: #f59e0b; }
        .toast .close { background: none; border: none; font-size: 18px; color: #94a3b8; cursor: pointer; }
        @keyframes slideDown { from { opacity: 0; transform: translateY(-20px); } to { opacity: 1; transform: translateY(0); } }
    </style>
</head>
<body>

<div class="toast-container" id="toastContainer">
    <?php if (isset($_SESSION['success'])): ?>
        <div class="toast success">
            <span>✅</span>
            <span><?= $_SESSION['success']; unset($_SESSION['success']); ?></span>
            <button class="close" onclick="this.parentElement.remove()">&times;</button>
        </div>
    <?php endif; ?>
    <?php if (isset($_SESSION['error'])): ?>
        <div class="toast error">
            <span>❌</span>
            <span><?= $_SESSION['error']; unset($_SESSION['error']); ?></span>
            <button class="close" onclick="this.parentElement.remove()">&times;</button>
        </div>
    <?php endif; ?>
</div>

<div class="admin-layout">
    
    <!-- ===== القائمة الجانبية ===== -->
    <aside class="admin-sidebar">
        <div class="logo">🛡️ مزاد<span>البناء</span></div>
        <nav>
            <a href="admin.php?section=dashboard" class="<?= $section === 'dashboard' ? 'active' : '' ?>">
                <i class="fas fa-chart-pie"></i> الإحصائيات
            </a>
            <a href="admin.php?section=users" class="<?= $section === 'users' ? 'active' : '' ?>">
                <i class="fas fa-users"></i> المستخدمين
                <?php if($stats['pending_users'] > 0): ?><span class="badge"><?= $stats['pending_users'] ?></span><?php endif; ?>
            </a>
            <a href="admin.php?section=verification" class="<?= $section === 'verification' ? 'active' : '' ?>">
                <i class="fas fa-id-card"></i> توثيق المقاولين
                <?php if($stats['pending_verification'] > 0): ?><span class="badge yellow"><?= $stats['pending_verification'] ?></span><?php endif; ?>
            </a>
            <a href="admin.php?section=employer_verification" class="<?= $section === 'employer_verification' ? 'active' : '' ?>">
                <i class="fas fa-briefcase"></i> توثيق أصحاب العمل
                <?php if($stats['pending_employer_verification'] > 0): ?><span class="badge blue"><?= $stats['pending_employer_verification'] ?></span><?php endif; ?>
            </a>
            <a href="admin.php?section=projects" class="<?= $section === 'projects' ? 'active' : '' ?>">
                <i class="fas fa-project-diagram"></i> المشاريع
                <?php if($stats['pending_projects'] > 0): ?><span class="badge"><?= $stats['pending_projects'] ?></span><?php endif; ?>
            </a>
            <a href="admin.php?section=bids_list" class="<?= $section === 'bids_list' ? 'active' : '' ?>">
                <i class="fas fa-gavel"></i> العطاءات
                <?php if($stats['pending_bids'] > 0): ?><span class="badge"><?= $stats['pending_bids'] ?></span><?php endif; ?>
            </a>
            
            <!-- ✅ جـديد: موافقة المحلات -->
            <a href="admin.php?section=shops_verification" class="<?= $section === 'shops_verification' ? 'active' : '' ?>">
                <i class="fas fa-store"></i> موافقة المحلات
                <?php if($stats['pending_shops'] > 0): ?>
                <span class="badge"><?= $stats['pending_shops'] ?></span>
                <?php endif; ?>
            </a>
            
            <a href="admin.php?section=shops_list" class="<?= $section === 'shops_list' ? 'active' : '' ?>">
                <i class="fas fa-store-alt"></i> المحلات
            </a>
            <a href="admin.php?section=products" class="<?= $section === 'products' ? 'active' : '' ?>">
                <i class="fas fa-boxes"></i> المنتجات
            </a>
            <a href="admin.php?section=settings" class="<?= $section === 'settings' ? 'active' : '' ?>">
                <i class="fas fa-cog"></i> الإعدادات
            </a>
            <a href="admin.php?section=notifications" class="<?= $section === 'notifications' ? 'active' : '' ?>">
                <i class="fas fa-bell"></i> الإشعارات
            </a>
            <a href="admin.php?section=logs" class="<?= $section === 'logs' ? 'active' : '' ?>">
                <i class="fas fa-history"></i> سجل العمليات
            </a>
            <div style="border-top:1px solid #1e293b;margin:12px 16px;"></div>
            <a href="<?= SITE_URL ?>/"><i class="fas fa-home"></i> الرئيسية</a>
            <a href="<?= SITE_URL ?>/logout.php" style="color:#ef4444;"><i class="fas fa-sign-out-alt"></i> تسجيل خروج</a>
        </nav>
    </aside>
    
    <!-- ===== المحتوى الرئيسي ===== -->
    <main class="admin-content">
        
        <!-- ============================================================ -->
        <!-- 1. لوحة التحكم (Dashboard) -->
        <!-- ============================================================ -->
        <?php if ($section === 'dashboard'): ?>
        <h2 class="section-title">📊 لوحة التحكم</h2>
        <p class="section-subtitle">نظرة عامة على منصة مزاد البناء</p>
        
        <!-- روابط سريعة -->
        <div class="quick-actions">
            <a href="admin.php?section=projects&filter_status=pending" class="quick-action">
                <span class="icon">⏳</span>
                <span class="label">مشاريع معلقة</span>
                <span class="count"><?= $stats['pending_projects'] ?></span>
            </a>
            <a href="admin.php?section=bids_list&filter_status=pending" class="quick-action">
                <span class="icon">📋</span>
                <span class="label">عطاءات معلقة</span>
                <span class="count"><?= $stats['pending_bids'] ?></span>
            </a>
            <a href="admin.php?section=verification&filter_status=pending" class="quick-action">
                <span class="icon">🆔</span>
                <span class="label">توثيق مقاولين</span>
                <span class="count"><?= $stats['pending_verification'] ?></span>
            </a>
            <a href="admin.php?section=employer_verification&filter_status=pending" class="quick-action">
                <span class="icon">👔</span>
                <span class="label">توثيق أصحاب عمل</span>
                <span class="count"><?= $stats['pending_employer_verification'] ?></span>
            </a>
            <!-- ✅ جـديد: موافقة المحلات -->
            <a href="admin.php?section=shops_verification" class="quick-action">
                <span class="icon">🏪</span>
                <span class="label">موافقة المحلات</span>
                <span class="count"><?= $stats['pending_shops'] ?></span>
            </a>
            <a href="admin.php?section=users&filter_status=pending" class="quick-action">
                <span class="icon">👤</span>
                <span class="label">مستخدمين معلقين</span>
                <span class="count"><?= $stats['pending_users'] ?></span>
            </a>
        </div>
        
        <!-- الإحصائيات -->
        <div class="stats-grid">
            <div class="stat-card info"><div class="icon">👥</div><div class="num"><?= number_format($stats['users']) ?></div><div class="label">إجمالي المستخدمين</div></div>
            <div class="stat-card success"><div class="icon">🏗️</div><div class="num"><?= number_format($stats['projects']) ?></div><div class="label">إجمالي المشاريع</div></div>
            <div class="stat-card purple"><div class="icon">📋</div><div class="num"><?= number_format($stats['bids']) ?></div><div class="label">إجمالي العطاءات</div></div>
            <div class="stat-card info"><div class="icon">📦</div><div class="num"><?= number_format($stats['products']) ?></div><div class="label">المنتجات</div></div>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card"><div class="icon">👨‍💼</div><div class="num"><?= number_format($stats['employers']) ?></div><div class="label">أصحاب عمل</div></div>
            <div class="stat-card success"><div class="icon">✅</div><div class="num"><?= number_format($stats['verified_employers'] ?? 0) ?></div><div class="label">موثقين</div></div>
            <div class="stat-card pending"><div class="icon">⏳</div><div class="num"><?= number_format($stats['pending_employer_verification'] ?? 0) ?></div><div class="label">بإنتظار توثيق</div></div>
            <div class="stat-card"><div class="icon">👷</div><div class="num"><?= number_format($stats['contractors']) ?></div><div class="label">مقاولين</div></div>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card success"><div class="icon">📈</div><div class="num"><?= $stats['open_projects'] ?></div><div class="label">مشاريع مفتوحة</div></div>
            <div class="stat-card pending"><div class="icon">🔄</div><div class="num"><?= $stats['in_progress_projects'] ?></div><div class="label">قيد التنفيذ</div></div>
            <div class="stat-card info"><div class="icon">✔️</div><div class="num"><?= $stats['completed_projects'] ?></div><div class="label">مكتملة</div></div>
            <div class="stat-card"><div class="icon">💰</div><div class="num"><?= $stats['accepted_bids'] ?></div><div class="label">عطاءات مقبولة</div></div>
        </div>
        
        <!-- آخر النشاطات -->
        <div class="table-wrap">
            <h3 style="padding:16px 20px;margin:0;font-size:16px;font-weight:700;color:#0f172a;border-bottom:1px solid #e2e8f0;">
                📜 آخر النشاطات
            </h3>
            <table>
                <thead><tr><th>#</th><th>المشرف</th><th>الإجراء</th><th>التفاصيل</th><th>التاريخ</th></tr></thead>
                <tbody>
                    <?php if (empty($recent_activities)): ?>
                        <tr><td colspan="5" class="empty-state">لا توجد سجلات بعد</td></tr>
                    <?php else: foreach ($recent_activities as $i => $log): ?>
                        <tr>
                            <td><?= $i+1 ?></td>
                            <td><strong><?= clean($log['admin_name'] ?? 'أدمن') ?></strong></td>
                            <td><span class="badge-status pending"><?= clean($log['action']) ?></span></td>
                            <td><?= clean($log['details'] ?? '-') ?></td>
                            <td><?= date('Y/m/d H:i', strtotime($log['created_at'])) ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- ============================================================ -->
        <!-- 2. إدارة المستخدمين -->
        <!-- ============================================================ -->
        <?php elseif ($section === 'users'): ?>
        <h2 class="section-title">👥 إدارة المستخدمين</h2>
        <p class="section-subtitle">إدارة حسابات المستخدمين والتحكم بصلاحياتهم</p>
        
        <div class="flex-between">
            <div class="filter-links">
                <a href="admin.php?section=users" class="<?= empty($filter_status) && empty($filter_type) ? 'active' : '' ?>">الكل (<?= $stats['users'] ?>)</a>
                <a href="admin.php?section=users&filter_status=pending" class="<?= $filter_status === 'pending' ? 'active' : '' ?>">⏳ معلق (<?= $stats['pending_users'] ?? 0 ?>)</a>
                <a href="admin.php?section=users&filter_status=active" class="<?= $filter_status === 'active' ? 'active' : '' ?>">✅ مفعل</a>
                <a href="admin.php?section=users&filter_status=suspended" class="<?= $filter_status === 'suspended' ? 'active' : '' ?>">⛔ موقوف</a>
                <a href="admin.php?section=users&filter_type=employer" class="<?= $filter_type === 'employer' ? 'active' : '' ?>">👨‍💼 أصحاب عمل</a>
                <a href="admin.php?section=users&filter_type=contractor" class="<?= $filter_type === 'contractor' ? 'active' : '' ?>">👷 مقاولين</a>
                <a href="admin.php?section=users&filter_type=shop" class="<?= $filter_type === 'shop' ? 'active' : '' ?>">🏪 محلات</a>
            </div>
            <div class="search-box">
                <form method="GET" action="" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                    <input type="hidden" name="section" value="users">
                    <input type="text" name="search" placeholder="🔍 بحث باسم أو بريد..." value="<?= clean($search) ?>">
                    <button type="submit" class="btn-search"><i class="fas fa-search"></i> بحث</button>
                    <a href="admin.php?section=users" class="btn-reset"><i class="fas fa-times"></i> إعادة ضبط</a>
                </form>
            </div>
        </div>
        
        <?php
        $users_query = "SELECT u.*, c.name_ar as country_name, ci.name_ar as city_name FROM users u 
                        LEFT JOIN countries c ON u.country_id = c.id 
                        LEFT JOIN cities ci ON u.city_id = ci.id 
                        WHERE u.user_type != 'admin'";
        if (!empty($search)) {
            $users_query .= " AND (u.name LIKE '%$search%' OR u.email LIKE '%$search%')";
        }
        if ($filter_status === 'pending') {
            $users_query .= " AND u.status = 'pending'";
        } elseif ($filter_status === 'active') {
            $users_query .= " AND u.status = 'active'";
        } elseif ($filter_status === 'suspended') {
            $users_query .= " AND u.status = 'suspended'";
        }
        if ($filter_type === 'employer') {
            $users_query .= " AND u.user_type = 'employer'";
        } elseif ($filter_type === 'contractor') {
            $users_query .= " AND u.user_type = 'contractor'";
        } elseif ($filter_type === 'shop') {
            $users_query .= " AND u.user_type = 'shop'";
        }
        $users_query .= " ORDER BY u.created_at DESC";
        $all_users = $pdo->query($users_query)->fetchAll();
        ?>
        
        <div class="table-wrap">
            <table>
                <thead><tr><th>#</th><th>الاسم</th><th>البريد</th><th>النوع</th><th>الموقع</th><th>الحالة</th><th>تاريخ التسجيل</th><th>إجراءات</th></tr></thead>
                <tbody>
                    <?php if(empty($all_users)): ?><tr><td colspan="8" class="empty-state">لا يوجد مستخدمين</td></tr><?php endif; ?>
                    <?php foreach($all_users as $i => $user): ?>
                    <tr>
                        <td><?= $i+1 ?></td>
                        <td><strong><?= clean($user['name']) ?></strong></td>
                        <td><?= clean($user['email']) ?></td>
                        <td><span class="badge-status <?= $user['user_type'] ?>"><?= ['employer'=>'صاحب عمل','contractor'=>'مقاول','shop'=>'محل'][$user['user_type']] ?? $user['user_type'] ?></span></td>
                        <td><?= clean($user['city_name']??'') ?>، <?= clean($user['country_name']??'-') ?></td>
                        <td><span class="badge-status <?= $user['status'] ?>"><?= $user['status'] === 'active' ? 'مفعّل' : ($user['status'] === 'pending' ? 'معلّق' : 'موقوف') ?></span></td>
                        <td style="font-size:11px;color:#94a3b8;"><?= date('Y/m/d', strtotime($user['created_at'])) ?></td>
                        <td>
                            <?php if($user['status'] === 'pending'): ?>
                            <a href="admin.php?section=users&action=approve_user&id=<?= $user['id'] ?>" class="btn-xs green" onclick="return confirm('تفعيل هذا الحساب؟')"><i class="fas fa-check"></i> تفعيل</a>
                            <?php endif; ?>
                            <?php if($user['status'] === 'active'): ?>
                            <a href="admin.php?section=users&action=suspend_user&id=<?= $user['id'] ?>" class="btn-xs red" onclick="return confirm('إيقاف هذا الحساب؟')"><i class="fas fa-ban"></i> إيقاف</a>
                            <?php elseif($user['status'] === 'suspended'): ?>
                            <a href="admin.php?section=users&action=activate_user&id=<?= $user['id'] ?>" class="btn-xs green" onclick="return confirm('تنشيط هذا الحساب؟')"><i class="fas fa-check"></i> تنشيط</a>
                            <?php endif; ?>
                            <a href="admin.php?section=users&action=delete_user&id=<?= $user['id'] ?>" class="btn-xs red" onclick="return confirm('حذف هذا المستخدم نهائياً؟')"><i class="fas fa-trash"></i></a>
                            <?php if($user['user_type'] !== 'admin'): ?>
                            <a href="admin.php?section=users&action=toggle_featured&id=<?= $user['id'] ?>" class="btn-xs <?= $user['is_featured'] ? 'yellow' : 'cyan' ?>" onclick="return confirm('تغيير حالة التمييز؟')">
                                <?= $user['is_featured'] ? '⭐' : '☆' ?>
                            </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- ============================================================ -->
        <!-- 3. توثيق المقاولين -->
        <!-- ============================================================ -->
        <?php elseif ($section === 'verification'): ?>
        <h2 class="section-title">📋 توثيق المقاولين</h2>
        <p class="section-subtitle">إدارة طلبات التوثيق وتصنيف المقاولين</p>
        
        <div class="flex-between">
            <div class="filter-links">
                <a href="admin.php?section=verification" class="<?= empty($filter_status) ? 'active' : '' ?>">📋 الكل (<?= $stats['contractors'] ?? 0 ?>)</a>
                <a href="admin.php?section=verification&filter_status=pending" class="<?= $filter_status === 'pending' ? 'active' : '' ?>">⏳ قيد المراجعة (<?= $stats['pending_verification'] ?? 0 ?>)</a>
                <a href="admin.php?section=verification&filter_status=approved" class="<?= $filter_status === 'approved' ? 'active' : '' ?>">✅ موثقين (<?= $stats['verified_contractors'] ?? 0 ?>)</a>
                <a href="admin.php?section=verification&filter_status=rejected" class="<?= $filter_status === 'rejected' ? 'active' : '' ?>">❌ مرفوضين</a>
            </div>
            <div class="search-box">
                <form method="GET" action="" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                    <input type="hidden" name="section" value="verification">
                    <input type="text" name="search" placeholder="🔍 بحث باسم المقاول..." value="<?= clean($search) ?>">
                    <button type="submit" class="btn-search"><i class="fas fa-search"></i> بحث</button>
                    <a href="admin.php?section=verification" class="btn-reset"><i class="fas fa-times"></i> إعادة ضبط</a>
                </form>
            </div>
        </div>
        
        <?php
        $verification_query = "SELECT u.*, 
            (SELECT AVG(rating) FROM reviews WHERE reviewed_id = u.id) as avg_rating, 
            (SELECT COUNT(*) FROM reviews WHERE reviewed_id = u.id) as reviews_count,
            (SELECT COUNT(*) FROM projects WHERE employer_id = u.id) as projects_count,
            (SELECT COUNT(*) FROM bids WHERE contractor_id = u.id) as bids_count
            FROM users u 
            WHERE u.user_type = 'contractor'";
        if (!empty($search)) {
            $verification_query .= " AND (u.name LIKE '%$search%' OR u.email LIKE '%$search%')";
        }
        if ($filter_status === 'pending') {
            $verification_query .= " AND u.verification_status = 'pending'";
        } elseif ($filter_status === 'approved') {
            $verification_query .= " AND u.verification_status = 'approved'";
        } elseif ($filter_status === 'rejected') {
            $verification_query .= " AND u.verification_status = 'rejected'";
        }
        $verification_query .= " ORDER BY CASE WHEN u.verification_status = 'pending' THEN 0 ELSE 1 END, u.created_at DESC";
        $contractors = $pdo->query($verification_query)->fetchAll();
        ?>
        
        <div class="table-wrap">
            <table>
                <thead><tr><th>#</th><th>المقاول</th><th>البريد</th><th>التقييم</th><th>المشاريع</th><th>التصنيف</th><th>مميز</th><th>الحالة</th><th>تاريخ التوثيق</th><th style="min-width:200px;">التحكم</th></tr></thead>
                <tbody>
                    <?php if(empty($contractors)): ?><tr><td colspan="10" class="empty-state">لا يوجد مقاولين</td></tr><?php endif; ?>
                    <?php foreach($contractors as $i => $c): ?>
                    <tr class="<?= $c['verification_status'] === 'pending' ? 'bg-yellow-50' : ($c['verification_status'] === 'approved' ? 'bg-green-50' : '') ?>">
                        <td><?= $i+1 ?></td>
                        <td>
                            <strong><?= clean($c['name']) ?></strong>
                            <?php if($c['is_featured']): ?><span style="font-size:10px;background:#f59e0b;color:white;padding:1px 8px;border-radius:10px;">⭐ مميز</span><?php endif; ?>
                        </td>
                        <td><?= clean($c['email']) ?></td>
                        <td><?php if($c['avg_rating']): ?><?= number_format($c['avg_rating'], 1) ?> ⭐ <span style="font-size:10px;color:#94a3b8;">(<?= $c['reviews_count'] ?? 0 ?>)</span><?php else: ?><span style="color:#94a3b8;">لا يوجد</span><?php endif; ?></td>
                        <td><?= $c['projects_count'] ?? 0 ?> مشروع</td>
                        <td><span class="class-badge <?= $c['contractor_class'] ?>" style="cursor:pointer;" onclick="openClassModal(<?= $c['id'] ?>, '<?= clean(addslashes($c['name'])) ?>', '<?= $c['contractor_class'] ?>')"><?= $c['contractor_class'] ?></span></td>
                        <td><a href="admin.php?section=verification&action=toggle_featured&id=<?= $c['id'] ?>" class="btn-xs <?= $c['is_featured'] ? 'yellow' : 'cyan' ?>" onclick="return confirm('تغيير حالة التمييز؟')"><?= $c['is_featured'] ? '⭐ إلغاء' : '⭐ تمييز' ?></a></td>
                        <td><span class="badge-status <?= $c['verification_status'] ?>"><?= $c['verification_status'] === 'approved' ? '✅ موثق' : ($c['verification_status'] === 'pending' ? '⏳ معلق' : '❌ مرفوض') ?></span>
                            <?php if($c['verification_status'] === 'approved' && $c['verification_expiry']): ?><div style="font-size:9px;color:#94a3b8;">ينتهي: <?= date('Y/m/d', strtotime($c['verification_expiry'])) ?></div><?php endif; ?>
                        </td>
                        <td style="font-size:11px;color:#64748b;"><?= $c['verification_date'] ? date('Y/m/d', strtotime($c['verification_date'])) : '-' ?></td>
                        <td>
                            <div style="display:flex;flex-wrap:wrap;gap:4px;">
                                <?php if($c['verification_status'] === 'pending'): ?>
                                <a href="admin.php?section=verification&action=verify_contractor&id=<?= $c['id'] ?>&status=approved" class="btn-xs green" onclick="return confirm('✅ توثيق هذا المقاول؟')"><i class="fas fa-check"></i> توثيق</a>
                                <a href="admin.php?section=verification&action=verify_contractor&id=<?= $c['id'] ?>&status=rejected" class="btn-xs red" onclick="return confirm('❌ رفض توثيق هذا المقاول؟')"><i class="fas fa-times"></i> رفض</a>
                                <?php endif; ?>
                                <?php if($c['verification_status'] === 'rejected'): ?>
                                <a href="admin.php?section=verification&action=verify_contractor&id=<?= $c['id'] ?>&status=approved" class="btn-xs blue" onclick="return confirm('🔄 إعادة توثيق هذا المقاول؟')"><i class="fas fa-undo"></i> إعادة توثيق</a>
                                <?php endif; ?>
                                <button onclick="openClassModal(<?= $c['id'] ?>, '<?= clean(addslashes($c['name'])) ?>', '<?= $c['contractor_class'] ?>')" class="btn-xs purple"><i class="fas fa-tag"></i> تصنيف</button>
                                <button onclick="openNotesModal(<?= $c['id'] ?>, '<?= clean(addslashes($c['name'])) ?>', '<?= addslashes($c['admin_notes'] ?? '') ?>')" class="btn-xs gray"><i class="fas fa-edit"></i> ملاحظات</button>
                                <?php if($c['verification_status'] === 'approved'): ?>
                                <a href="admin.php?section=verification&action=verify_contractor&id=<?= $c['id'] ?>&status=rejected" class="btn-xs red" onclick="return confirm('⚠️ إلغاء توثيق هذا المقاول؟')"><i class="fas fa-user-slash"></i> إلغاء التوثيق</a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- مودال التصنيف -->
        <div id="classModal" class="modal-overlay">
            <div class="modal-box">
                <h3>🏷️ تغيير تصنيف المقاول</h3>
                <p class="sub" id="classModalName"></p>
                <form method="POST" action="">
                    <input type="hidden" name="update_class" value="1">
                    <input type="hidden" id="classModalId" name="user_id" value="">
                    <div style="margin-bottom:16px;">
                        <label>اختر التصنيف:</label>
                        <select name="contractor_class" style="width:100%;padding:10px 14px;border:2px solid #e2e8f0;border-radius:8px;font-size:14px;">
                            <option value="A">🌟 أ (متقدم)</option>
                            <option value="B">⭐ ب (جيد جداً)</option>
                            <option value="C">⭐ ج (متوسط)</option>
                            <option value="D">⭐ د (مبتدئ)</option>
                        </select>
                    </div>
                    <div class="modal-actions">
                        <button type="submit" class="btn-save" style="flex:1;">💾 حفظ التصنيف</button>
                        <button type="button" class="btn-cancel" onclick="closeModal('classModal')" style="flex:1;">إلغاء</button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- مودال الملاحظات -->
        <div id="notesModal" class="modal-overlay">
            <div class="modal-box">
                <h3>📝 ملاحظات على المقاول</h3>
                <p class="sub" id="notesModalName"></p>
                <form method="POST" action="">
                    <input type="hidden" name="update_notes" value="1">
                    <input type="hidden" id="notesModalId" name="user_id" value="">
                    <div style="margin-bottom:16px;">
                        <label>الملاحظات:</label>
                        <textarea id="notesModalText" name="admin_notes" style="width:100%;padding:10px 14px;border:2px solid #e2e8f0;border-radius:8px;min-height:100px;resize:vertical;"></textarea>
                    </div>
                    <div class="modal-actions">
                        <button type="submit" class="btn-save" style="flex:1;">💾 حفظ الملاحظات</button>
                        <button type="button" class="btn-cancel" onclick="closeModal('notesModal')" style="flex:1;">إلغاء</button>
                    </div>
                </form>
            </div>
        </div>
        
        <script>
        function openClassModal(id, name, currentClass) {
            document.getElementById('classModalId').value = id;
            document.getElementById('classModalName').textContent = 'المقاول: ' + name;
            document.querySelector('#classModal select[name="contractor_class"]').value = currentClass || 'C';
            document.getElementById('classModal').classList.add('open');
        }
        function openNotesModal(id, name, notes) {
            document.getElementById('notesModalId').value = id;
            document.getElementById('notesModalName').textContent = 'المقاول: ' + name;
            document.getElementById('notesModalText').value = notes || '';
            document.getElementById('notesModal').classList.add('open');
        }
        function closeModal(id) {
            document.getElementById(id).classList.remove('open');
        }
        document.querySelectorAll('.modal-overlay').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.classList.remove('open');
                }
            });
        });
        </script>
        
        <!-- ============================================================ -->
        <!-- 4. توثيق أصحاب العمل -->
        <!-- ============================================================ -->
        <?php elseif ($section === 'employer_verification'): ?>
        <h2 class="section-title">📋 توثيق أصحاب العمل</h2>
        <p class="section-subtitle">إدارة طلبات توثيق أصحاب العمل</p>
        
        <div class="flex-between">
            <div class="filter-links">
                <a href="admin.php?section=employer_verification" class="<?= empty($filter_status) ? 'active' : '' ?>">📋 الكل (<?= $stats['employers'] ?? 0 ?>)</a>
                <a href="admin.php?section=employer_verification&filter_status=pending" class="<?= $filter_status === 'pending' ? 'active' : '' ?>">⏳ قيد المراجعة (<?= $stats['pending_employer_verification'] ?? 0 ?>)</a>
                <a href="admin.php?section=employer_verification&filter_status=approved" class="<?= $filter_status === 'approved' ? 'active' : '' ?>">✅ موثقين (<?= $stats['verified_employers'] ?? 0 ?>)</a>
                <a href="admin.php?section=employer_verification&filter_status=rejected" class="<?= $filter_status === 'rejected' ? 'active' : '' ?>">❌ مرفوضين</a>
            </div>
            <div class="search-box">
                <form method="GET" action="" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                    <input type="hidden" name="section" value="employer_verification">
                    <input type="text" name="search" placeholder="🔍 بحث باسم صاحب العمل..." value="<?= clean($search) ?>">
                    <button type="submit" class="btn-search"><i class="fas fa-search"></i> بحث</button>
                    <a href="admin.php?section=employer_verification" class="btn-reset"><i class="fas fa-times"></i> إعادة ضبط</a>
                </form>
            </div>
        </div>
        
        <?php
        $employer_query = "SELECT u.*, 
            (SELECT COUNT(*) FROM projects WHERE employer_id = u.id) as projects_count,
            (SELECT COUNT(*) FROM projects WHERE employer_id = u.id AND status = 'open') as open_projects_count,
            (SELECT COUNT(*) FROM bids WHERE project_id IN (SELECT id FROM projects WHERE employer_id = u.id)) as bids_on_projects
            FROM users u WHERE u.user_type = 'employer'";
        if (!empty($search)) {
            $employer_query .= " AND (u.name LIKE '%$search%' OR u.email LIKE '%$search%')";
        }
        if ($filter_status === 'pending') {
            $employer_query .= " AND u.employer_verification_status = 'pending'";
        } elseif ($filter_status === 'approved') {
            $employer_query .= " AND u.employer_verification_status = 'approved'";
        } elseif ($filter_status === 'rejected') {
            $employer_query .= " AND u.employer_verification_status = 'rejected'";
        }
        $employer_query .= " ORDER BY CASE WHEN u.employer_verification_status = 'pending' THEN 0 ELSE 1 END, u.created_at DESC";
        $employers = $pdo->query($employer_query)->fetchAll();
        ?>
        
        <div class="table-wrap">
            <table>
                <thead><tr><th>#</th><th>صاحب العمل</th><th>البريد</th><th>المشاريع</th><th>عطاءات</th><th>مميز</th><th>حالة التوثيق</th><th>تاريخ التوثيق</th><th style="min-width:180px;">التحكم</th></tr></thead>
                <tbody>
                    <?php if(empty($employers)): ?><tr><td colspan="9" class="empty-state">لا يوجد أصحاب عمل</td></tr><?php endif; ?>
                    <?php foreach($employers as $i => $e): ?>
                    <tr class="<?= $e['employer_verification_status'] === 'pending' ? 'bg-yellow-50' : ($e['employer_verification_status'] === 'approved' ? 'bg-blue-50' : '') ?>">
                        <td><?= $i+1 ?></td>
                        <td><strong><?= clean($e['name']) ?></strong> <?php if($e['is_featured']): ?><span style="font-size:10px;background:#f59e0b;color:white;padding:1px 8px;border-radius:10px;">⭐ مميز</span><?php endif; ?></td>
                        <td><?= clean($e['email']) ?></td>
                        <td><?= $e['projects_count'] ?? 0 ?> مشروع <?php if(($e['open_projects_count'] ?? 0) > 0): ?><span style="font-size:10px;color:#166534;">(<?= $e['open_projects_count'] ?> مفتوح)</span><?php endif; ?></td>
                        <td><?= $e['bids_on_projects'] ?? 0 ?></td>
                        <td><a href="admin.php?section=employer_verification&action=toggle_featured&id=<?= $e['id'] ?>" class="btn-xs <?= $e['is_featured'] ? 'yellow' : 'cyan' ?>" onclick="return confirm('تغيير حالة التمييز؟')"><?= $e['is_featured'] ? '⭐ إلغاء' : '⭐ تمييز' ?></a></td>
                        <td>
                            <?php
                            $status_label = $e['employer_verification_status'] ?? 'none';
                            $status_text = ['approved' => '✅ موثق', 'pending' => '⏳ معلق', 'rejected' => '❌ مرفوض', 'none' => '—'][$status_label] ?? $status_label;
                            $status_class = ['approved' => 'approved', 'pending' => 'pending', 'rejected' => 'rejected', 'none' => 'none'][$status_label] ?? 'none';
                            ?>
                            <span class="badge-status <?= $status_class ?>"><?= $status_text ?></span>
                            <?php if($status_label === 'approved' && $e['employer_verification_date']): ?><div style="font-size:9px;color:#94a3b8;">منذ: <?= date('Y/m/d', strtotime($e['employer_verification_date'])) ?></div><?php endif; ?>
                        </td>
                        <td style="font-size:11px;color:#64748b;"><?= $e['employer_verification_date'] ? date('Y/m/d', strtotime($e['employer_verification_date'])) : '-' ?></td>
                        <td>
                            <div style="display:flex;flex-wrap:wrap;gap:4px;">
                                <?php if(($e['employer_verification_status'] ?? 'none') === 'pending'): ?>
                                <a href="admin.php?section=employer_verification&action=verify_employer&id=<?= $e['id'] ?>&status=approved" class="btn-xs green" onclick="return confirm('✅ توثيق هذا صاحب العمل؟')"><i class="fas fa-check"></i> توثيق</a>
                                <a href="admin.php?section=employer_verification&action=verify_employer&id=<?= $e['id'] ?>&status=rejected" class="btn-xs red" onclick="return confirm('❌ رفض توثيق هذا صاحب العمل؟')"><i class="fas fa-times"></i> رفض</a>
                                <?php endif; ?>
                                <?php if(($e['employer_verification_status'] ?? 'none') === 'rejected'): ?>
                                <a href="admin.php?section=employer_verification&action=verify_employer&id=<?= $e['id'] ?>&status=approved" class="btn-xs blue" onclick="return confirm('🔄 إعادة توثيق هذا صاحب العمل؟')"><i class="fas fa-undo"></i> إعادة توثيق</a>
                                <?php endif; ?>
                                <?php if(($e['employer_verification_status'] ?? 'none') === 'approved'): ?>
                                <a href="admin.php?section=employer_verification&action=verify_employer&id=<?= $e['id'] ?>&status=rejected" class="btn-xs red" onclick="return confirm('⚠️ إلغاء توثيق هذا صاحب العمل؟')"><i class="fas fa-user-slash"></i> إلغاء التوثيق</a>
                                <?php endif; ?>
                                <button onclick="openEmployerNotesModal(<?= $e['id'] ?>, '<?= clean(addslashes($e['name'])) ?>', '<?= addslashes($e['admin_notes'] ?? '') ?>')" class="btn-xs gray"><i class="fas fa-edit"></i> ملاحظات</button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- مودال ملاحظات أصحاب العمل -->
        <div id="employerNotesModal" class="modal-overlay">
            <div class="modal-box">
                <h3>📝 ملاحظات على صاحب العمل</h3>
                <p class="sub" id="employerNotesModalName"></p>
                <form method="POST" action="">
                    <input type="hidden" name="update_notes" value="1">
                    <input type="hidden" id="employerNotesModalId" name="user_id" value="">
                    <div style="margin-bottom:16px;">
                        <label>الملاحظات:</label>
                        <textarea id="employerNotesModalText" name="admin_notes" style="width:100%;padding:10px 14px;border:2px solid #e2e8f0;border-radius:8px;min-height:100px;resize:vertical;"></textarea>
                    </div>
                    <div class="modal-actions">
                        <button type="submit" class="btn-save" style="flex:1;">💾 حفظ الملاحظات</button>
                        <button type="button" class="btn-cancel" onclick="closeModal('employerNotesModal')" style="flex:1;">إلغاء</button>
                    </div>
                </form>
            </div>
        </div>
        <script>
        function openEmployerNotesModal(id, name, notes) {
            document.getElementById('employerNotesModalId').value = id;
            document.getElementById('employerNotesModalName').textContent = 'صاحب العمل: ' + name;
            document.getElementById('employerNotesModalText').value = notes || '';
            document.getElementById('employerNotesModal').classList.add('open');
        }
        </script>
        
        <!-- ============================================================ -->
        <!-- 5. إدارة المشاريع -->
        <!-- ============================================================ -->
        <?php elseif ($section === 'projects'): ?>
        <h2 class="section-title">🏗️ إدارة المشاريع</h2>
        <p class="section-subtitle">مراجعة المشاريع والموافقة عليها أو رفضها</p>
        
        <div class="flex-between">
            <div class="filter-links">
                <a href="admin.php?section=projects" class="<?= empty($filter_status) ? 'active' : '' ?>">الكل (<?= $stats['projects'] ?>)</a>
                <a href="admin.php?section=projects&filter_status=pending" class="<?= $filter_status === 'pending' ? 'active' : '' ?>">⏳ معلق (<?= $stats['pending_projects'] ?? 0 ?>)</a>
                <a href="admin.php?section=projects&filter_status=open" class="<?= $filter_status === 'open' ? 'active' : '' ?>">✅ مفتوح</a>
                <a href="admin.php?section=projects&filter_status=in_progress" class="<?= $filter_status === 'in_progress' ? 'active' : '' ?>">🔄 قيد التنفيذ</a>
                <a href="admin.php?section=projects&filter_status=completed" class="<?= $filter_status === 'completed' ? 'active' : '' ?>">✔️ مكتمل</a>
                <a href="admin.php?section=projects&filter_status=cancelled" class="<?= $filter_status === 'cancelled' ? 'active' : '' ?>">❌ ملغي</a>
            </div>
            <div class="search-box">
                <form method="GET" action="" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                    <input type="hidden" name="section" value="projects">
                    <input type="text" name="search" placeholder="🔍 بحث بعنوان المشروع..." value="<?= clean($search) ?>">
                    <button type="submit" class="btn-search"><i class="fas fa-search"></i> بحث</button>
                    <a href="admin.php?section=projects" class="btn-reset"><i class="fas fa-times"></i> إعادة ضبط</a>
                </form>
            </div>
        </div>
        
        <?php
        $projects_query = "SELECT p.*, u.name as employer_name, 
                           (SELECT COUNT(*) FROM bids WHERE project_id = p.id) as bids_count,
                           (SELECT COUNT(*) FROM bids WHERE project_id = p.id AND status = 'accepted') as accepted_bids_count
                           FROM projects p 
                           JOIN users u ON p.employer_id = u.id";
        if (!empty($search)) {
            $projects_query .= " WHERE p.title LIKE '%$search%'";
        }
        if ($filter_status === 'pending') {
            $projects_query .= (strpos($projects_query, 'WHERE') !== false ? ' AND' : ' WHERE') . " p.status = 'pending'";
        } elseif ($filter_status === 'open') {
            $projects_query .= (strpos($projects_query, 'WHERE') !== false ? ' AND' : ' WHERE') . " p.status = 'open'";
        } elseif ($filter_status === 'in_progress') {
            $projects_query .= (strpos($projects_query, 'WHERE') !== false ? ' AND' : ' WHERE') . " p.status = 'in_progress'";
        } elseif ($filter_status === 'completed') {
            $projects_query .= (strpos($projects_query, 'WHERE') !== false ? ' AND' : ' WHERE') . " p.status = 'completed'";
        } elseif ($filter_status === 'cancelled') {
            $projects_query .= (strpos($projects_query, 'WHERE') !== false ? ' AND' : ' WHERE') . " p.status = 'cancelled'";
        }
        $projects_query .= " ORDER BY CASE WHEN p.status = 'pending' THEN 0 ELSE 1 END, p.created_at DESC";
        $all_projects = $pdo->query($projects_query)->fetchAll();
        ?>
        
        <div class="table-wrap">
            <table>
                <thead><tr><th>#</th><th>العنوان</th><th>صاحب العمل</th><th>العطاءات</th><th>المقبول</th><th>الحالة</th><th>إجراءات</th></tr></thead>
                <tbody>
                    <?php if(empty($all_projects)): ?><tr><td colspan="7" class="empty-state">لا توجد مشاريع</td></tr><?php endif; ?>
                    <?php foreach($all_projects as $i => $proj): ?>
                    <tr class="<?= $proj['status'] === 'pending' ? 'bg-yellow-50' : '' ?>">
                        <td><?= $i+1 ?></td>
                        <td><strong><?= clean($proj['title']) ?></strong></td>
                        <td><?= clean($proj['employer_name']) ?></td>
                        <td><?= $proj['bids_count'] ?? 0 ?></td>
                        <td><?= $proj['accepted_bids_count'] ?? 0 ?></td>
                        <td><span class="badge-status <?= $proj['status'] ?>"><?= ['pending'=>'معلّق','open'=>'مفتوح','in_progress'=>'قيد التنفيذ','completed'=>'مكتمل','cancelled'=>'ملغي'][$proj['status']] ?? $proj['status'] ?></span></td>
                        <td>
                            <?php if($proj['status'] === 'pending'): ?>
                            <a href="admin.php?section=projects&action=approve_project&id=<?= $proj['id'] ?>" class="btn-xs green" onclick="return confirm('الموافقة على هذا المشروع؟')"><i class="fas fa-check"></i> موافقة</a>
                            <a href="admin.php?section=projects&action=reject_project&id=<?= $proj['id'] ?>" class="btn-xs red" onclick="return confirm('رفض هذا المشروع؟')"><i class="fas fa-times"></i> رفض</a>
                            <?php endif; ?>
                            <a href="<?= SITE_URL ?>/project_detail.php?id=<?= $proj['id'] ?>" class="btn-xs blue" target="_blank"><i class="fas fa-eye"></i></a>
                            <a href="admin.php?section=projects&action=delete_project&id=<?= $proj['id'] ?>" class="btn-xs red" onclick="return confirm('حذف هذا المشروع نهائياً؟')"><i class="fas fa-trash"></i></a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- ============================================================ -->
        <!-- 6. إدارة العطاءات -->
        <!-- ============================================================ -->
        <?php elseif ($section === 'bids_list'): ?>
        <h2 class="section-title">📋 إدارة العطاءات</h2>
        <p class="section-subtitle">مراجعة وإدارة العطاءات المقدمة من المقاولين</p>
        
        <div class="flex-between">
            <div class="filter-links">
                <a href="admin.php?section=bids_list" class="<?= empty($filter_status) ? 'active' : '' ?>">الكل (<?= $stats['bids'] ?>)</a>
                <a href="admin.php?section=bids_list&filter_status=pending" class="<?= $filter_status === 'pending' ? 'active' : '' ?>">⏳ معلق (<?= $stats['pending_bids'] ?? 0 ?>)</a>
                <a href="admin.php?section=bids_list&filter_status=accepted" class="<?= $filter_status === 'accepted' ? 'active' : '' ?>">✅ مقبول</a>
                <a href="admin.php?section=bids_list&filter_status=rejected" class="<?= $filter_status === 'rejected' ? 'active' : '' ?>">❌ مرفوض</a>
                <a href="admin.php?section=bids_list&filter_status=admin_approved" class="<?= $filter_status === 'admin_approved' ? 'active' : '' ?>">🟦 موافقة إدارية</a>
                <a href="admin.php?section=bids_list&filter_status=pending_employer" class="<?= $filter_status === 'pending_employer' ? 'active' : '' ?>">⏳ بانتظار صاحب العمل</a>
            </div>
            <div class="search-box">
                <form method="GET" action="" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                    <input type="hidden" name="section" value="bids_list">
                    <input type="text" name="search" placeholder="🔍 بحث باسم المشروع أو المقاول..." value="<?= clean($search) ?>">
                    <button type="submit" class="btn-search"><i class="fas fa-search"></i> بحث</button>
                    <a href="admin.php?section=bids_list" class="btn-reset"><i class="fas fa-times"></i> إعادة ضبط</a>
                </form>
            </div>
        </div>
        
        <?php
        $bids_query = "SELECT b.*, p.title as project_title, p.id as project_id, u.name as contractor_name 
                       FROM bids b 
                       JOIN projects p ON b.project_id = p.id 
                       JOIN users u ON b.contractor_id = u.id";
        if (!empty($search)) {
            $bids_query .= " WHERE (p.title LIKE '%$search%' OR u.name LIKE '%$search%')";
        }
        if ($filter_status === 'pending') {
            $bids_query .= (strpos($bids_query, 'WHERE') !== false ? ' AND' : ' WHERE') . " b.status = 'pending'";
        } elseif ($filter_status === 'accepted') {
            $bids_query .= (strpos($bids_query, 'WHERE') !== false ? ' AND' : ' WHERE') . " b.status = 'accepted'";
        } elseif ($filter_status === 'rejected') {
            $bids_query .= (strpos($bids_query, 'WHERE') !== false ? ' AND' : ' WHERE') . " b.status = 'rejected'";
        } elseif ($filter_status === 'admin_approved') {
            $bids_query .= (strpos($bids_query, 'WHERE') !== false ? ' AND' : ' WHERE') . " b.admin_approved = 1 AND b.status = 'pending_employer'";
        } elseif ($filter_status === 'pending_employer') {
            $bids_query .= (strpos($bids_query, 'WHERE') !== false ? ' AND' : ' WHERE') . " b.status = 'pending_employer'";
        }
        $bids_query .= " ORDER BY b.created_at DESC LIMIT 100";
        $all_bids = $pdo->query($bids_query)->fetchAll();
        ?>
        
        <div class="table-wrap">
            <table>
                <thead><tr><th>#</th><th>المشروع</th><th>المقاول</th><th>المبلغ</th><th>المدة</th><th>الحالة</th><th>إجراءات</th></tr></thead>
                <tbody>
                    <?php if(empty($all_bids)): ?><tr><td colspan="7" class="empty-state">لا توجد عطاءات</td></tr><?php endif; ?>
                    <?php foreach($all_bids as $i => $bid): ?>
                    <tr>
                        <td><?= $i+1 ?></td>
                        <td><a href="<?= SITE_URL ?>/project_detail.php?id=<?= $bid['project_id'] ?>"><?= clean($bid['project_title']) ?></a></td>
                        <td><?= clean($bid['contractor_name']) ?></td>
                        <td><strong><?= number_format($bid['amount']) ?> ر.س</strong></td>
                        <td><?= $bid['duration_days'] ? $bid['duration_days'] . ' يوم' : '-' ?></td>
                        <td>
                            <?php if($bid['status'] === 'pending_employer' && $bid['admin_approved'] == 1): ?>
                            <span class="badge-status admin_approved">✅ موافقة إدارية (بانتظار صاحب العمل)</span>
                            <?php elseif($bid['status'] === 'pending'): ?>
                            <span class="badge-status pending">⏳ معلق</span>
                            <?php elseif($bid['status'] === 'accepted'): ?>
                            <span class="badge-status accepted">✅ مقبول</span>
                            <?php elseif($bid['status'] === 'rejected'): ?>
                            <span class="badge-status rejected">❌ مرفوض</span>
                            <?php else: ?>
                            <span class="badge-status"><?= $bid['status'] ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if($bid['status'] === 'pending'): ?>
                            <a href="admin.php?section=bids_list&action=admin_approve_bid&id=<?= $bid['id'] ?>" class="btn-xs blue" onclick="return confirm('الموافقة الإدارية على هذا العطاء؟')"><i class="fas fa-check"></i> موافقة</a>
                            <a href="admin.php?section=bids_list&action=reject_bid&id=<?= $bid['id'] ?>" class="btn-xs red" onclick="return confirm('رفض هذا العطاء؟')"><i class="fas fa-times"></i> رفض</a>
                            <?php endif; ?>
                            <?php if($bid['status'] === 'pending_employer'): ?>
                            <span class="btn-xs gray" style="cursor:default;">⏳ بانتظار صاحب العمل</span>
                            <?php endif; ?>
                            <?php if($bid['status'] === 'accepted' || $bid['status'] === 'rejected'): ?>
                            <a href="admin.php?section=bids_list&action=reset_bid&id=<?= $bid['id'] ?>" class="btn-xs yellow" onclick="return confirm('إعادة العطاء إلى معلق؟')"><i class="fas fa-undo"></i> إعادة</a>
                            <?php endif; ?>
                            <a href="admin.php?section=bids_list&action=delete_bid&id=<?= $bid['id'] ?>" class="btn-xs red" onclick="return confirm('حذف هذا العطاء؟')"><i class="fas fa-trash"></i></a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- ============================================================ -->
        <!-- ✅ 7. موافقة المحلات (جديد) -->
        <!-- ============================================================ -->
        <?php elseif ($section === 'shops_verification'): ?>
        <h2 class="section-title">🏪 موافقة المحلات</h2>
        <p class="section-subtitle">مراجعة المحلات المعلقة والموافقة عليها أو رفضها</p>
        
        <?php
        // جلب المحلات المعلقة
        $pending_shops = $pdo->prepare("
            SELECT s.*, u.name as owner_name, u.email as owner_email,
                   c.name_ar as country_name, ci.name_ar as city_name
            FROM shops s
            JOIN users u ON s.user_id = u.id
            LEFT JOIN countries c ON s.country_id = c.id
            LEFT JOIN cities ci ON s.city_id = ci.id
            WHERE s.status = 'pending'
            ORDER BY s.created_at DESC
        ");
        $pending_shops->execute();
        $pending_shops = $pending_shops->fetchAll();
        ?>
        
        <?php if (empty($pending_shops)): ?>
        <div class="card-bg rounded-xl p-12 text-center border border-gray-200 shadow-sm">
            <div class="text-6xl text-gray-400 mb-4">✅</div>
            <h3 class="text-xl font-bold text-gray-700 mb-2">لا توجد محلات تنتظر الموافقة</h3>
            <p class="text-gray-400">جميع المحلات تمت مراجعتها</p>
        </div>
        <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>اسم المحل</th>
                        <th>المالك</th>
                        <th>البريد الإلكتروني</th>
                        <th>الموقع</th>
                        <th>تاريخ التسجيل</th>
                        <th>الإجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pending_shops as $i => $shop): ?>
                    <tr class="bg-yellow-50">
                        <td><?= $i+1 ?></td>
                        <td><strong><?= clean($shop['shop_name']) ?></strong></td>
                        <td><?= clean($shop['owner_name']) ?></td>
                        <td><?= clean($shop['owner_email']) ?></td>
                        <td><?= clean($shop['city_name'] ?? '') ?>, <?= clean($shop['country_name'] ?? '') ?></td>
                        <td style="font-size:12px;color:#94a3b8;"><?= date('Y/m/d', strtotime($shop['created_at'])) ?></td>
                        <td>
                            <div style="display:flex;flex-wrap:wrap;gap:4px;">
                                <a href="admin.php?section=shops_verification&action=approve_shop&id=<?= $shop['id'] ?>" 
                                   class="btn-xs green" 
                                   onclick="return confirm('✅ الموافقة على هذا المحل؟ سيظهر في قائمة المحلات.')">
                                    <i class="fas fa-check"></i> موافقة
                                </a>
                                <a href="admin.php?section=shops_verification&action=reject_shop&id=<?= $shop['id'] ?>" 
                                   class="btn-xs red" 
                                   onclick="return confirm('❌ رفض هذا المحل؟ سيتم حذفه نهائياً.')">
                                    <i class="fas fa-times"></i> رفض
                                </a>
                                <a href="<?= SITE_URL ?>/preview_shop.php?id=<?= $shop['id'] ?>" class="btn-xs blue" target="_blank">
    <i class="fas fa-eye"></i> معاينة
</a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        
        <!-- ============================================================ -->
        <!-- 8. إدارة المحلات (قائمة المحلات النشطة) -->
        <!-- ============================================================ -->
        <?php elseif ($section === 'shops_list'): ?>
        <h2 class="section-title">🏪 إدارة المحلات</h2>
        <p class="section-subtitle">إدارة محلات مواد البناء المسجلة (النشطة والموقوفة)</p>
        
        <div class="flex-between">
            <div class="filter-links">
                <a href="admin.php?section=shops_list" class="<?= empty($filter_status) ? 'active' : '' ?>">الكل (<?= $stats['shops'] ?>)</a>
                <a href="admin.php?section=shops_list&filter_status=active" class="<?= $filter_status === 'active' ? 'active' : '' ?>">✅ نشط</a>
                <a href="admin.php?section=shops_list&filter_status=suspended" class="<?= $filter_status === 'suspended' ? 'active' : '' ?>">⛔ موقوف</a>
            </div>
            <div class="search-box">
                <form method="GET" action="" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                    <input type="hidden" name="section" value="shops_list">
                    <input type="text" name="search" placeholder="🔍 بحث باسم المحل..." value="<?= clean($search) ?>">
                    <button type="submit" class="btn-search"><i class="fas fa-search"></i> بحث</button>
                    <a href="admin.php?section=shops_list" class="btn-reset"><i class="fas fa-times"></i> إعادة ضبط</a>
                </form>
            </div>
        </div>
        
        <?php
        $shops_query = "SELECT s.*, u.name as owner_name, 
                        (SELECT COUNT(*) FROM products WHERE shop_id = s.id) as products_count 
                        FROM shops s 
                        JOIN users u ON s.user_id = u.id";
        if (!empty($search)) {
            $shops_query .= " WHERE s.shop_name LIKE '%$search%'";
        }
        if ($filter_status === 'active') {
            $shops_query .= (strpos($shops_query, 'WHERE') !== false ? ' AND' : ' WHERE') . " s.status = 'active'";
        } elseif ($filter_status === 'suspended') {
            $shops_query .= (strpos($shops_query, 'WHERE') !== false ? ' AND' : ' WHERE') . " s.status = 'suspended'";
        } else {
            // عرض جميع المحلات باستثناء المعلقة (لأنها في قسم الموافقة)
            $shops_query .= " WHERE s.status IN ('active', 'suspended')";
        }
        $shops_query .= " ORDER BY s.created_at DESC";
        $all_shops = $pdo->query($shops_query)->fetchAll();
        ?>
        
        <div class="table-wrap">
            <table>
                <thead><tr><th>#</th><th>اسم المحل</th><th>المالك</th><th>المنتجات</th><th>الحالة</th><th>إجراءات</th></tr></thead>
                <tbody>
                    <?php if(empty($all_shops)): ?><tr><td colspan="6" class="empty-state">لا توجد محلات</td></tr><?php endif; ?>
                    <?php foreach($all_shops as $i => $shop): ?>
                    <tr>
                        <td><?= $i+1 ?></td>
                        <td><strong><?= clean($shop['shop_name']) ?></strong></td>
                        <td><?= clean($shop['owner_name']) ?></td>
                        <td><?= $shop['products_count'] ?? 0 ?></td>
                        <td><span class="badge-status <?= $shop['status'] ?>"><?= $shop['status'] === 'active' ? 'نشط' : 'موقوف' ?></span></td>
                        <td>
                            <a href="<?= SITE_URL ?>/shop_detail.php?id=<?= $shop['id'] ?>" class="btn-xs blue"><i class="fas fa-eye"></i> عرض</a>
                            <?php if($shop['status'] === 'active'): ?>
                            <a href="admin.php?section=shops_list&action=suspend_shop&id=<?= $shop['id'] ?>" class="btn-xs red" onclick="return confirm('إيقاف هذا المحل؟')"><i class="fas fa-ban"></i> إيقاف</a>
                            <?php elseif($shop['status'] === 'suspended'): ?>
                            <a href="admin.php?section=shops_list&action=activate_shop&id=<?= $shop['id'] ?>" class="btn-xs green" onclick="return confirm('تنشيط هذا المحل؟')"><i class="fas fa-check"></i> تنشيط</a>
                            <?php endif; ?>
                            <a href="admin.php?section=shops_list&action=delete_shop&id=<?= $shop['id'] ?>" class="btn-xs red" onclick="return confirm('حذف هذا المحل نهائياً؟')"><i class="fas fa-trash"></i></a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- ============================================================ -->
        <!-- 9. إدارة المنتجات -->
        <!-- ============================================================ -->
        <?php elseif ($section === 'products'): ?>
        <h2 class="section-title">📦 إدارة المنتجات</h2>
        <p class="section-subtitle">إدارة منتجات محلات مواد البناء</p>
        
        <div class="flex-between">
            <div class="filter-links">
                <a href="admin.php?section=products" class="<?= empty($filter_status) ? 'active' : '' ?>">الكل (<?= $stats['products'] ?>)</a>
                <a href="admin.php?section=products&filter_status=available" class="<?= $filter_status === 'available' ? 'active' : '' ?>">✅ متوفر</a>
                <a href="admin.php?section=products&filter_status=unavailable" class="<?= $filter_status === 'unavailable' ? 'active' : '' ?>">❌ غير متوفر</a>
            </div>
            <div class="search-box">
                <form method="GET" action="" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                    <input type="hidden" name="section" value="products">
                    <input type="text" name="search" placeholder="🔍 بحث باسم المنتج..." value="<?= clean($search) ?>">
                    <button type="submit" class="btn-search"><i class="fas fa-search"></i> بحث</button>
                    <a href="admin.php?section=products" class="btn-reset"><i class="fas fa-times"></i> إعادة ضبط</a>
                </form>
            </div>
        </div>
        
        <?php
        $products_query = "SELECT p.*, s.shop_name FROM products p JOIN shops s ON p.shop_id = s.id";
        if (!empty($search)) {
            $products_query .= " WHERE p.name LIKE '%$search%'";
        }
        if ($filter_status === 'available') {
            $products_query .= (strpos($products_query, 'WHERE') !== false ? ' AND' : ' WHERE') . " p.is_available = 1";
        } elseif ($filter_status === 'unavailable') {
            $products_query .= (strpos($products_query, 'WHERE') !== false ? ' AND' : ' WHERE') . " p.is_available = 0";
        }
        $products_query .= " ORDER BY p.created_at DESC";
        $all_products = $pdo->query($products_query)->fetchAll();
        ?>
        
        <div class="table-wrap">
            <table>
                <thead><tr><th>#</th><th>المنتج</th><th>المحل</th><th>السعر</th><th>متوفر</th><th>إجراءات</th></tr></thead>
                <tbody>
                    <?php if(empty($all_products)): ?><tr><td colspan="6" class="empty-state">لا توجد منتجات</td></tr><?php endif; ?>
                    <?php foreach($all_products as $i => $prod): ?>
                    <tr>
                        <td><?= $i+1 ?></td>
                        <td><strong><?= clean($prod['name']) ?></strong></td>
                        <td><?= clean($prod['shop_name']) ?></td>
                        <td><?= $prod['price'] ? number_format($prod['price']) . ' ر.س' : '-' ?></td>
                        <td><?= $prod['is_available'] ? '✅' : '❌' ?></td>
                        <td>
                            <a href="admin.php?section=products&action=delete_product&id=<?= $prod['id'] ?>" class="btn-xs red" onclick="return confirm('حذف هذا المنتج؟')"><i class="fas fa-trash"></i></a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- ============================================================ -->
        <!-- 10. الإعدادات -->
        <!-- ============================================================ -->
        <?php elseif ($section === 'settings'): ?>
        <h2 class="section-title">⚙️ الإعدادات</h2>
        <p class="section-subtitle">إدارة التصنيفات والدول والمدن</p>
        
        <!-- التصنيفات -->
        <div style="background:white;border-radius:12px;border:1px solid #e2e8f0;padding:20px;margin-bottom:20px;">
            <h3 style="font-size:16px;font-weight:700;color:#0f172a;margin:0 0 16px 0;">📂 إدارة التصنيفات</h3>
            <form method="POST" action="" style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:16px;">
                <input type="hidden" name="add_category" value="1">
                <input type="text" name="category_name" placeholder="اسم التصنيف" style="flex:1;min-width:150px;padding:8px 14px;border:2px solid #e2e8f0;border-radius:8px;font-size:13px;">
                <input type="text" name="category_icon" placeholder="رمز تعبيري (مثال 🏗️)" style="width:120px;padding:8px 14px;border:2px solid #e2e8f0;border-radius:8px;font-size:13px;">
                <button type="submit" class="btn-xs green" style="padding:8px 20px;font-size:13px;">➕ إضافة</button>
            </form>
            <?php
            try {
                $categories = $pdo->query("SELECT * FROM project_categories ORDER BY name_ar")->fetchAll();
            } catch (Exception $e) {
                $categories = [];
            }
            ?>
            <div style="display:flex;flex-wrap:wrap;gap:8px;">
                <?php foreach ($categories as $cat): ?>
                <div style="background:#f1f5f9;padding:6px 14px;border-radius:20px;display:flex;align-items:center;gap:8px;font-size:13px;">
                    <span><?= $cat['icon'] ?? '📁' ?></span>
                    <span><?= clean($cat['name_ar']) ?></span>
                    <form method="POST" action="" style="display:inline;">
                        <input type="hidden" name="delete_category" value="1">
                        <input type="hidden" name="category_id" value="<?= $cat['id'] ?>">
                        <button type="submit" class="btn-xs red" style="font-size:10px;padding:1px 6px;">✕</button>
                    </form>
                </div>
                <?php endforeach; ?>
                <?php if(empty($categories)): ?><span style="color:#94a3b8;font-size:13px;">لا توجد تصنيفات مضافة</span><?php endif; ?>
            </div>
        </div>
        
        <!-- الدول -->
        <div style="background:white;border-radius:12px;border:1px solid #e2e8f0;padding:20px;margin-bottom:20px;">
            <h3 style="font-size:16px;font-weight:700;color:#0f172a;margin:0 0 16px 0;">🌍 إدارة الدول</h3>
            <form method="POST" action="" style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:16px;">
                <input type="hidden" name="add_country" value="1">
                <input type="text" name="country_name" placeholder="اسم الدولة" style="flex:1;min-width:150px;padding:8px 14px;border:2px solid #e2e8f0;border-radius:8px;font-size:13px;">
                <input type="text" name="country_flag" placeholder="علم (مثال 🇸🇦)" style="width:100px;padding:8px 14px;border:2px solid #e2e8f0;border-radius:8px;font-size:13px;">
                <input type="text" name="country_currency" placeholder="عملة (مثال SAR)" style="width:120px;padding:8px 14px;border:2px solid #e2e8f0;border-radius:8px;font-size:13px;">
                <button type="submit" class="btn-xs green" style="padding:8px 20px;font-size:13px;">➕ إضافة</button>
            </form>
            <?php
            try {
                $countries = $pdo->query("SELECT * FROM countries ORDER BY name_ar")->fetchAll();
            } catch (Exception $e) {
                $countries = [];
            }
            ?>
            <div style="display:flex;flex-wrap:wrap;gap:8px;">
                <?php foreach ($countries as $c): ?>
                <div style="background:#f1f5f9;padding:6px 14px;border-radius:20px;display:flex;align-items:center;gap:8px;font-size:13px;">
                    <span><?= $c['flag'] ?? '🏳️' ?></span>
                    <span><?= clean($c['name_ar']) ?></span>
                    <span style="color:#94a3b8;font-size:11px;"><?= clean($c['currency_code'] ?? '') ?></span>
                </div>
                <?php endforeach; ?>
                <?php if(empty($countries)): ?><span style="color:#94a3b8;font-size:13px;">لا توجد دول مضافة</span><?php endif; ?>
            </div>
        </div>
        
        <!-- ============================================================ -->
        <!-- 11. الإشعارات -->
        <!-- ============================================================ -->
        <?php elseif ($section === 'notifications'): ?>
        <h2 class="section-title">🔔 الإشعارات</h2>
        <p class="section-subtitle">إدارة وإرسال الإشعارات للمستخدمين</p>
        
        <!-- إرسال إشعار جماعي -->
        <div style="background:white;border-radius:12px;border:1px solid #e2e8f0;padding:24px;margin-bottom:24px;">
            <h3 style="font-size:16px;font-weight:700;color:#0f172a;margin:0 0 16px 0;">📨 إرسال إشعار جماعي</h3>
            <form method="POST" action="" style="display:flex;flex-direction:column;gap:12px;">
                <input type="hidden" name="send_bulk_notification" value="1">
                <input type="text" name="notification_title" placeholder="عنوان الإشعار" style="padding:10px 14px;border:2px solid #e2e8f0;border-radius:8px;font-size:14px;">
                <textarea name="notification_message" placeholder="نص الإشعار" style="padding:10px 14px;border:2px solid #e2e8f0;border-radius:8px;font-size:14px;min-height:80px;resize:vertical;"></textarea>
                <div style="display:flex;gap:10px;flex-wrap:wrap;">
                    <select name="notification_user_type" style="padding:10px 14px;border:2px solid #e2e8f0;border-radius:8px;font-size:14px;flex:1;min-width:150px;">
                        <option value="all">👥 جميع المستخدمين</option>
                        <option value="employer">👨‍💼 أصحاب عمل</option>
                        <option value="contractor">👷 مقاولين</option>
                        <option value="shop">🏪 محلات</option>
                    </select>
                    <button type="submit" class="btn-xs blue" style="padding:10px 30px;font-size:14px;">📤 إرسال الإشعار</button>
                </div>
            </form>
        </div>
        
        <!-- عرض آخر الإشعارات -->
        <div class="table-wrap">
            <h3 style="padding:16px 20px;margin:0;font-size:16px;font-weight:700;color:#0f172a;border-bottom:1px solid #e2e8f0;">
                📋 آخر الإشعارات المرسلة
            </h3>
            <table>
                <thead><tr><th>#</th><th>المستخدم</th><th>العنوان</th><th>النوع</th><th>التاريخ</th></tr></thead>
                <tbody>
                    <?php
                    try {
                        $stmt = $pdo->prepare("SELECT n.*, u.name as user_name FROM notifications n JOIN users u ON n.user_id = u.id ORDER BY n.created_at DESC LIMIT 30");
                        $stmt->execute();
                        $notifs = $stmt->fetchAll();
                    } catch (Exception $e) {
                        $notifs = [];
                    }
                    ?>
                    <?php if(empty($notifs)): ?>
                        <tr><td colspan="5" class="empty-state">لا توجد إشعارات</td></tr>
                    <?php else: foreach ($notifs as $i => $n): ?>
                        <tr>
                            <td><?= $i+1 ?></td>
                            <td><strong><?= clean($n['user_name']) ?></strong></td>
                            <td><?= clean($n['title']) ?></td>
                            <td><span class="badge-status <?= $n['type'] ?>"><?= $n['type'] ?></span></td>
                            <td style="font-size:11px;color:#94a3b8;"><?= date('Y/m/d H:i', strtotime($n['created_at'])) ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- ============================================================ -->
        <!-- 12. سجل العمليات -->
        <!-- ============================================================ -->
        <?php elseif ($section === 'logs'): ?>
        <h2 class="section-title">📜 سجل العمليات</h2>
        <p class="section-subtitle">سجل جميع العمليات والإجراءات التي قام بها الأدمن</p>
        
        <div class="flex-between">
            <div class="filter-links">
                <a href="admin.php?section=logs" class="<?= empty($filter_status) ? 'active' : '' ?>">الكل</a>
                <a href="admin.php?section=logs&filter_status=approve_user" class="<?= $filter_status === 'approve_user' ? 'active' : '' ?>">👤 تفعيل مستخدم</a>
                <a href="admin.php?section=logs&filter_status=verify_contractor" class="<?= $filter_status === 'verify_contractor' ? 'active' : '' ?>">📋 توثيق مقاول</a>
                <a href="admin.php?section=logs&filter_status=verify_employer" class="<?= $filter_status === 'verify_employer' ? 'active' : '' ?>">👨‍💼 توثيق صاحب عمل</a>
                <a href="admin.php?section=logs&filter_status=approve_project" class="<?= $filter_status === 'approve_project' ? 'active' : '' ?>">🏗️ موافقة مشروع</a>
                <a href="admin.php?section=logs&filter_status=admin_approve_bid" class="<?= $filter_status === 'admin_approve_bid' ? 'active' : '' ?>">📋 موافقة عطاء</a>
                <a href="admin.php?section=logs&filter_status=approve_shop" class="<?= $filter_status === 'approve_shop' ? 'active' : '' ?>">🏪 موافقة محل</a>
            </div>
            <div class="search-box">
                <form method="GET" action="" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                    <input type="hidden" name="section" value="logs">
                    <input type="text" name="search" placeholder="🔍 بحث..." value="<?= clean($search) ?>">
                    <button type="submit" class="btn-search"><i class="fas fa-search"></i> بحث</button>
                    <a href="admin.php?section=logs" class="btn-reset"><i class="fas fa-times"></i> إعادة ضبط</a>
                </form>
            </div>
        </div>
        
        <?php
        $logs_query = "SELECT al.*, u.name as admin_name FROM admin_logs al JOIN users u ON al.admin_id = u.id";
        if (!empty($search)) {
            $logs_query .= " WHERE (u.name LIKE '%$search%' OR al.action LIKE '%$search%' OR al.details LIKE '%$search%')";
        }
        if (!empty($filter_status) && in_array($filter_status, ['approve_user', 'suspend_user', 'activate_user', 'verify_contractor', 'verify_employer', 'toggle_featured', 'approve_project', 'reject_project', 'admin_approve_bid', 'reject_bid', 'reset_bid', 'delete_user', 'delete_project', 'delete_bid', 'delete_shop', 'delete_product', 'suspend_shop', 'activate_shop', 'approve_shop', 'reject_shop'])) {
            $logs_query .= (strpos($logs_query, 'WHERE') !== false ? ' AND' : ' WHERE') . " al.action = '$filter_status'";
        }
        $logs_query .= " ORDER BY al.created_at DESC LIMIT 100";
        $logs = $pdo->query($logs_query)->fetchAll();
        ?>
        
        <div class="table-wrap">
            <table>
                <thead><tr><th>#</th><th>المشرف</th><th>الإجراء</th><th>التفاصيل</th><th>التاريخ</th></tr></thead>
                <tbody>
                    <?php if(empty($logs)): ?><tr><td colspan="5" class="empty-state">لا توجد سجلات بعد</td></tr><?php endif; ?>
                    <?php foreach($logs as $i => $log): ?>
                    <tr>
                        <td><?= $i+1 ?></td>
                        <td><strong><?= clean($log['admin_name']) ?></strong></td>
                        <td><span class="badge-status pending"><?= clean($log['action']) ?></span></td>
                        <td><?= clean($log['details'] ?? '-') ?></td>
                        <td><?= date('Y/m/d H:i', strtotime($log['created_at'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <?php endif; ?>
        
    </main>
</div>

<script>
// إغلاق المودال عند النقر خارجها
document.querySelectorAll('.modal-overlay').forEach(modal => {
    modal.addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.remove('open');
        }
    });
});

// إغلاق المودال عند الضغط على Escape
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-overlay.open').forEach(modal => {
            modal.classList.remove('open');
        });
    }
});

// إزالة رسائل الإشعارات التلقائية
setTimeout(() => {
    document.querySelectorAll('.toast').forEach(toast => {
        setTimeout(() => {
            if (toast.parentElement) toast.style.opacity = '0';
            setTimeout(() => {
                if (toast.parentElement) toast.remove();
            }, 300);
        }, 5000);
    });
}, 1000);
</script>

<?php include 'includes/footer.php'; ?>