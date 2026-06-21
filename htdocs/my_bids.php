<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once 'config.php';

// ============================================
// دالة مساعدة timeAgo (إذا لم تكن موجودة في config)
// ============================================
if (!function_exists('timeAgo')) {
    function timeAgo($datetime) {
        $time = strtotime($datetime);
        $diff = time() - $time;
        
        if ($diff < 60) return 'منذ لحظات';
        if ($diff < 3600) return 'منذ ' . floor($diff / 60) . ' دقيقة';
        if ($diff < 86400) return 'منذ ' . floor($diff / 3600) . ' ساعة';
        if ($diff < 604800) return 'منذ ' . floor($diff / 86400) . ' يوم';
        if ($diff < 2592000) return 'منذ ' . floor($diff / 604800) . ' أسبوع';
        return date('Y-m-d', $time);
    }
}

// ============================================
// دوال مساعدة (إذا لم تكن موجودة)
// ============================================
if (!function_exists('getWorkspaceId')) {
    function getWorkspaceId($project_id, $bid_id) {
        global $pdo;
        try {
            $stmt = $pdo->prepare("SELECT id FROM project_workspace WHERE project_id = ? AND bid_id = ?");
            $stmt->execute([$project_id, $bid_id]);
            $workspace = $stmt->fetch();
            return $workspace ? $workspace['id'] : null;
        } catch (Exception $e) {
            return null;
        }
    }
}

if (!function_exists('hasUserRated')) {
    function hasUserRated($user_id, $bid_id, $type) {
        global $pdo;
        try {
            $stmt = $pdo->prepare("SELECT id FROM reviews WHERE bid_id = ? AND reviewer_id = ? AND type = ?");
            $stmt->execute([$bid_id, $user_id, $type]);
            return $stmt->fetch() ? true : false;
        } catch (Exception $e) {
            return false;
        }
    }
}

// ============================================
// التأكد من تسجيل الدخول
// ============================================
if (!isLoggedIn()) {
    redirect('login.php');
}

$user_id = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'];

// جلب الفلتر من الرابط
$filter = isset($_GET['filter']) ? clean($_GET['filter']) : 'all';

// عنوان الفلتر لصاحب العمل
$filter_title = '';
$filter_description = '';

if ($filter === 'accepted') {
    $filter_title = ' - المشاريع المحالة قيد التنفيذ';
    $filter_description = 'استعرض جميع المشاريع التي تم قبول عطاءاتها نهائياً';
} elseif ($filter === 'pending_employer') {
    $filter_title = ' - في انتظار موافقتك';
    $filter_description = 'استعرض العطاءات التي وافق عليها الأدمن وتنتظر قرارك النهائي';
} elseif ($filter === 'pending_admin') {
    $filter_title = ' - قيد مراجعة الإدارة';
    $filter_description = 'استعرض العطاءات التي تنتظر موافقة الإدارة (لا يمكنك التصرف بها)';
} elseif ($filter === 'rejected') {
    $filter_title = ' - المرفوضة';
    $filter_description = 'استعرض العطاءات التي تم رفضها (من الإدارة أو منك)';
} else {
    $filter_title = ' - العطاءات الواردة';
    $filter_description = 'استعرض العطاءات الواردة على مشاريعك (في انتظار موافقتك والمرفوضة)';
}

$page_title = 'عطاءاتي' . $filter_title;
include 'includes/header.php';

// ============================================
// جلب البيانات حسب نوع المستخدم
// ============================================

$projects = [];
$all_bids = 0;
$pending_employer_count = 0;
$pending_admin_count = 0;
$accepted_count = 0;
$rejected_count = 0;

// ============================================
// 1. لصاحب العمل
// ============================================
if ($user_type === 'employer') {
    try {
        // بناء شرط الفلتر
        $status_filter = '';

        if ($filter === 'accepted') {
            $status_filter = "AND b.status = 'accepted'";
        } elseif ($filter === 'pending_employer') {
            $status_filter = "AND b.status = 'pending_employer'";
        } elseif ($filter === 'pending_admin') {
            $status_filter = "AND b.status = 'pending'";
        } elseif ($filter === 'rejected') {
            $status_filter = "AND b.status = 'rejected'";
        } else {
            $status_filter = "AND b.status IN ('pending_employer', 'rejected', 'pending')";
        }

        // جلب المشاريع والعطاءات
        $sql = "
            SELECT 
                p.id as project_id,
                p.title as project_title,
                p.status as project_status,
                p.created_at as project_created,
                b.id as bid_id,
                b.amount as bid_amount,
                b.proposal as bid_proposal,
                b.status as bid_status,
                b.created_at as bid_created,
                u.id as contractor_id,
                u.name as contractor_name,
                u.avatar as contractor_avatar,
                u.email as contractor_email,
                (SELECT COUNT(*) FROM bids WHERE project_id = p.id) as total_bids,
                (SELECT COUNT(*) FROM bids WHERE project_id = p.id AND status = 'accepted') as accepted_bids_count
            FROM projects p
            LEFT JOIN bids b ON p.id = b.project_id
            LEFT JOIN users u ON b.contractor_id = u.id
            WHERE p.employer_id = ?
            {$status_filter}
            ORDER BY p.created_at DESC, b.created_at DESC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$user_id]);
        $results = $stmt->fetchAll();

        // تنظيم البيانات
        foreach ($results as $row) {
            if (!isset($projects[$row['project_id']])) {
                $projects[$row['project_id']] = [
                    'id' => $row['project_id'],
                    'title' => $row['project_title'],
                    'status' => $row['project_status'],
                    'created_at' => $row['project_created'],
                    'total_bids' => $row['total_bids'],
                    'accepted_bids_count' => $row['accepted_bids_count'],
                    'bids' => []
                ];
            }
            if ($row['bid_id']) {
                $projects[$row['project_id']]['bids'][] = [
                    'id' => $row['bid_id'],
                    'amount' => $row['bid_amount'],
                    'proposal' => $row['bid_proposal'],
                    'status' => $row['bid_status'],
                    'created_at' => $row['bid_created'],
                    'contractor_id' => $row['contractor_id'],
                    'contractor_name' => $row['contractor_name'],
                    'contractor_email' => $row['contractor_email'],
                    'contractor_avatar' => $row['contractor_avatar']
                ];
            }
        }

        // حساب الإحصائيات
        $stmt_accepted = $pdo->prepare("SELECT COUNT(*) FROM bids b JOIN projects p ON b.project_id = p.id WHERE p.employer_id = ? AND b.status = 'accepted'");
        $stmt_accepted->execute([$user_id]);
        $accepted_count = $stmt_accepted->fetchColumn();

        $stmt_pe = $pdo->prepare("SELECT COUNT(*) FROM bids b JOIN projects p ON b.project_id = p.id WHERE p.employer_id = ? AND b.status = 'pending_employer'");
        $stmt_pe->execute([$user_id]);
        $pending_employer_count = $stmt_pe->fetchColumn();

        $stmt_pa = $pdo->prepare("SELECT COUNT(*) FROM bids b JOIN projects p ON b.project_id = p.id WHERE p.employer_id = ? AND b.status = 'pending'");
        $stmt_pa->execute([$user_id]);
        $pending_admin_count = $stmt_pa->fetchColumn();

        $stmt_rej = $pdo->prepare("SELECT COUNT(*) FROM bids b JOIN projects p ON b.project_id = p.id WHERE p.employer_id = ? AND b.status = 'rejected'");
        $stmt_rej->execute([$user_id]);
        $rejected_count = $stmt_rej->fetchColumn();

        $all_bids = $pending_employer_count + $pending_admin_count + $accepted_count + $rejected_count;

    } catch (Exception $e) {
        $projects = [];
    }
}

// ============================================
// 2. للمقاول
// ============================================
$my_bids = [];
$total_bids = 0;
$accepted_bids_count = 0;
$pending_bids_count = 0;
$rejected_bids_count = 0;

if ($user_type === 'contractor') {
    try {
        // إعداد شرط الفلتر للمقاول
        $status_condition = '';
        switch ($filter) {
            case 'accepted':
                $status_condition = "AND b.status = 'accepted'";
                break;
            case 'rejected':
                $status_condition = "AND b.status = 'rejected'";
                break;
            case 'pending':
                $status_condition = "AND b.status IN ('pending', 'pending_employer')";
                break;
            default:
                $status_condition = '';
        }

        $sql = "
            SELECT 
                b.*,
                p.id as project_id,
                p.title as project_title,
                p.status as project_status,
                p.budget_min,
                p.budget_max,
                u.id as employer_id,
                u.name as employer_name,
                u.avatar as employer_avatar,
                (SELECT COUNT(*) FROM bids WHERE project_id = p.id) as total_bids
            FROM bids b
            JOIN projects p ON b.project_id = p.id
            JOIN users u ON p.employer_id = u.id
            WHERE b.contractor_id = ?
            {$status_condition}
            ORDER BY b.created_at DESC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$user_id]);
        $my_bids = $stmt->fetchAll();

        // حساب الإحصائيات للمقاول
        $total_bids = count($my_bids);
        
        $stmt_counts = $pdo->prepare("
            SELECT 
                SUM(CASE WHEN status = 'accepted' THEN 1 ELSE 0 END) as accepted,
                SUM(CASE WHEN status IN ('pending', 'pending_employer') THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
            FROM bids WHERE contractor_id = ?
        ");
        $stmt_counts->execute([$user_id]);
        $counts = $stmt_counts->fetch();
        $accepted_bids_count = $counts['accepted'] ?? 0;
        $pending_bids_count = $counts['pending'] ?? 0;
        $rejected_bids_count = $counts['rejected'] ?? 0;

    } catch (Exception $e) {
        $my_bids = [];
    }
}

// عرض رسائل الجلسة
$success_msg = isset($_SESSION['success']) ? $_SESSION['success'] : '';
$error_msg = isset($_SESSION['error']) ? $_SESSION['error'] : '';
unset($_SESSION['success'], $_SESSION['error']);
?>

<style>
    /* ===== RESET & OVERRIDE ===== */
    body, html {
        min-height: 100vh !important;
        margin: 0 !important;
        padding: 0 !important;
        background: #254163 !important;
        position: relative !important;
        overflow-x: hidden !important;
    }
    
    .bg-gradient-to-b {
        background: transparent !important;
        background-image: none !important;
        padding-top: 80px !important;
        padding-bottom: 80px !important;
    }
    
    .min-h-screen {
        min-height: 100vh !important;
    }
    
    /* ===== ترويسة الصفحة ===== */
    .page-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 30px !important;
        padding-top: 20px !important;
        flex-wrap: wrap;
        gap: 15px;
    }
    
    .page-title h1 {
        font-size: 28px !important;
        font-weight: 900 !important;
        color: #ffffff !important;
        text-shadow: 0 2px 10px rgba(0,0,0,0.3);
        margin-bottom: 4px !important;
    }
    .page-title p {
        font-size: 15px !important;
        color: rgba(255,255,255,0.7) !important;
    }
    
    /* ===== فلتر الأزرار ===== */
    .filter-buttons {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        margin-bottom: 25px;
    }
    
    .filter-btn {
        padding: 8px 20px !important;
        border-radius: 10px !important;
        font-weight: 600 !important;
        font-size: 14px !important;
        transition: all 0.3s ease !important;
        border: 2px solid rgba(255,255,255,0.15) !important;
        background: rgba(255,255,255,0.05) !important;
        color: rgba(255,255,255,0.7) !important;
        text-decoration: none !important;
        display: inline-flex !important;
        align-items: center !important;
        gap: 6px !important;
        cursor: pointer !important;
    }
    
    .filter-btn:hover {
        background: rgba(255,255,255,0.12) !important;
        border-color: rgba(255,255,255,0.25) !important;
        color: white !important;
        transform: translateY(-2px) !important;
    }
    
    .filter-btn.active {
        background: rgba(255,255,255,0.15) !important;
        border-color: rgba(255,255,255,0.3) !important;
        color: white !important;
        box-shadow: 0 4px 15px rgba(0,0,0,0.2) !important;
    }
    
    .filter-btn.all.active {
        border-color: #60a5fa !important;
        background: rgba(96, 165, 250, 0.15) !important;
    }
    
    .filter-btn.accepted.active {
        border-color: #34d399 !important;
        background: rgba(52, 211, 153, 0.15) !important;
        color: #34d399 !important;
    }
    
    .filter-btn.rejected.active {
        border-color: #f87171 !important;
        background: rgba(248, 113, 113, 0.15) !important;
        color: #f87171 !important;
    }
    
    .filter-btn.pending_employer.active {
        border-color: #fbbf24 !important;
        background: rgba(251, 191, 36, 0.15) !important;
        color: #fbbf24 !important;
    }
    
    .filter-btn.pending_admin.active {
        border-color: #a78bfa !important;
        background: rgba(167, 139, 250, 0.15) !important;
        color: #a78bfa !important;
    }
    
    .filter-count {
        background: rgba(255,255,255,0.1) !important;
        padding: 1px 8px !important;
        border-radius: 6px !important;
        font-size: 11px !important;
    }
    
    /* ===== الكروت ===== */
    .card-bg {
        background: rgba(255,255,255,0.10) !important;
        backdrop-filter: blur(16px) !important;
        -webkit-backdrop-filter: blur(16px) !important;
        border: 1px solid rgba(255,255,255,0.08) !important;
        border-radius: 16px !important;
        overflow: hidden !important;
    }
    .card-bg:hover {
        background: rgba(255,255,255,0.15) !important;
        border-color: rgba(255,255,255,0.15) !important;
    }
    
    .card-accepted {
        background: rgba(16, 185, 129, 0.15) !important;
        backdrop-filter: blur(16px) !important;
        -webkit-backdrop-filter: blur(16px) !important;
        border: 1px solid rgba(16, 185, 129, 0.25) !important;
    }
    .card-accepted:hover {
        background: rgba(16, 185, 129, 0.22) !important;
    }
    
    .card-rejected {
        background: rgba(239, 68, 68, 0.15) !important;
        backdrop-filter: blur(16px) !important;
        -webkit-backdrop-filter: blur(16px) !important;
        border: 1px solid rgba(239, 68, 68, 0.25) !important;
    }
    .card-rejected:hover {
        background: rgba(239, 68, 68, 0.22) !important;
    }
    
    .card-pending_employer {
        background: rgba(245, 158, 11, 0.12) !important;
        backdrop-filter: blur(16px) !important;
        -webkit-backdrop-filter: blur(16px) !important;
        border: 1px solid rgba(245, 158, 11, 0.2) !important;
    }
    .card-pending_employer:hover {
        background: rgba(245, 158, 11, 0.18) !important;
    }
    
    .card-pending_admin {
        background: rgba(139, 92, 246, 0.12) !important;
        backdrop-filter: blur(16px) !important;
        -webkit-backdrop-filter: blur(16px) !important;
        border: 1px solid rgba(139, 92, 246, 0.2) !important;
    }
    .card-pending_admin:hover {
        background: rgba(139, 92, 246, 0.18) !important;
    }
    
    /* ===== ألوان الحالات ===== */
    .status-open { background: rgba(34, 197, 94, 0.15) !important; color: #86efac !important; }
    .status-in_progress { background: rgba(245, 158, 11, 0.15) !important; color: #fcd34d !important; }
    .status-completed { background: rgba(59, 130, 246, 0.15) !important; color: #93bbfc !important; }
    .status-cancelled { background: rgba(239, 68, 68, 0.15) !important; color: #fca5a5 !important; }
    
    .bid-status-pending { background: rgba(139, 92, 246, 0.15) !important; color: #a78bfa !important; }
    .bid-status-pending_employer { background: rgba(245, 158, 11, 0.15) !important; color: #fcd34d !important; }
    .bid-status-accepted { background: rgba(34, 197, 94, 0.15) !important; color: #86efac !important; }
    .bid-status-rejected { background: rgba(239, 68, 68, 0.15) !important; color: #fca5a5 !important; }
    
    /* ===== ألوان النصوص ===== */
    .text-gray-800 { color: #ffffff !important; }
    .text-gray-700 { color: rgba(255,255,255,0.8) !important; }
    .text-gray-600 { color: rgba(255,255,255,0.7) !important; }
    .text-gray-500 { color: rgba(255,255,255,0.6) !important; }
    .text-gray-400 { color: rgba(255,255,255,0.4) !important; }
    .text-emerald { color: #86efac !important; }
    .text-red { color: #fca5a5 !important; }
    .text-amber { color: #fcd34d !important; }
    .text-purple { color: #a78bfa !important; }
    
    .border-gray-200 { border-color: rgba(255,255,255,0.08) !important; }
    .border-gray-100 { border-color: rgba(255,255,255,0.06) !important; }
    
    .bg-gray-50 { background: rgba(255,255,255,0.05) !important; }
    .bg-blue-50 { background: rgba(59, 130, 246, 0.08) !important; }
    .bg-purple-50 { background: rgba(139, 92, 246, 0.08) !important; }
    .bg-emerald-50 { background: rgba(16, 185, 129, 0.08) !important; }
    .bg-red-50 { background: rgba(239, 68, 68, 0.08) !important; }
    .bg-amber-50 { background: rgba(245, 158, 11, 0.08) !important; }
    
    .shadow-sm { box-shadow: 0 4px 15px rgba(0,0,0,0.2) !important; }
    .shadow-emerald { box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3) !important; }
    .shadow-red { box-shadow: 0 4px 15px rgba(239, 68, 68, 0.3) !important; }
    .shadow-blue { box-shadow: 0 4px 15px rgba(59, 130, 246, 0.3) !important; }
    .shadow-amber { box-shadow: 0 4px 15px rgba(245, 158, 11, 0.3) !important; }
    .shadow-purple { box-shadow: 0 4px 15px rgba(139, 92, 246, 0.3) !important; }
    .shadow-indigo { box-shadow: 0 4px 15px rgba(99, 102, 241, 0.3) !important; }
    
    /* ===== الأزرار ===== */
    .btn-emerald { background: linear-gradient(135deg, #10b981, #059669) !important; color: white !important; border: none !important; cursor: pointer !important; }
    .btn-emerald:hover { background: linear-gradient(135deg, #059669, #047857) !important; transform: translateY(-2px) !important; }
    .btn-red { background: linear-gradient(135deg, #ef4444, #dc2626) !important; color: white !important; border: none !important; cursor: pointer !important; }
    .btn-red:hover { background: linear-gradient(135deg, #dc2626, #b91c1c) !important; transform: translateY(-2px) !important; }
    .btn-blue { background: linear-gradient(135deg, #3b82f6, #2563eb) !important; color: white !important; border: none !important; cursor: pointer !important; }
    .btn-blue:hover { background: linear-gradient(135deg, #2563eb, #1d4ed8) !important; transform: translateY(-2px) !important; }
    .btn-amber { background: linear-gradient(135deg, #f59e0b, #d97706) !important; color: white !important; border: none !important; cursor: pointer !important; }
    .btn-amber:hover { background: linear-gradient(135deg, #d97706, #b45309) !important; transform: translateY(-2px) !important; }
    .btn-purple { background: linear-gradient(135deg, #8b5cf6, #7c3aed) !important; color: white !important; border: none !important; cursor: pointer !important; }
    .btn-purple:hover { background: linear-gradient(135deg, #7c3aed, #6d28d9) !important; transform: translateY(-2px) !important; }
    .btn-indigo { background: linear-gradient(135deg, #6366f1, #4f46e5) !important; color: white !important; border: none !important; cursor: pointer !important; }
    .btn-indigo:hover { background: linear-gradient(135deg, #4f46e5, #4338ca) !important; transform: translateY(-2px) !important; }
    .btn-workspace { background: linear-gradient(135deg, #8b5cf6, #7c3aed) !important; color: white !important; border: none !important; cursor: pointer !important; }
    .btn-workspace:hover { background: linear-gradient(135deg, #7c3aed, #6d28d9) !important; transform: translateY(-2px) !important; }
    .btn-upload { background: linear-gradient(135deg, #f59e0b, #d97706) !important; color: white !important; border: none !important; cursor: pointer !important; }
    .btn-upload:hover { background: linear-gradient(135deg, #d97706, #b45309) !important; transform: translateY(-2px) !important; }
    .btn-complete { background: linear-gradient(135deg, #22c55e, #16a34a) !important; color: white !important; border: none !important; cursor: pointer !important; }
    .btn-complete:hover { background: linear-gradient(135deg, #16a34a, #15803d) !important; transform: translateY(-2px) !important; }
    .btn-rate { background: linear-gradient(135deg, #ec4899, #db2777) !important; color: white !important; border: none !important; cursor: pointer !important; }
    .btn-rate:hover { background: linear-gradient(135deg, #db2777, #be185d) !important; transform: translateY(-2px) !important; }
    .btn-rated { background: rgba(148, 163, 184, 0.2) !important; color: #94a3b8 !important; border: 1px solid rgba(148, 163, 184, 0.2) !important; cursor: default !important; }
    .btn-rated:hover { transform: none !important; box-shadow: none !important; }
    
    /* ===== أيقونات ===== */
    .fa, .fas, .far, .fal, .fab {
        color: rgba(255,255,255,0.5) !important;
    }
    .fa-coins, .fa-check-circle, .fa-check, .fa-check-double {
        color: #86efac !important;
    }
    .fa-times, .fa-times-circle {
        color: #fca5a5 !important;
    }
    .fa-gavel, .fa-calendar, .fa-clock, .fa-eye {
        color: rgba(255,255,255,0.3) !important;
    }
    .fa-star { color: #fcd34d !important; }
    .fa-upload { color: #a78bfa !important; }
    .fa-door-open { color: #818cf8 !important; }
    .fa-comments { color: #60a5fa !important; }
    .fa-trash { color: #fca5a5 !important; }
    .fa-paperclip { color: rgba(255,255,255,0.3) !important; }
    .fa-plus { color: #ffffff !important; }
    .fa-arrow-left { color: rgba(255,255,255,0.5) !important; }
    
    /* ===== المشروع الرئيسي ===== */
    .project-main-card {
        background: rgba(255,255,255,0.06) !important;
        backdrop-filter: blur(16px) !important;
        -webkit-backdrop-filter: blur(16px) !important;
        border: 1px solid rgba(255,255,255,0.08) !important;
        border-radius: 16px !important;
        overflow: hidden !important;
        margin-bottom: 20px !important;
    }
    
    .project-main-card .project-header {
        background: rgba(255,255,255,0.05) !important;
        padding: 16px 20px !important;
        border-bottom: 1px solid rgba(255,255,255,0.06) !important;
        display: flex !important;
        align-items: center !important;
        justify-content: space-between !important;
        flex-wrap: wrap !important;
        gap: 10px !important;
    }
    
    .project-main-card .project-header h2 {
        font-size: 18px !important;
        font-weight: 800 !important;
        color: white !important;
        margin: 0 !important;
    }
    
    .project-main-card .project-header h2 a {
        color: white !important;
        text-decoration: none !important;
        transition: color 0.3s ease !important;
    }
    
    .project-main-card .project-header h2 a:hover {
        color: #60a5fa !important;
    }
    
    .project-main-card .project-header .project-meta {
        display: flex !important;
        align-items: center !important;
        gap: 15px !important;
        font-size: 13px !important;
        color: rgba(255,255,255,0.6) !important;
    }
    
    .project-main-card .project-bids {
        padding: 12px 20px 20px !important;
    }
    
    .bid-item-in-project {
        background: rgba(255,255,255,0.05) !important;
        border-radius: 12px !important;
        padding: 14px 16px !important;
        margin-bottom: 10px !important;
        border: 1px solid rgba(255,255,255,0.06) !important;
        transition: all 0.3s ease !important;
        cursor: pointer;
    }
    
    .bid-item-in-project:last-child {
        margin-bottom: 0 !important;
    }
    
    .bid-item-in-project:hover {
        background: rgba(255,255,255,0.08) !important;
        border-color: rgba(255,255,255,0.12) !important;
    }
    
    .bid-item-in-project.card-accepted {
        border-color: rgba(16, 185, 129, 0.2) !important;
    }
    
    .bid-item-in-project.card-rejected {
        border-color: rgba(239, 68, 68, 0.2) !important;
    }
    
    .bid-item-in-project.card-pending_employer {
        border-color: rgba(245, 158, 11, 0.15) !important;
    }
    
    .bid-item-in-project.card-pending_admin {
        border-color: rgba(139, 92, 246, 0.15) !important;
    }
    
    .bid-item-in-project .bid-row {
        display: flex !important;
        align-items: center !important;
        justify-content: space-between !important;
        flex-wrap: wrap !important;
        gap: 10px !important;
    }
    
    .bid-item-in-project .contractor-info {
        display: flex !important;
        align-items: center !important;
        gap: 10px !important;
    }
    
    .bid-item-in-project .contractor-avatar-sm {
        width: 32px !important;
        height: 32px !important;
        border-radius: 50% !important;
        overflow: hidden !important;
        background: linear-gradient(135deg, #3b82f6, #6366f1) !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        color: white !important;
        font-weight: 700 !important;
        font-size: 12px !important;
        flex-shrink: 0 !important;
    }
    
    .bid-item-in-project .contractor-avatar-sm img {
        width: 100% !important;
        height: 100% !important;
        object-fit: cover !important;
    }
    
    .bid-item-in-project .bid-details {
        display: flex !important;
        align-items: center !important;
        gap: 15px !important;
        flex-wrap: wrap !important;
    }
    
    .bid-item-in-project .bid-amount-sm {
        font-weight: 700 !important;
        font-size: 15px !important;
        color: #86efac !important;
    }
    
    .bid-item-in-project .bid-time {
        font-size: 11px !important;
        color: rgba(255,255,255,0.4) !important;
    }
    
    .bid-item-in-project .bid-proposal-sm {
        font-size: 13px !important;
        color: rgba(255,255,255,0.7) !important;
        margin-top: 8px !important;
        padding: 8px 12px !important;
        background: rgba(255,255,255,0.03) !important;
        border-radius: 8px !important;
        border-right: 3px solid rgba(255,255,255,0.1) !important;
    }
    
    .bid-item-in-project .bid-actions {
        display: flex !important;
        align-items: center !important;
        gap: 8px !important;
        flex-wrap: wrap !important;
        margin-top: 10px !important;
        padding-top: 10px !important;
        border-top: 1px solid rgba(255,255,255,0.06) !important;
    }
    
    .bid-item-in-project .bid-actions .btn-sm {
        padding: 5px 12px !important;
        font-size: 12px !important;
        border-radius: 8px !important;
        font-weight: 600 !important;
        transition: all 0.3s ease !important;
        text-decoration: none !important;
        display: inline-flex !important;
        align-items: center !important;
        gap: 4px !important;
        border: none !important;
        cursor: pointer !important;
    }
    
    /* ===== تنسيق الفوتر ===== */
    footer {
        margin-top: 40px !important;
        padding: 20px 0 !important;
        position: relative !important;
        z-index: 10 !important;
    }
    
    /* ===== عدد العطاءات ===== */
    .bids-count-badge {
        background: rgba(255,255,255,0.08) !important;
        padding: 2px 12px !important;
        border-radius: 20px !important;
        font-size: 12px !important;
        color: rgba(255,255,255,0.6) !important;
    }
    
    /* ===== رسائل ===== */
    .alert-success-custom {
        background: rgba(16, 185, 129, 0.2) !important;
        border: 1px solid rgba(16, 185, 129, 0.3) !important;
        color: #86efac !important;
        padding: 12px 20px !important;
        border-radius: 12px !important;
        margin-bottom: 20px !important;
        display: flex !important;
        align-items: center !important;
        gap: 12px !important;
        backdrop-filter: blur(8px) !important;
    }
    
    .alert-success-custom i {
        color: #86efac !important;
        font-size: 20px !important;
    }
    
    .alert-error-custom {
        background: rgba(239, 68, 68, 0.2) !important;
        border: 1px solid rgba(239, 68, 68, 0.3) !important;
        color: #fca5a5 !important;
        padding: 12px 20px !important;
        border-radius: 12px !important;
        margin-bottom: 20px !important;
        display: flex !important;
        align-items: center !important;
        gap: 12px !important;
        backdrop-filter: blur(8px) !important;
    }
    
    .alert-error-custom i {
        color: #fca5a5 !important;
        font-size: 20px !important;
    }
    
    /* ===== التجاوب ===== */
    @media (max-width: 640px) {
        .page-title h1 {
            font-size: 20px !important;
        }
        .page-title p {
            font-size: 13px !important;
        }
        .filter-btn {
            font-size: 12px !important;
            padding: 6px 14px !important;
        }
        .project-main-card .project-header {
            padding: 12px 16px !important;
        }
        .project-main-card .project-header h2 {
            font-size: 15px !important;
        }
        .project-main-card .project-bids {
            padding: 10px 16px 16px !important;
        }
        .bid-item-in-project {
            padding: 12px !important;
        }
        .bid-item-in-project .bid-row {
            flex-direction: column !important;
            align-items: stretch !important;
        }
        .bid-item-in-project .bid-details {
            justify-content: space-between !important;
        }
        .bid-item-in-project .bid-actions .btn-sm {
            font-size: 10px !important;
            padding: 4px 8px !important;
        }
    }
</style>

<div class="bg-gradient-to-b from-blue-50 to-white min-h-screen py-8">
    <div class="max-w-7xl mx-auto px-4">
        
        <!-- ===== ترويسة الصفحة ===== -->
        <div class="page-header">
            <div class="page-title">
                <div class="flex items-center gap-3 flex-wrap">
                    <?php if ($filter !== 'all'): ?>
                    <a href="?filter=all" class="btn-back" style="display:inline-flex;align-items:center;gap:6px;padding:8px 16px;background:rgba(255,255,255,0.08);color:rgba(255,255,255,0.7);border-radius:10px;text-decoration:none;font-weight:600;font-size:13px;transition:all 0.3s ease;border:1px solid rgba(255,255,255,0.06);">
                        <i class="fas fa-arrow-right"></i> العودة للكل
                    </a>
                    <?php endif; ?>
                    <div>
                        <h1>📋 عطاءاتي <?= $filter_title ?></h1>
                        <p><?= $filter_description ?></p>
                    </div>
                </div>
            </div>
            <a href="<?= SITE_URL ?>/post_project.php" class="btn-new-project" style="background:linear-gradient(135deg,#10b981,#059669);color:white;padding:10px 24px;border-radius:12px;font-weight:700;font-size:14px;transition:all 0.3s ease;box-shadow:0 4px 15px rgba(16,185,129,0.3);display:inline-flex;align-items:center;gap:8px;text-decoration:none;border:none;cursor:pointer;">
                <i class="fas fa-plus"></i> مشروع جديد
            </a>
        </div>

        <!-- ===== عرض الرسائل ===== -->
        <?php if ($success_msg): ?>
        <div class="alert-success-custom">
            <i class="fas fa-check-circle"></i>
            <div><strong><?= $success_msg ?></strong></div>
        </div>
        <?php endif; ?>

        <?php if ($error_msg): ?>
        <div class="alert-error-custom">
            <i class="fas fa-exclamation-circle"></i>
            <div><strong><?= $error_msg ?></strong></div>
        </div>
        <?php endif; ?>

        <?php if ($user_type === 'employer'): ?>
            <!-- ========================================== -->
            <!-- عرض لصاحب العمل -->
            <!-- ========================================== -->
            
            <?php if (empty($projects)): ?>
                <div class="card-bg rounded-xl p-12 text-center border border-gray-200 shadow-sm">
                    <div class="text-6xl text-gray-400 mb-4">📭</div>
                    <h3 class="text-xl font-bold text-gray-700 mb-2">
                        <?php if ($filter === 'accepted'): ?>
                            لا توجد مشاريع محالة قيد التنفيذ
                        <?php elseif ($filter === 'pending_employer'): ?>
                            لا توجد عطاءات في انتظار موافقتك
                        <?php elseif ($filter === 'pending_admin'): ?>
                            لا توجد عطاءات قيد مراجعة الإدارة
                        <?php elseif ($filter === 'rejected'): ?>
                            لا توجد عطاءات مرفوضة
                        <?php else: ?>
                            لا توجد عطاءات واردة
                        <?php endif; ?>
                    </h3>
                    <p class="text-gray-400">
                        <?php if ($filter === 'accepted'): ?>
                            سيتم عرض المشاريع التي قبلت عطاءاتها هنا
                        <?php elseif ($filter === 'pending_employer'): ?>
                            العطاءات التي وافق عليها الأدمن تظهر هنا لتتخذ قرارك النهائي
                        <?php elseif ($filter === 'pending_admin'): ?>
                            العطاءات الجديدة تنتظر موافقة الإدارة أولاً
                        <?php elseif ($filter === 'rejected'): ?>
                            سيتم عرض العطاءات التي تم رفضها هنا
                        <?php else: ?>
                            لم تستلم أي عطاء على مشاريعك حتى الآن
                        <?php endif; ?>
                    </p>
                    <a href="?filter=all" class="inline-block mt-4 btn-blue px-6 py-2.5 rounded-lg transition-all shadow-blue">
                        <i class="fas fa-list ml-1"></i> عرض الكل
                    </a>
                </div>
            <?php else: ?>
                
                <!-- ===== فلتر العطاءات ===== -->
                <div class="filter-buttons">
                    <a href="?filter=all" class="filter-btn all <?= $filter === 'all' ? 'active' : '' ?>">
                        📊 الكل <span class="filter-count"><?= $all_bids ?></span>
                    </a>
                    <a href="?filter=pending_employer" class="filter-btn pending_employer <?= $filter === 'pending_employer' ? 'active' : '' ?>">
                        ⏳ في انتظار موافقتك <span class="filter-count"><?= $pending_employer_count ?></span>
                    </a>
                    <a href="?filter=pending_admin" class="filter-btn pending_admin <?= $filter === 'pending_admin' ? 'active' : '' ?>">
                        🔍 قيد مراجعة الإدارة <span class="filter-count"><?= $pending_admin_count ?></span>
                    </a>
                    <a href="?filter=accepted" class="filter-btn accepted <?= $filter === 'accepted' ? 'active' : '' ?>">
                        ✅ المشاريع المحالة <span class="filter-count"><?= $accepted_count ?></span>
                    </a>
                    <a href="?filter=rejected" class="filter-btn rejected <?= $filter === 'rejected' ? 'active' : '' ?>">
                        ❌ مرفوضة <span class="filter-count"><?= $rejected_count ?></span>
                    </a>
                </div>

                <!-- ===== عرض المشاريع والعطاءات ===== -->
                <?php 
                $has_bids = false;
                foreach ($projects as $project): 
                    $filtered_bids = $project['bids'];
                    if (empty($filtered_bids)) continue;
                    $has_bids = true;
                ?>
                    
                    <div class="project-main-card">
                        <!-- رأس المشروع -->
                        <div class="project-header">
                            <div class="flex items-center gap-3 flex-wrap">
                                <h2>
                                    <a href="<?= SITE_URL ?>/project_detail.php?id=<?= $project['id'] ?>">
                                        <?= clean($project['title']) ?>
                                    </a>
                                </h2>
                                <span class="status-<?= $project['status'] ?> text-xs px-2 py-1 rounded-full font-medium">
                                    <?php
                                        $status_labels = [
                                            'open' => '🟢 مفتوح',
                                            'in_progress' => '🟡 قيد التنفيذ',
                                            'completed' => '🔵 مكتمل',
                                            'cancelled' => '🔴 ملغي'
                                        ];
                                        echo $status_labels[$project['status']] ?? $project['status'];
                                    ?>
                                </span>
                                <span class="bids-count-badge">
                                    <i class="fas fa-gavel"></i> <?= count($project['bids']) ?> عطاء
                                </span>
                                <?php if ($project['accepted_bids_count'] > 0 && $filter !== 'accepted'): ?>
                                <span class="text-emerald text-xs font-bold bg-emerald-50 px-2 py-1 rounded-full">
                                    ✅ <?= $project['accepted_bids_count'] ?> محال
                                </span>
                                <?php endif; ?>
                            </div>
                            <div class="flex items-center gap-3">
                                <div class="project-meta">
                                    <span><i class="fas fa-calendar"></i> <?= date('Y-m-d', strtotime($project['created_at'])) ?></span>
                                </div>
                                <a href="<?= SITE_URL ?>/project_detail.php?id=<?= $project['id'] ?>" class="view-all-bids-link" style="display:inline-flex;align-items:center;gap:6px;padding:6px 14px;background:rgba(96,165,250,0.15);color:#60a5fa;border-radius:8px;text-decoration:none;font-weight:600;font-size:13px;transition:all 0.3s ease;border:1px solid rgba(96,165,250,0.2);">
                                    <i class="fas fa-eye"></i> عرض الكل
                                </a>
                            </div>
                        </div>
                        
                        <!-- العطاءات -->
                        <div class="project-bids">
                            <?php foreach ($filtered_bids as $bid): 
                                $card_class = 'bid-item-in-project';
                                if ($bid['status'] === 'accepted') {
                                    $card_class .= ' card-accepted';
                                } elseif ($bid['status'] === 'rejected') {
                                    $card_class .= ' card-rejected';
                                } elseif ($bid['status'] === 'pending_employer') {
                                    $card_class .= ' card-pending_employer';
                                } elseif ($bid['status'] === 'pending') {
                                    $card_class .= ' card-pending_admin';
                                }
                                
                                $workspace_id = null;
                                if ($bid['status'] === 'accepted') {
                                    $workspace_id = getWorkspaceId($project['id'], $bid['id']);
                                }
                                
                                $has_rated = false;
                                if ($project['status'] === 'completed') {
                                    $has_rated = hasUserRated($user_id, $bid['id'], 'employer');
                                }
                            ?>
                            <div class="<?= $card_class ?>" onclick="window.location.href='<?= SITE_URL ?>/project_detail.php?id=<?= $project['id'] ?>'">
                                <div class="bid-row">
                                    <div class="contractor-info">
                                        <div class="contractor-avatar-sm">
                                            <?php if (!empty($bid['contractor_avatar']) && $bid['contractor_avatar'] !== 'default-avatar.png'): ?>
                                            <img src="<?= SITE_URL ?>/<?= $bid['contractor_avatar'] ?>" alt="<?= clean($bid['contractor_name']) ?>">
                                            <?php else: ?>
                                            <?= mb_substr($bid['contractor_name'], 0, 1) ?>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <a href="<?= SITE_URL ?>/profile.php?id=<?= $bid['contractor_id'] ?>" class="font-semibold text-white hover:text-blue-400 transition-all">
                                                <?= clean($bid['contractor_name']) ?>
                                            </a>
                                            <div class="text-xs text-gray-400">
                                                <i class="fas fa-clock"></i> <?= timeAgo($bid['created_at']) ?>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="bid-details">
                                        <span class="bid-amount-sm"><?= number_format($bid['amount']) ?> ر.س</span>
                                        <span class="bid-status-<?= $bid['status'] ?> text-xs px-2 py-1 rounded-full font-medium">
                                            <?php
                                                $bid_status_labels = [
                                                    'pending' => '⏳ قيد مراجعة الإدارة',
                                                    'pending_employer' => '⏳ في انتظار موافقتك',
                                                    'accepted' => '✅ مقبول',
                                                    'rejected' => '❌ مرفوض'
                                                ];
                                                echo $bid_status_labels[$bid['status']] ?? $bid['status'];
                                            ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <?php if (!empty($bid['proposal'])): ?>
                                <div class="bid-proposal-sm">
                                    <?= clean(substr($bid['proposal'], 0, 150)) ?><?= strlen($bid['proposal']) > 150 ? '...' : '' ?>
                                </div>
                                <?php endif; ?>
                                
                                <!-- ===== جميع الأزرار ===== -->
                                <div class="bid-actions">
                                    
                                    <!-- 1. أزرار قبول/رفض (للعطاءات التي تنتظر موافقة صاحب العمل فقط) -->
                                    <?php if ($bid['status'] === 'pending_employer'): ?>
                                        <a href="<?= SITE_URL ?>/accept_bid.php?id=<?= $bid['id'] ?>" 
                                           class="btn-sm btn-emerald"
                                           onclick="event.stopPropagation(); return confirm('✅ سيتم نقل هذا العطاء إلى قسم المشاريع المحالة قيد التنفيذ. هل أنت متأكد من قبول هذا العطاء نهائياً؟')">
                                            <i class="fas fa-check"></i> قبول نهائي
                                        </a>
                                        <a href="<?= SITE_URL ?>/reject_bid.php?id=<?= $bid['id'] ?>" 
                                           class="btn-sm btn-red"
                                           onclick="event.stopPropagation(); return confirm('هل أنت متأكد من رفض هذا العطاء نهائياً؟')">
                                            <i class="fas fa-times"></i> رفض
                                        </a>
                                    <?php endif; ?>
                                    
                                    <!-- 2. محادثة (دائماً) -->
                                    <a href="<?= SITE_URL ?>/chat.php?user_id=<?= $bid['contractor_id'] ?>&bid_id=<?= $bid['id'] ?>" 
                                       class="btn-sm btn-blue"
                                       onclick="event.stopPropagation();">
                                        <i class="fas fa-comments"></i> محادثة
                                    </a>
                                    
                                    <!-- 3. رفع ملفات (للعطاءات المقبولة فقط) -->
                                    <?php if ($bid['status'] === 'accepted'): ?>
                                        <a href="<?= SITE_URL ?>/upload_project_files.php?bid_id=<?= $bid['id'] ?>&project_id=<?= $project['id'] ?>" 
                                           class="btn-sm btn-upload"
                                           onclick="event.stopPropagation();">
                                            <i class="fas fa-upload"></i> رفع ملفات
                                        </a>
                                    <?php endif; ?>
                                    
                                    <!-- 4. غرفة العمل (للمقبولة دائماً) -->
                                    <?php if ($bid['status'] === 'accepted'): ?>
                                        <?php if ($workspace_id): ?>
                                            <a href="<?= SITE_URL ?>/project_workspace.php?id=<?= $workspace_id ?>" 
                                               class="btn-sm btn-workspace"
                                               onclick="event.stopPropagation();">
                                                <i class="fas fa-door-open"></i> غرفة العمل
                                            </a>
                                        <?php else: ?>
                                            <span class="btn-sm btn-workspace" style="opacity:0.5;cursor:default;">
                                                <i class="fas fa-door-open"></i> قيد الإنشاء
                                            </span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    
                                    <!-- 5. اكتمال المشروع (للعطاءات المقبولة والمشروع قيد التنفيذ) -->
                                    <?php if ($bid['status'] === 'accepted' && $project['status'] === 'in_progress'): ?>
                                        <a href="<?= SITE_URL ?>/complete_project.php?project_id=<?= $project['id'] ?>&bid_id=<?= $bid['id'] ?>" 
                                           class="btn-sm btn-complete"
                                           onclick="event.stopPropagation(); return confirm('هل أنت متأكد من اكتمال المشروع؟')">
                                            <i class="fas fa-check-double"></i> اكتمال
                                        </a>
                                    <?php endif; ?>
                                    
                                    <!-- 6. تقييم المقاول (للمشاريع المكتملة) -->
                                    <?php if ($bid['status'] === 'accepted' && $project['status'] === 'completed'): ?>
                                        <?php if ($has_rated): ?>
                                            <span class="btn-sm btn-rated">
                                                <i class="fas fa-star"></i> تم التقييم ✅
                                            </span>
                                        <?php else: ?>
                                            <a href="<?= SITE_URL ?>/rate_contractor.php?bid_id=<?= $bid['id'] ?>&project_id=<?= $project['id'] ?>" 
                                               class="btn-sm btn-rate"
                                               onclick="event.stopPropagation();">
                                                <i class="fas fa-star"></i> تقييم
                                            </a>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    
                                    <!-- 7. تفاصيل المشروع -->
                                    <a href="<?= SITE_URL ?>/project_detail.php?id=<?= $project['id'] ?>" 
                                       class="btn-sm btn-purple"
                                       onclick="event.stopPropagation();">
                                        <i class="fas fa-eye"></i> تفاصيل
                                    </a>
                                    
                                    <!-- 8. حالة العطاء -->
                                    <?php if ($bid['status'] === 'accepted'): ?>
                                        <span class="text-emerald text-xs font-bold flex items-center gap-1 bg-emerald-50 px-3 py-1.5 rounded-lg">
                                            <i class="fas fa-check-circle"></i> ✅ محال
                                        </span>
                                    <?php endif; ?>
                                    
                                    <?php if ($bid['status'] === 'rejected'): ?>
                                        <span class="text-red text-xs font-bold flex items-center gap-1 bg-red-50 px-3 py-1.5 rounded-lg">
                                            <i class="fas fa-times-circle"></i> تم الرفض
                                        </span>
                                    <?php endif; ?>
                                    
                                    <?php if ($bid['status'] === 'pending_employer'): ?>
                                        <span class="text-amber text-xs font-bold flex items-center gap-1 bg-amber-50 px-3 py-1.5 rounded-lg">
                                            <i class="fas fa-clock"></i> في انتظار قرارك
                                        </span>
                                    <?php endif; ?>
                                    
                                    <?php if ($bid['status'] === 'pending'): ?>
                                        <span class="text-purple text-xs font-bold flex items-center gap-1 bg-purple-50 px-3 py-1.5 rounded-lg">
                                            <i class="fas fa-spinner"></i> قيد مراجعة الإدارة
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            
                            <!-- زر عرض جميع العطاءات -->
                            <div class="text-center mt-4">
                                <a href="<?= SITE_URL ?>/project_detail.php?id=<?= $project['id'] ?>" class="view-all-bids-link" style="display:inline-flex;align-items:center;gap:6px;padding:6px 14px;background:rgba(96,165,250,0.15);color:#60a5fa;border-radius:8px;text-decoration:none;font-weight:600;font-size:13px;transition:all 0.3s ease;border:1px solid rgba(96,165,250,0.2);">
                                    <i class="fas fa-eye"></i> عرض جميع العطاءات على هذا المشروع (<?= count($project['bids']) ?> عطاء)
                                </a>
                            </div>
                        </div>
                    </div>
                    
                <?php endforeach; ?>
                
                <?php if (!$has_bids): ?>
                    <div class="card-bg rounded-xl p-12 text-center border border-gray-200 shadow-sm">
                        <div class="text-6xl text-gray-400 mb-4">🔍</div>
                        <h3 class="text-xl font-bold text-gray-700 mb-2">لا توجد عطاءات بهذه الحالة</h3>
                        <p class="text-gray-400">
                            <?php if ($filter === 'accepted'): ?>
                                لم يتم قبول أي عطاء حتى الآن
                            <?php elseif ($filter === 'pending_employer'): ?>
                                لا توجد عطاءات في انتظار موافقتك
                            <?php elseif ($filter === 'pending_admin'): ?>
                                لا توجد عطاءات قيد مراجعة الإدارة
                            <?php elseif ($filter === 'rejected'): ?>
                                لا توجد عطاءات مرفوضة
                            <?php else: ?>
                                لا توجد عطاءات على مشاريعك
                            <?php endif; ?>
                        </p>
                        <a href="?filter=all" class="inline-block mt-4 btn-blue px-6 py-2.5 rounded-lg transition-all shadow-blue">
                            <i class="fas fa-list ml-1"></i> عرض الكل
                        </a>
                    </div>
                <?php endif; ?>

            <?php endif; ?>

        <?php else: ?>
            <!-- ========================================== -->
            <!-- عرض للمقاول (مع جميع الأزرار) -->
            <!-- ========================================== -->
            <div class="filter-buttons">
                <a href="?filter=all" class="filter-btn all <?= $filter === 'all' ? 'active' : '' ?>">
                    📊 الكل <span class="filter-count"><?= $total_bids ?></span>
                </a>
                <a href="?filter=accepted" class="filter-btn accepted <?= $filter === 'accepted' ? 'active' : '' ?>">
                    ✅ الموافق عليها <span class="filter-count"><?= $accepted_bids_count ?></span>
                </a>
                <a href="?filter=pending" class="filter-btn pending <?= $filter === 'pending' ? 'active' : '' ?>">
                    ⏳ المعلقة <span class="filter-count"><?= $pending_bids_count ?></span>
                </a>
                <a href="?filter=rejected" class="filter-btn rejected <?= $filter === 'rejected' ? 'active' : '' ?>">
                    ❌ المرفوضة <span class="filter-count"><?= $rejected_bids_count ?></span>
                </a>
            </div>

            <?php if (empty($my_bids)): ?>
                <div class="card-bg rounded-xl p-12 text-center border border-gray-200 shadow-sm">
                    <div class="text-6xl text-gray-400 mb-4">🔨</div>
                    <h3 class="text-xl font-bold text-gray-700 mb-2">
                        <?php if ($filter === 'accepted'): ?>
                            لا توجد عطاءات موافق عليها
                        <?php elseif ($filter === 'pending'): ?>
                            لا توجد عطاءات معلقة
                        <?php elseif ($filter === 'rejected'): ?>
                            لا توجد عطاءات مرفوضة
                        <?php else: ?>
                            لا توجد عطاءات
                        <?php endif; ?>
                    </h3>
                    <p class="text-gray-400">
                        <?php if ($filter === 'accepted'): ?>
                            سيتم عرض العطاءات التي تم قبولها هنا
                        <?php elseif ($filter === 'pending'): ?>
                            العطاءات التي تنتظر القرار تظهر هنا
                        <?php elseif ($filter === 'rejected'): ?>
                            العطاءات المرفوضة تظهر هنا
                        <?php else: ?>
                            لم تقدم أي عطاء بعد
                        <?php endif; ?>
                    </p>
                    <a href="?filter=all" class="inline-block mt-4 btn-blue px-6 py-2.5 rounded-lg transition-all shadow-blue">
                        <i class="fas fa-list ml-1"></i> عرض الكل
                    </a>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 gap-4">
                    <?php foreach ($my_bids as $bid): 
                        $card_class = 'card-bg';
                        if ($bid['status'] === 'accepted') {
                            $card_class = 'card-accepted';
                        } elseif ($bid['status'] === 'rejected') {
                            $card_class = 'card-rejected';
                        } else {
                            $card_class = 'card-pending_employer';
                        }
                        
                        $workspace_id = null;
                        if ($bid['status'] === 'accepted') {
                            $workspace_id = getWorkspaceId($bid['project_id'], $bid['id']);
                        }
                        
                        $has_rated = false;
                        if ($bid['project_status'] === 'completed') {
                            $has_rated = hasUserRated($user_id, $bid['id'], 'contractor');
                        }
                    ?>
                    <div class="<?= $card_class ?> rounded-xl shadow-sm border border-gray-200 overflow-hidden transition-all hover:shadow-md">
                        <div class="p-5">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div class="flex-1">
                                    <div class="flex items-center gap-3 mb-2">
                                        <h3 class="text-lg font-bold text-gray-800">
                                            <a href="<?= SITE_URL ?>/project_detail.php?id=<?= $bid['project_id'] ?>" class="hover:text-blue-600 transition-all">
                                                <?= clean($bid['project_title']) ?>
                                            </a>
                                        </h3>
                                        <span class="status-<?= $bid['project_status'] ?> text-xs px-2 py-1 rounded-full font-medium">
                                            <?php
                                                $status_labels = [
                                                    'open' => '🟢 مفتوح',
                                                    'in_progress' => '🟡 قيد التنفيذ',
                                                    'completed' => '🔵 مكتمل',
                                                    'cancelled' => '🔴 ملغي'
                                                ];
                                                echo $status_labels[$bid['project_status']] ?? $bid['project_status'];
                                            ?>
                                        </span>
                                    </div>
                                    
                                    <div class="flex flex-wrap items-center gap-4 text-sm text-gray-600">
                                        <div class="flex items-center gap-2">
                                            <a href="<?= SITE_URL ?>/profile.php?id=<?= $bid['employer_id'] ?>" class="flex items-center gap-2 hover:text-blue-600">
                                                <div class="w-6 h-6 rounded-full overflow-hidden bg-gradient-to-br from-blue-500 to-blue-600 flex items-center justify-center text-white font-bold text-xs flex-shrink-0">
                                                    <?php if (!empty($bid['employer_avatar']) && $bid['employer_avatar'] !== 'default-avatar.png'): ?>
                                                    <img src="<?= SITE_URL ?>/<?= $bid['employer_avatar'] ?>" class="w-full h-full object-cover">
                                                    <?php else: ?>
                                                    <?= mb_substr($bid['employer_name'], 0, 1) ?>
                                                    <?php endif; ?>
                                                </div>
                                                <span class="font-medium text-gray-700"><?= clean($bid['employer_name']) ?></span>
                                            </a>
                                        </div>
                                        <span class="flex items-center gap-1"><i class="fas fa-coins"></i> <strong class="text-emerald"><?= number_format($bid['amount']) ?> ر.س</strong></span>
                                        <span class="flex items-center gap-1"><i class="fas fa-gavel"></i> <span class="text-gray-500"><?= $bid['total_bids'] ?> عطاء</span></span>
                                    </div>
                                </div>
                                
                                <div class="text-right flex flex-col items-end gap-1">
                                    <span class="bid-status-<?= $bid['status'] ?> text-sm px-3 py-1 rounded-full font-medium">
                                        <?php
                                            $bid_status_labels = [
                                                'pending' => '⏳ قيد مراجعة الإدارة',
                                                'pending_employer' => '⏳ في انتظار موافقة صاحب العمل',
                                                'accepted' => '✅ مقبول',
                                                'rejected' => '❌ مرفوض'
                                            ];
                                            echo $bid_status_labels[$bid['status']] ?? $bid['status'];
                                        ?>
                                    </span>
                                    <div class="text-xs text-gray-400">
                                        <i class="fas fa-clock"></i> <?= timeAgo($bid['created_at']) ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mt-3 p-3 bg-gray-50 rounded-lg border border-gray-100">
                                <div class="text-xs text-gray-400 font-medium mb-1">📝 الاقتراح</div>
                                <p class="text-sm text-gray-600 leading-relaxed"><?= clean(substr($bid['proposal'], 0, 200)) ?><?= strlen($bid['proposal']) > 200 ? '...' : '' ?></p>
                            </div>
                            
                            <!-- ===== أزرار المقاول الكاملة ===== -->
                            <div class="mt-3 flex flex-wrap gap-2 pt-3 border-t border-gray-100">
                                <a href="<?= SITE_URL ?>/project_detail.php?id=<?= $bid['project_id'] ?>" 
                                   class="btn-blue px-4 py-1.5 rounded-lg text-sm transition-all shadow-blue flex items-center gap-1">
                                    <i class="fas fa-eye"></i> تفاصيل المشروع
                                </a>
                                
                                <?php if ($bid['status'] === 'pending'): ?>
                                    <a href="<?= SITE_URL ?>/delete_bid.php?id=<?= $bid['id'] ?>" 
                                       class="btn-red px-4 py-1.5 rounded-lg text-sm transition-all shadow-red flex items-center gap-1" 
                                       onclick="return confirm('هل أنت متأكد من حذف هذا العطاء؟')">
                                        <i class="fas fa-trash"></i> حذف
                                    </a>
                                <?php endif; ?>
                                
                                <a href="<?= SITE_URL ?>/chat.php?user_id=<?= $bid['employer_id'] ?>&project_id=<?= $bid['project_id'] ?>" 
                                   class="btn-blue px-4 py-1.5 rounded-lg text-sm transition-all shadow-blue flex items-center gap-1">
                                    <i class="fas fa-comments"></i> محادثة
                                </a>
                                
                                <!-- ✅ أزرار إضافية للعطاءات المقبولة (غرفة العمل، رفع ملفات) -->
                                <?php if ($bid['status'] === 'accepted'): ?>
                                    
                                    <!-- غرفة العمل -->
                                    <?php if ($workspace_id): ?>
                                        <a href="<?= SITE_URL ?>/project_workspace.php?id=<?= $workspace_id ?>" 
                                           class="btn-workspace px-4 py-1.5 rounded-lg text-sm transition-all shadow-purple flex items-center gap-1">
                                            <i class="fas fa-door-open"></i> غرفة العمل
                                        </a>
                                    <?php else: ?>
                                        <span class="btn-workspace px-4 py-1.5 rounded-lg text-sm opacity-50 cursor-default flex items-center gap-1" style="background:#8b5cf6;color:white;">
                                            <i class="fas fa-door-open"></i> قيد الإنشاء
                                        </span>
                                    <?php endif; ?>
                                    
                                    <!-- رفع ملفات -->
                                    <a href="<?= SITE_URL ?>/upload_project_files.php?bid_id=<?= $bid['id'] ?>&project_id=<?= $bid['project_id'] ?>" 
                                       class="btn-upload px-4 py-1.5 rounded-lg text-sm transition-all shadow-amber flex items-center gap-1">
                                        <i class="fas fa-upload"></i> رفع ملفات
                                    </a>
                                    
                                <?php endif; ?>
                                
                                <!-- ✅ تقييم صاحب العمل (للمشاريع المكتملة) -->
                                <?php if ($bid['status'] === 'accepted' && $bid['project_status'] === 'completed'): ?>
                                    <?php if ($has_rated): ?>
                                        <span class="btn-sm btn-rated px-3 py-1.5 rounded-lg text-sm">
                                            <i class="fas fa-star"></i> تم التقييم ✅
                                        </span>
                                    <?php else: ?>
                                        <a href="<?= SITE_URL ?>/rate_employer.php?bid_id=<?= $bid['id'] ?>&project_id=<?= $bid['project_id'] ?>" 
                                           class="btn-rate px-4 py-1.5 rounded-lg text-sm transition-all shadow-purple flex items-center gap-1">
                                            <i class="fas fa-star"></i> تقييم صاحب العمل
                                        </a>
                                    <?php endif; ?>
                                <?php endif; ?>
                                
                                <!-- حالة العطاء -->
                                <?php if ($bid['status'] === 'accepted'): ?>
                                    <span class="text-emerald text-xs font-bold flex items-center gap-1 bg-emerald-50 px-3 py-1.5 rounded-lg">
                                        <i class="fas fa-check-circle"></i> عطاء مقبول
                                    </span>
                                <?php endif; ?>
                                
                                <?php if ($bid['status'] === 'pending_employer'): ?>
                                    <span class="text-amber text-xs font-bold flex items-center gap-1 bg-amber-50 px-3 py-1.5 rounded-lg">
                                        <i class="fas fa-clock"></i> في انتظار موافقة صاحب العمل
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        <?php endif; ?>
        
    </div>
</div>

<?php include 'includes/footer.php'; ?>