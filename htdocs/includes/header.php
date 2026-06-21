<?php
// ============================================
// HEADER.PHP - نسخة احترافية عالمية (معدلة)
// ============================================
ob_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/notification_system.php';   // <-- تمت الإضافة هنا

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ============================================
// جلب بيانات المستخدم والإشعارات
// ============================================
// ... باقي الكود ...
$current_user = null;
$unread_count = 0;
$chat_unread_count = 0;
$alert_unread_count = 0;
$user_notifications = [];
$theme = $_COOKIE['theme'] ?? 'light';

if (isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0) {
    $current_user = getUser($_SESSION['user_id']);
    
    if ($current_user) {
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
            $stmt->execute([$_SESSION['user_id']]);
            $unread_count = (int)$stmt->fetchColumn();
            
            $stmt2 = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
            $stmt2->execute([$_SESSION['user_id']]);
            $user_notifications = $stmt2->fetchAll();
            
            $user_type = $_SESSION['user_type'] ?? null;
            if ($user_type === 'employer') {
                $stmt = $pdo->prepare("SELECT SUM(employer_unread) as total FROM conversations WHERE employer_id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $chat_unread_count = (int)($stmt->fetch()['total'] ?? 0);
            } elseif ($user_type === 'contractor') {
                $stmt = $pdo->prepare("SELECT SUM(contractor_unread) as total FROM conversations WHERE contractor_id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $chat_unread_count = (int)($stmt->fetch()['total'] ?? 0);
            }
            
            if ($user_type === 'contractor') {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM contractor_alerts_log WHERE contractor_id = ? AND is_read = 0");
                $stmt->execute([$_SESSION['user_id']]);
                $alert_unread_count = (int)$stmt->fetchColumn();
            }
        } catch (Exception $e) {
            error_log("Header error: " . $e->getMessage());
        }
    }
}

// ============================================
// ✅ تحديد رابط الرئيسية حسب نوع المستخدم
// ============================================
$home_url = SITE_URL . '/';
if (isset($_SESSION['user_id']) && isset($_SESSION['user_type'])) {
    if ($_SESSION['user_type'] === 'employer') {
        $home_url = SITE_URL . '/employer_dashboard.php';
    } elseif ($_SESSION['user_type'] === 'admin') {
        $home_url = SITE_URL . '/admin.php';
    }
    // للمقاول والمحل والزوار يبقى الرابط للرئيسية العامة (index)
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl" class="<?= $theme === 'dark' ? 'dark' : '' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#2563eb">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title><?= isset($page_title) ? $page_title . ' | ' . SITE_NAME : SITE_NAME ?></title>
    
    <!-- ============================================ -->
    <!-- FONTS & ICONS -->
    <!-- ============================================ -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <!-- ============================================ -->
    <!-- TAILWIND CSS -->
    <!-- ============================================ -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#eff6ff', 100: '#dbeafe', 200: '#bfdbfe',
                            300: '#93c5fd', 400: '#60a5fa', 500: '#3b82f6',
                            600: '#2563eb', 700: '#1d4ed8', 800: '#1e40af', 900: '#1e3a8a'
                        }
                    }
                }
            }
        }
    </script>
    
    <style>
        /* ============================================ */
        /* RESET & BASE */
        /* ============================================ */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        
        body {
            font-family: 'Cairo', sans-serif;
            font-size: 14px;
            line-height: 1.6;
            background: #f8fafc;
            color: #1e293b;
            padding-top: 72px !important;
            padding-bottom: 100px !important;
            min-height: 100vh;
            transition: background 0.3s ease, color 0.3s ease;
        }
        
        .dark body {
            background: #0f172a;
            color: #e2e8f0;
        }
        
        /* ============================================ */
        /* HEADER - GLASSMORPHISM */
        /* ============================================ */
        .header-glass {
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            right: 0 !important;
            z-index: 99999 !important;
            height: 72px !important;
            background: rgba(255, 255, 255, 0.72) !important;
            backdrop-filter: blur(20px) saturate(180%) !important;
            -webkit-backdrop-filter: blur(20px) saturate(180%) !important;
            border-bottom: 1px solid rgba(255, 255, 255, 0.3) !important;
            box-shadow: 0 1px 3px rgba(0,0,0,0.04), 0 8px 32px rgba(0,0,0,0.04) !important;
            transition: all 0.3s ease !important;
            display: flex !important;
            align-items: center !important;
        }
        
        .dark .header-glass {
            background: rgba(15, 23, 42, 0.78) !important;
            border-bottom: 1px solid rgba(255,255,255,0.06) !important;
            box-shadow: 0 1px 3px rgba(0,0,0,0.2), 0 8px 32px rgba(0,0,0,0.2) !important;
        }
        
        .header-glass.scrolled {
            height: 64px !important;
            background: rgba(255, 255, 255, 0.92) !important;
            box-shadow: 0 4px 24px rgba(0,0,0,0.06) !important;
        }
        
        .dark .header-glass.scrolled {
            background: rgba(15, 23, 42, 0.92) !important;
        }
        
        .header-inner {
            max-width: 1280px !important;
            margin: 0 auto !important;
            padding: 0 20px !important;
            width: 100% !important;
            display: flex !important;
            align-items: center !important;
            justify-content: space-between !important;
            height: 100% !important;
        }
        
        /* ============================================ */
        /* LOGO */
        /* ============================================ */
        .logo {
            display: flex !important;
            align-items: center !important;
            gap: 10px !important;
            text-decoration: none !important;
            flex-shrink: 0 !important;
        }
        
        .logo-icon {
            width: 40px !important;
            height: 40px !important;
            background: linear-gradient(135deg, #2563eb, #1d4ed8) !important;
            border-radius: 12px !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            color: white !important;
            font-size: 20px !important;
            box-shadow: 0 4px 16px rgba(37,99,235,0.25) !important;
            transition: transform 0.2s ease !important;
        }
        
        .logo:hover .logo-icon { transform: scale(1.05) rotate(-4deg) !important; }
        
        .logo-text {
            font-size: 20px !important;
            font-weight: 900 !important;
            color: #1e293b !important;
            letter-spacing: -0.5px !important;
        }
        
        .dark .logo-text { color: #f1f5f9 !important; }
        .logo-text span { color: #2563eb !important; }
        
        .logo-badge {
            font-size: 9px !important;
            font-weight: 700 !important;
            background: linear-gradient(135deg, #f59e0b, #d97706) !important;
            color: white !important;
            padding: 2px 8px !important;
            border-radius: 20px !important;
            letter-spacing: 0.3px !important;
            text-transform: uppercase !important;
            margin-right: 4px !important;
        }
        
        /* ============================================ */
        /* NAVIGATION LINKS */
        /* ============================================ */
        .nav-links {
            display: flex !important;
            align-items: center !important;
            gap: 4px !important;
        }
        
        .nav-link {
            padding: 8px 16px !important;
            border-radius: 10px !important;
            font-size: 14px !important;
            font-weight: 600 !important;
            color: #475569 !important;
            text-decoration: none !important;
            transition: all 0.2s ease !important;
            position: relative !important;
        }
        
        .dark .nav-link { color: #94a3b8 !important; }
        
        .nav-link:hover {
            background: rgba(37,99,235,0.08) !important;
            color: #2563eb !important;
        }
        
        .dark .nav-link:hover {
            background: rgba(37,99,235,0.15) !important;
            color: #60a5fa !important;
        }
        
        .nav-link.active {
            color: #2563eb !important;
            background: rgba(37,99,235,0.1) !important;
        }
        
        .dark .nav-link.active {
            color: #60a5fa !important;
            background: rgba(37,99,235,0.15) !important;
        }
        
        .nav-link.active::after {
            content: '' !important;
            position: absolute !important;
            bottom: 2px !important;
            left: 50% !important;
            transform: translateX(-50%) !important;
            width: 20px !important;
            height: 3px !important;
            background: #2563eb !important;
            border-radius: 4px !important;
        }
        
        /* ============================================ */
        /* BUTTONS */
        /* ============================================ */
        .btn-primary {
            background: linear-gradient(135deg, #2563eb, #1d4ed8) !important;
            color: white !important;
            padding: 8px 20px !important;
            border-radius: 10px !important;
            font-weight: 700 !important;
            font-size: 14px !important;
            border: none !important;
            cursor: pointer !important;
            transition: all 0.25s ease !important;
            text-decoration: none !important;
            display: inline-flex !important;
            align-items: center !important;
            gap: 6px !important;
            box-shadow: 0 4px 14px rgba(37,99,235,0.25) !important;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 8px 28px rgba(37,99,235,0.35) !important;
        }
        
        .btn-secondary {
            background: rgba(37,99,235,0.08) !important;
            color: #2563eb !important;
            padding: 8px 16px !important;
            border-radius: 10px !important;
            font-weight: 600 !important;
            font-size: 14px !important;
            border: none !important;
            cursor: pointer !important;
            transition: all 0.25s ease !important;
            text-decoration: none !important;
        }
        
        .dark .btn-secondary {
            background: rgba(37,99,235,0.15) !important;
            color: #60a5fa !important;
        }
        
        .btn-secondary:hover { background: rgba(37,99,235,0.15) !important; }
        
        /* ============================================ */
        /* ICON BUTTONS */
        /* ============================================ */
        .icon-btn {
            width: 40px !important;
            height: 40px !important;
            border-radius: 50% !important;
            border: none !important;
            background: transparent !important;
            color: #475569 !important;
            font-size: 20px !important;
            cursor: pointer !important;
            transition: all 0.2s ease !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            position: relative !important;
        }
        
        .dark .icon-btn { color: #94a3b8 !important; }
        
        .icon-btn:hover {
            background: rgba(37,99,235,0.08) !important;
            color: #2563eb !important;
        }
        
        .dark .icon-btn:hover {
            background: rgba(37,99,235,0.15) !important;
            color: #60a5fa !important;
        }
        
        .icon-btn .badge {
            position: absolute !important;
            top: 2px !important;
            right: 2px !important;
            background: #ef4444 !important;
            color: white !important;
            font-size: 9px !important;
            min-width: 18px !important;
            height: 18px !important;
            padding: 0 5px !important;
            border-radius: 20px !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            font-weight: 700 !important;
            border: 2px solid rgba(255,255,255,0.9) !important;
        }
        
        .dark .icon-btn .badge { border-color: #1e293b !important; }
        
        /* ============================================ */
        /* USER AVATAR */
        /* ============================================ */
        .user-avatar {
            width: 40px !important;
            height: 40px !important;
            border-radius: 50% !important;
            overflow: hidden !important;
            border: 2px solid #e2e8f0 !important;
            cursor: pointer !important;
            transition: all 0.25s ease !important;
            flex-shrink: 0 !important;
            background: linear-gradient(135deg, #dbeafe, #bfdbfe) !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            text-decoration: none !important;
        }
        
        .dark .user-avatar {
            border-color: #334155 !important;
            background: linear-gradient(135deg, #1e293b, #334155) !important;
        }
        
        .user-avatar:hover {
            border-color: #2563eb !important;
            transform: scale(1.05) !important;
        }
        
        .user-avatar img {
            width: 100% !important;
            height: 100% !important;
            object-fit: cover !important;
        }
        
        .user-avatar .fallback {
            width: 100% !important;
            height: 100% !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            font-weight: 700 !important;
            font-size: 16px !important;
            color: #2563eb !important;
            background: linear-gradient(135deg, #dbeafe, #bfdbfe) !important;
        }
        
        .dark .user-avatar .fallback {
            color: #60a5fa !important;
            background: linear-gradient(135deg, #1e293b, #334155) !important;
        }
        
        /* ============================================ */
        /* DROPDOWN OVERLAY */
        /* ============================================ */
        .dropdown-overlay {
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            right: 0 !important;
            bottom: 0 !important;
            z-index: 99998 !important;
            display: none !important;
        }
        
        .dropdown-overlay.open { display: block !important; }
        
        /* ============================================ */
        /* DROPDOWN MENU */
        /* ============================================ */
        .dropdown-menu {
            position: absolute !important;
            top: calc(100% + 8px) !important;
            right: 0 !important;
            min-width: 360px !important;
            max-width: 400px !important;
            background: rgba(255,255,255,0.95) !important;
            backdrop-filter: blur(24px) saturate(180%) !important;
            -webkit-backdrop-filter: blur(24px) saturate(180%) !important;
            border-radius: 16px !important;
            border: 1px solid rgba(255,255,255,0.3) !important;
            box-shadow: 0 20px 60px rgba(0,0,0,0.12) !important;
            padding: 8px 0 !important;
            display: none !important;
            animation: slideDown 0.25s cubic-bezier(0.4,0,0.2,1) !important;
            overflow: hidden !important;
        }
        
        .dark .dropdown-menu {
            background: rgba(30,41,59,0.95) !important;
            border-color: rgba(255,255,255,0.06) !important;
            box-shadow: 0 20px 60px rgba(0,0,0,0.4) !important;
        }
        
        .dropdown-menu.open { display: block !important; }
        
        @keyframes slideDown {
            from { transform: translateY(-10px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        
        .dropdown-header {
            padding: 14px 20px !important;
            border-bottom: 1px solid rgba(0,0,0,0.05) !important;
            display: flex !important;
            justify-content: space-between !important;
            align-items: center !important;
        }
        
        .dark .dropdown-header { border-color: rgba(255,255,255,0.05) !important; }
        
        .dropdown-header h3 {
            font-weight: 700 !important;
            font-size: 16px !important;
            color: #1e293b !important;
        }
        
        .dark .dropdown-header h3 { color: #f1f5f9 !important; }
        
        .dropdown-header .mark-all {
            font-size: 12px !important;
            font-weight: 600 !important;
            color: #2563eb !important;
            cursor: pointer !important;
            background: none !important;
            border: none !important;
            transition: color 0.2s !important;
        }
        
        .dropdown-header .mark-all:hover { color: #1d4ed8 !important; }
        
        .dropdown-list {
            max-height: 340px !important;
            overflow-y: auto !important;
            padding: 4px 0 !important;
        }
        
        .dropdown-item {
            display: flex !important;
            align-items: flex-start !important;
            gap: 12px !important;
            padding: 12px 20px !important;
            text-decoration: none !important;
            transition: background 0.15s ease !important;
            border-bottom: 1px solid rgba(0,0,0,0.03) !important;
        }
        
        .dark .dropdown-item { border-color: rgba(255,255,255,0.03) !important; }
        
        .dropdown-item:hover { background: rgba(37,99,235,0.05) !important; }
        .dark .dropdown-item:hover { background: rgba(37,99,235,0.08) !important; }
        
        .dropdown-item.unread {
            background: rgba(37,99,235,0.06) !important;
            border-right: 3px solid #2563eb !important;
        }
        
        .dark .dropdown-item.unread { background: rgba(37,99,235,0.1) !important; }
        
        .dropdown-item .icon {
            width: 36px !important;
            height: 36px !important;
            border-radius: 50% !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            flex-shrink: 0 !important;
            font-size: 14px !important;
        }
        
        .dropdown-item .content { flex: 1 !important; min-width: 0 !important; }
        
        .dropdown-item .title {
            font-weight: 600 !important;
            font-size: 14px !important;
            color: #1e293b !important;
        }
        
        .dark .dropdown-item .title { color: #f1f5f9 !important; }
        
        .dropdown-item .message {
            font-size: 13px !important;
            color: #64748b !important;
            margin-top: 2px !important;
            display: -webkit-box !important;
            -webkit-line-clamp: 2 !important;
            -webkit-box-orient: vertical !important;
            overflow: hidden !important;
        }
        
        .dark .dropdown-item .message { color: #94a3b8 !important; }
        
        .dropdown-item .time {
            font-size: 11px !important;
            color: #94a3b8 !important;
            margin-top: 4px !important;
        }
        
        .dropdown-empty {
            padding: 40px 20px !important;
            text-align: center !important;
            color: #94a3b8 !important;
        }
        
        .dropdown-empty i {
            font-size: 36px !important;
            color: #cbd5e1 !important;
            margin-bottom: 12px !important;
            display: block !important;
        }
        
        /* ============================================ */
        /* MOBILE MENU TOGGLE */
        /* ============================================ */
      .menu-toggle {
    display: flex !important;  /* تغيير من none إلى flex */
    width: 40px !important;
    height: 40px !important;
    border-radius: 50% !important;
    border: none !important;
    background: transparent !important;
    color: #1e293b !important;
    font-size: 20px !important;
    cursor: pointer !important;
    transition: all 0.2s ease !important;
    align-items: center !important;
    justify-content: center !important;
}
        
        .dark .menu-toggle { color: #f1f5f9 !important; }
        .menu-toggle:hover {
            background: rgba(37,99,235,0.08) !important;
            color: #2563eb !important;
        }
        
        /* ============================================ */
        /* SIDEBAR - القائمة الجانبية المحسّنة */
        /* ============================================ */
        .sidebar-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 99998;
            display: none;
            backdrop-filter: blur(4px);
        }
        
        .sidebar-overlay.open { display: block; }
        
        .sidebar {
            position: fixed;
            top: 0;
            right: -340px;
            width: 320px;
            height: 100%;
            background: white;
            z-index: 99999;
            transition: right 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            box-shadow: -2px 0 30px rgba(0, 0, 0, 0.1);
            overflow-y: auto;
        }
        
        .dark .sidebar {
            background: #1e293b;
        }
        
        .sidebar.open {
            right: 0;
        }
        
        /* ============================================ */
        /* بروفايل المستخدم في القائمة الجانبية */
        /* ============================================ */
        .sidebar-user-profile {
            padding: 16px 20px;
            border-bottom: 1px solid #f1f5f9;
        }
        
        .dark .sidebar-user-profile {
            border-bottom-color: #334155;
        }
        
        .user-avatar-sidebar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            overflow: hidden;
            flex-shrink: 0;
            border: 2px solid #e2e8f0;
            background: linear-gradient(135deg, #dbeafe, #bfdbfe);
        }
        
        .dark .user-avatar-sidebar {
            border-color: #475569;
            background: linear-gradient(135deg, #1e293b, #334155);
        }
        
        .user-avatar-sidebar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .user-avatar-sidebar .fallback-sidebar {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 18px;
            color: #2563eb;
            background: linear-gradient(135deg, #dbeafe, #bfdbfe);
        }
        
        .dark .user-avatar-sidebar .fallback-sidebar {
            color: #60a5fa;
            background: linear-gradient(135deg, #1e293b, #334155);
        }
        
        /* ============================================ */
        /* روابط القائمة الجانبية */
        /* ============================================ */
        .sidebar .sidebar-header {
            padding: 16px 20px;
            border-bottom: 1px solid #f1f5f9;
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: white;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        
        .dark .sidebar .sidebar-header {
            background: #1e293b;
            border-bottom-color: #334155;
        }
        
        .sidebar .sidebar-close {
            background: none;
            border: none;
            color: #94a3b8;
            font-size: 22px;
            cursor: pointer;
            padding: 4px;
        }
        
        .sidebar .sidebar-close:hover {
            color: #1e293b;
        }
        
        .dark .sidebar .sidebar-close:hover {
            color: #f1f5f9;
        }
        
        .sidebar .sidebar-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 16px;
            color: #1e293b;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s ease;
            border-radius: 8px;
            margin: 0 4px;
        }
        
        .dark .sidebar .sidebar-link {
            color: #e2e8f0;
        }
        
        .sidebar .sidebar-link:hover {
            background: #f1f5f9;
            color: #2563eb;
        }
        
        .dark .sidebar .sidebar-link:hover {
            background: #334155;
            color: #60a5fa;
        }
        
        .sidebar .sidebar-link i {
            width: 24px;
            text-align: center;
            color: #64748b;
            font-size: 17px;
        }
        
        .dark .sidebar .sidebar-link i {
            color: #94a3b8;
        }
        
        .sidebar .sidebar-link:hover i {
            color: #2563eb;
        }
        
        .dark .sidebar .sidebar-link:hover i {
            color: #60a5fa;
        }
        
        .sidebar .sidebar-link .badge-sidebar {
            background: #ef4444;
            color: white;
            font-size: 10px;
            padding: 2px 8px;
            border-radius: 20px;
            margin-right: auto;
            font-weight: 700;
            min-width: 20px;
            text-align: center;
        }
        
        .sidebar .sidebar-divider {
            height: 1px;
            background: #f1f5f9;
            margin: 8px 12px;
        }
        
        .dark .sidebar .sidebar-divider {
            background: #334155;
        }
        
        .sidebar .sidebar-section-title {
            padding: 8px 16px 4px;
            font-size: 11px;
            font-weight: 700;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .dark .sidebar .sidebar-section-title {
            color: #64748b;
        }
        
        /* ============================================ */
        /* BOTTOM NAV - GLASSMORPHISM */
        /* ============================================ */
        .bottom-nav {
            position: fixed !important;
            bottom: 16px !important;
            left: 16px !important;
            right: 16px !important;
            background: rgba(255,255,255,0.88) !important;
            backdrop-filter: blur(24px) saturate(180%) !important;
            -webkit-backdrop-filter: blur(24px) saturate(180%) !important;
            border-radius: 28px !important;
            border: 1px solid rgba(255,255,255,0.3) !important;
            box-shadow: 0 8px 40px rgba(0,0,0,0.08) !important;
            padding: 8px 12px !important;
            display: flex !important;
            justify-content: space-around !important;
            align-items: center !important;
            z-index: 99999 !important;
            height: 78px !important;
            transition: all 0.4s cubic-bezier(0.4,0,0.2,1) !important;
        }
        
        .dark .bottom-nav {
            background: rgba(15,23,42,0.9) !important;
            border-color: rgba(255,255,255,0.06) !important;
            box-shadow: 0 8px 40px rgba(0,0,0,0.4) !important;
        }
        
        .bottom-nav.hidden-nav {
            transform: translateY(120px) !important;
            opacity: 0 !important;
            pointer-events: none !important;
        }
        
        /* ============================================ */
        /* BOTTOM NAV ITEMS */
        /* ============================================ */
        .bottom-nav-item {
            display: flex !important;
            flex-direction: column !important;
            align-items: center !important;
            justify-content: center !important;
            gap: 2px !important;
            color: #94a3b8 !important;
            font-size: 10px !important;
            font-weight: 600 !important;
            padding: 4px 6px !important;
            border-radius: 16px !important;
            text-decoration: none !important;
            min-width: 48px !important;
            max-width: 72px !important;
            position: relative !important;
            background: transparent !important;
            border: none !important;
            cursor: pointer !important;
            transition: all 0.25s cubic-bezier(0.4,0,0.2,1) !important;
            flex: 1 !important;
            -webkit-tap-highlight-color: transparent !important;
        }
        
        .dark .bottom-nav-item { color: #64748b !important; }
        
        .bottom-nav-item .nav-icon {
            position: relative !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            width: 44px !important;
            height: 44px !important;
            border-radius: 14px !important;
            transition: all 0.3s cubic-bezier(0.4,0,0.2,1) !important;
        }
        
        .bottom-nav-item .nav-icon i {
            font-size: 22px !important;
            transition: all 0.3s cubic-bezier(0.4,0,0.2,1) !important;
            color: #94a3b8 !important;
        }
        
        .dark .bottom-nav-item .nav-icon i { color: #64748b !important; }
        
        .bottom-nav-item .nav-label {
            font-size: 9px !important;
            font-weight: 600 !important;
            color: #94a3b8 !important;
            transition: all 0.3s ease !important;
            letter-spacing: 0.2px !important;
            white-space: nowrap !important;
        }
        
        .dark .bottom-nav-item .nav-label { color: #64748b !important; }
        
        /* ============================================ */
        /* EXPLORE BUTTON - BIG & SPECIAL */
        /* ============================================ */
        .bottom-nav-item.explore-btn {
            flex: 1.4 !important;
            max-width: 80px !important;
            min-width: 60px !important;
            margin-top: -12px !important;
        }
        
        .explore-icon-wrapper {
            position: relative !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            width: 64px !important;
            height: 64px !important;
        }
        
        .explore-ring {
            width: 64px !important;
            height: 64px !important;
            border-radius: 50% !important;
            background: linear-gradient(135deg, #2563eb, #7c3aed) !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            box-shadow: 0 6px 24px rgba(37,99,235,0.35) !important;
            transition: all 0.4s cubic-bezier(0.4,0,0.2,1) !important;
            position: relative !important;
        }
        
        .explore-ring::before {
            content: '' !important;
            position: absolute !important;
            inset: -4px !important;
            border-radius: 50% !important;
            background: conic-gradient(from 0deg, #2563eb, #7c3aed, #2563eb) !important;
            opacity: 0.3 !important;
            animation: spinRing 4s linear infinite !important;
            z-index: -1 !important;
        }
        
        @keyframes spinRing {
            to { transform: rotate(360deg) !important; }
        }
        
        .explore-ring i {
            font-size: 28px !important;
            color: white !important;
            transition: all 0.4s cubic-bezier(0.4,0,0.2,1) !important;
            filter: drop-shadow(0 2px 8px rgba(0,0,0,0.15)) !important;
        }
        
        .bottom-nav-item.explore-btn:hover .explore-ring {
            transform: scale(1.08) rotate(-8deg) !important;
            box-shadow: 0 8px 32px rgba(37,99,235,0.45) !important;
        }
        
        .bottom-nav-item.explore-btn:hover .explore-ring i {
            transform: rotate(45deg) scale(1.1) !important;
        }
        
        .bottom-nav-item.explore-btn:active .explore-ring {
            transform: scale(0.92) !important;
        }
        
        .explore-label {
            font-size: 10px !important;
            font-weight: 700 !important;
            background: linear-gradient(135deg, #2563eb, #7c3aed) !important;
            -webkit-background-clip: text !important;
            -webkit-text-fill-color: transparent !important;
            margin-top: 2px !important;
        }
        
        .dark .explore-label {
            background: linear-gradient(135deg, #60a5fa, #a78bfa) !important;
            -webkit-background-clip: text !important;
            -webkit-text-fill-color: transparent !important;
        }
        
        /* ============================================ */
        /* BOTTOM NAV ACTIVE STATE */
        /* ============================================ */
        .bottom-nav-item.active .nav-icon {
            background: rgba(37,99,235,0.1) !important;
            transform: translateY(-2px) !important;
        }
        
        .dark .bottom-nav-item.active .nav-icon {
            background: rgba(37,99,235,0.15) !important;
        }
        
        .bottom-nav-item.active .nav-icon i {
            color: #2563eb !important;
            transform: scale(1.1) !important;
        }
        
        .dark .bottom-nav-item.active .nav-icon i {
            color: #60a5fa !important;
        }
        
        .bottom-nav-item.active .nav-label {
            color: #2563eb !important;
        }
        
        .dark .bottom-nav-item.active .nav-label {
            color: #60a5fa !important;
        }
        
        .bottom-nav-item.explore-btn.active .explore-ring {
            box-shadow: 0 6px 28px rgba(37,99,235,0.5) !important;
            transform: scale(1.05) !important;
        }
        
        .bottom-nav-item.explore-btn.active .explore-ring i {
            transform: rotate(45deg) scale(1.1) !important;
        }
        
        /* ============================================ */
        /* BADGES */
        /* ============================================ */
        .bottom-nav-item .badge-nav {
            position: absolute !important;
            top: -2px !important;
            right: -2px !important;
            background: #ef4444 !important;
            color: white !important;
            font-size: 9px !important;
            min-width: 18px !important;
            height: 18px !important;
            padding: 0 5px !important;
            border-radius: 20px !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            font-weight: 700 !important;
            border: 2px solid white !important;
            z-index: 2 !important;
            animation: badgePop 0.3s cubic-bezier(0.4,0,0.2,1) !important;
        }
        
        .dark .bottom-nav-item .badge-nav { border-color: #1e293b !important; }
        
        @keyframes badgePop {
            0% { transform: scale(0); }
            50% { transform: scale(1.3); }
            100% { transform: scale(1); }
        }
        
        .bottom-nav-item .badge-nav.badge-blue { background: #2563eb !important; }
        .bottom-nav-item .badge-nav.badge-amber { background: #f59e0b !important; }
        
        /* ============================================ */
        /* LOGOUT BUTTON */
        /* ============================================ */
        .bottom-nav-item.logout-btn .nav-icon i {
            color: #ef4444 !important;
        }
        .bottom-nav-item.logout-btn .nav-label {
            color: #ef4444 !important;
        }
        .dark .bottom-nav-item.logout-btn .nav-icon i {
            color: #f87171 !important;
        }
        .dark .bottom-nav-item.logout-btn .nav-label {
            color: #f87171 !important;
        }
        
        /* ============================================ */
        /* RIPPLE EFFECT */
        /* ============================================ */
        .ripple-effect {
            position: absolute !important;
            border-radius: 50% !important;
            background: rgba(37,99,235,0.15) !important;
            transform: scale(0) !important;
            animation: rippleAnim 0.6s linear !important;
            pointer-events: none !important;
        }
        
        @keyframes rippleAnim {
            to { transform: scale(4) !important; opacity: 0 !important; }
        }
        
        /* ============================================ */
        /* TOAST */
        /* ============================================ */
        #toast-container {
            position: fixed !important;
            bottom: 100px !important;
            right: 16px !important;
            z-index: 999999 !important;
            display: flex !important;
            flex-direction: column !important;
            gap: 8px !important;
        }
        
        .toast {
            padding: 12px 20px !important;
            border-radius: 12px !important;
            background: rgba(15,23,42,0.9) !important;
            backdrop-filter: blur(12px) !important;
            color: white !important;
            font-weight: 500 !important;
            font-size: 14px !important;
            box-shadow: 0 8px 32px rgba(0,0,0,0.15) !important;
            animation: slideDown 0.3s ease !important;
            display: flex !important;
            align-items: center !important;
            gap: 10px !important;
            max-width: 360px !important;
        }
        
        .toast-success { border-right: 4px solid #10b981 !important; }
        .toast-error { border-right: 4px solid #ef4444 !important; }
        .toast-warning { border-right: 4px solid #f59e0b !important; }
        .toast-info { border-right: 4px solid #3b82f6 !important; }
        
        @keyframes slideOut {
            from { transform: translateX(0); opacity: 1; }
            to { transform: translateX(100%); opacity: 0; }
        }
        
        /* ============================================ */
        /* RESPONSIVE */
        /* ============================================ */
        @media (max-width: 1024px) {
            .nav-links { gap: 2px !important; }
            .nav-link { padding: 6px 12px !important; font-size: 13px !important; }
        }
        
        @media (max-width: 768px) {
            body { padding-top: 64px !important; padding-bottom: 90px !important; }
            .header-glass { height: 64px !important; }
            .header-glass.scrolled { height: 56px !important; }
            .header-inner { padding: 0 12px !important; }
            .menu-toggle { display: flex !important; }
            
            .nav-links {
                display: none !important;
                position: absolute !important;
                top: 100% !important;
                left: 0 !important;
                right: 0 !important;
                background: rgba(255,255,255,0.95) !important;
                backdrop-filter: blur(20px) !important;
                padding: 12px 16px !important;
                flex-direction: column !important;
                align-items: stretch !important;
                gap: 2px !important;
                border-top: 1px solid rgba(0,0,0,0.05) !important;
                box-shadow: 0 20px 40px rgba(0,0,0,0.08) !important;
            }
            
            .dark .nav-links {
                background: rgba(15,23,42,0.95) !important;
                border-top-color: rgba(255,255,255,0.05) !important;
            }
            
            .nav-links.open { display: flex !important; animation: slideDown 0.25s ease !important; }
            .nav-link { padding: 10px 14px !important; border-radius: 8px !important; font-size: 15px !important; }
            .nav-link.active::after { display: none !important; }
            .nav-link.active { background: rgba(37,99,235,0.1) !important; }
            
            .logo-text { font-size: 17px !important; }
            .logo-icon { width: 34px !important; height: 34px !important; font-size: 16px !important; border-radius: 10px !important; }
            
            .dropdown-menu {
                position: fixed !important;
                top: 64px !important;
                left: 8px !important;
                right: 8px !important;
                min-width: unset !important;
                max-width: unset !important;
                border-radius: 12px !important;
            }
            
            .btn-primary { padding: 6px 14px !important; font-size: 13px !important; }
            .btn-secondary { padding: 6px 12px !important; font-size: 13px !important; }
            .icon-btn { width: 36px !important; height: 36px !important; font-size: 17px !important; }
            .user-avatar { width: 36px !important; height: 36px !important; }
            
            .sidebar { width: 290px; right: -310px; }
            .user-avatar-sidebar { width: 40px; height: 40px; }
            .sidebar .sidebar-link { padding: 8px 12px; font-size: 13px; }
            
            .bottom-nav {
                bottom: 10px !important;
                left: 10px !important;
                right: 10px !important;
                height: 68px !important;
                padding: 6px 8px !important;
                border-radius: 22px !important;
            }
            
            .bottom-nav-item {
                min-width: 36px !important;
                padding: 2px 4px !important;
                gap: 1px !important;
            }
            
            .bottom-nav-item .nav-icon {
                width: 36px !important;
                height: 36px !important;
                border-radius: 10px !important;
            }
            
            .bottom-nav-item .nav-icon i { font-size: 17px !important; }
            .bottom-nav-item .nav-label { font-size: 8px !important; }
            
            .bottom-nav-item.explore-btn {
                flex: 1.3 !important;
                max-width: 70px !important;
                min-width: 50px !important;
                margin-top: -8px !important;
            }
            
            .explore-icon-wrapper { width: 52px !important; height: 52px !important; }
            .explore-ring { width: 52px !important; height: 52px !important; }
            .explore-ring i { font-size: 22px !important; }
            
            .bottom-nav-item .badge-nav {
                font-size: 7px !important;
                min-width: 14px !important;
                height: 14px !important;
                top: -3px !important;
                right: -3px !important;
                border-width: 1.5px !important;
            }
        }
        
        @media (max-width: 480px) {
            .logo-badge { display: none !important; }
            .header-inner { padding: 0 8px !important; }
        }
        
        @media (max-width: 380px) {
            .bottom-nav {
                bottom: 6px !important;
                left: 6px !important;
                right: 6px !important;
                height: 60px !important;
                padding: 4px 6px !important;
                border-radius: 18px !important;
            }
            
            .bottom-nav-item {
                min-width: 30px !important;
                padding: 2px 3px !important;
            }
            
            .bottom-nav-item .nav-icon {
                width: 30px !important;
                height: 30px !important;
                border-radius: 8px !important;
            }
            
            .bottom-nav-item .nav-icon i { font-size: 14px !important; }
            .bottom-nav-item .nav-label { font-size: 7px !important; }
            
            .bottom-nav-item.explore-btn {
                flex: 1.2 !important;
                max-width: 60px !important;
                min-width: 44px !important;
                margin-top: -6px !important;
            }
            
            .explore-icon-wrapper { width: 44px !important; height: 44px !important; }
            .explore-ring { width: 44px !important; height: 44px !important; }
            .explore-ring i { font-size: 18px !important; }
        }
        
        /* ============================================ */
        /* SCROLLBAR */
        /* ============================================ */
        .dropdown-list::-webkit-scrollbar { width: 4px !important; }
        .dropdown-list::-webkit-scrollbar-track { background: transparent !important; }
        .dropdown-list::-webkit-scrollbar-thumb {
            background: #cbd5e1 !important;
            border-radius: 4px !important;
        }
        .dark .dropdown-list::-webkit-scrollbar-thumb { background: #475569 !important; }
        
        .sidebar::-webkit-scrollbar { width: 4px !important; }
        .sidebar::-webkit-scrollbar-track { background: transparent !important; }
        .sidebar::-webkit-scrollbar-thumb {
            background: #cbd5e1 !important;
            border-radius: 4px !important;
        }
        .dark .sidebar::-webkit-scrollbar-thumb { background: #475569 !important; }
    </style>
</head>
<body>

<!-- ============================================ -->
<!-- TOAST CONTAINER -->
<!-- ============================================ -->
<div id="toast-container"></div>

<!-- ============================================ -->
<!-- SIDEBAR OVERLAY -->
<!-- ============================================ -->
<div id="sidebar-overlay" class="sidebar-overlay"></div>

<!-- ============================================ -->
<!-- SIDEBAR - القائمة الجانبية المحسّنة -->
<!-- ============================================ -->
<div id="sidebar" class="sidebar">
    <div class="sidebar-header">
        <div class="flex items-center gap-2">
            <div class="w-8 h-8 bg-primary-600 rounded-lg flex items-center justify-center">
                <i class="fas fa-hammer text-white text-sm"></i>
            </div>
            <span class="font-bold text-primary-800 dark:text-primary-400">مزاد البناء</span>
        </div>
        <button onclick="closeSidebar()" class="sidebar-close">
            <i class="fas fa-times"></i>
        </button>
    </div>
    
    <!-- ========================================== -->
    <!-- معلومات المستخدم المصغرة -->
    <!-- ========================================== -->
    <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0 && $current_user): ?>
    <div class="sidebar-user-profile">
        <div class="flex items-center gap-3">
            <div class="user-avatar-sidebar">
                <?php if (!empty($current_user['avatar']) && $current_user['avatar'] !== 'default-avatar.png'): ?>
                    <img src="<?= SITE_URL ?>/<?= htmlspecialchars($current_user['avatar']) ?>" alt="<?= htmlspecialchars($current_user['name']) ?>">
                <?php else: ?>
                    <div class="fallback-sidebar"><?= mb_substr($current_user['name'] ?? 'U', 0, 1) ?></div>
                <?php endif; ?>
            </div>
            <div>
                <p class="font-bold text-slate-800 dark:text-slate-100 text-sm"><?= htmlspecialchars($current_user['name'] ?? '') ?></p>
                <p class="text-xs text-slate-500 dark:text-slate-400"><?= htmlspecialchars($current_user['email'] ?? '') ?></p>
            </div>
        </div>
    </div>
    <div class="sidebar-divider"></div>
    <?php endif; ?>
    
    <!-- ========================================== -->
    <!-- الروابط الرئيسية -->
    <!-- ========================================== -->
    <div class="p-2 space-y-1">
        <!-- ✅ رابط الرئيسية المعدل حسب نوع المستخدم -->
        <a href="<?= $home_url ?>" class="sidebar-link <?= (basename($_SERVER['PHP_SELF']) == 'index.php' || basename($_SERVER['PHP_SELF']) == 'employer_dashboard.php' || basename($_SERVER['PHP_SELF']) == 'admin.php') ? 'bg-primary-50 dark:bg-primary-900/20 text-primary-600 dark:text-primary-400' : '' ?>">
            <i class="fas fa-home"></i> الرئيسية
        </a>
        <a href="<?= SITE_URL ?>/projects_list.php" class="sidebar-link <?= basename($_SERVER['PHP_SELF']) == 'projects_list.php' ? 'bg-primary-50 dark:bg-primary-900/20 text-primary-600 dark:text-primary-400' : '' ?>">
            <i class="fas fa-gavel"></i> العطاءات
        </a>
        <a href="<?= SITE_URL ?>/contractors_list.php" class="sidebar-link <?= basename($_SERVER['PHP_SELF']) == 'contractors_list.php' ? 'bg-primary-50 dark:bg-primary-900/20 text-primary-600 dark:text-primary-400' : '' ?>">
            <i class="fas fa-hard-hat"></i> المقاولون
        </a>
        <a href="<?= SITE_URL ?>/shops_list.php" class="sidebar-link <?= basename($_SERVER['PHP_SELF']) == 'shops_list.php' ? 'bg-primary-50 dark:bg-primary-900/20 text-primary-600 dark:text-primary-400' : '' ?>">
            <i class="fas fa-store"></i> محلات المواد
        </a>
        
        <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0): ?>
            <div class="sidebar-divider"></div>
            
            <!-- ========================================== -->
            <!-- خيارات خاصة حسب نوع المستخدم -->
            <!-- ========================================== -->
            <?php if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'employer'): ?>
            <a href="<?= SITE_URL ?>/post_project.php" class="sidebar-link text-emerald-600 dark:text-emerald-400">
                <i class="fas fa-plus-circle"></i> طرح مشروع جديد
            </a>
            <a href="<?= SITE_URL ?>/my_bids.php" class="sidebar-link <?= basename($_SERVER['PHP_SELF']) == 'my_bids.php' ? 'bg-primary-50 dark:bg-primary-900/20 text-primary-600 dark:text-primary-400' : '' ?>">
                <i class="fas fa-gavel"></i> عطاءاتي
            </a>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'shop'): ?>
            <a href="<?= SITE_URL ?>/add_product.php" class="sidebar-link text-purple-600 dark:text-purple-400">
                <i class="fas fa-plus-circle"></i> إضافة منتج
            </a>
            <a href="<?= SITE_URL ?>/my_products.php" class="sidebar-link <?= basename($_SERVER['PHP_SELF']) == 'my_products.php' ? 'bg-primary-50 dark:bg-primary-900/20 text-primary-600 dark:text-primary-400' : '' ?>">
                <i class="fas fa-boxes"></i> منتجاتي
            </a>
            <a href="<?= SITE_URL ?>/shop_orders.php" class="sidebar-link <?= basename($_SERVER['PHP_SELF']) == 'shop_orders.php' ? 'bg-primary-50 dark:bg-primary-900/20 text-primary-600 dark:text-primary-400' : '' ?>">
                <i class="fas fa-shopping-cart"></i> طلباتي
            </a>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'contractor'): ?>
            <a href="<?= SITE_URL ?>/my_bids.php" class="sidebar-link <?= basename($_SERVER['PHP_SELF']) == 'my_bids.php' ? 'bg-primary-50 dark:bg-primary-900/20 text-primary-600 dark:text-primary-400' : '' ?>">
                <i class="fas fa-gavel"></i> عطاءاتي
            </a>
            <a href="<?= SITE_URL ?>/alerts.php" class="sidebar-link text-blue-600 dark:text-blue-400 <?= basename($_SERVER['PHP_SELF']) == 'alerts.php' ? 'bg-primary-50 dark:bg-primary-900/20' : '' ?>">
                <i class="fas fa-bell"></i> تنبيهاتي
                <?php if ($alert_unread_count > 0): ?>
                <span class="badge-sidebar"><?= $alert_unread_count > 9 ? '9+' : $alert_unread_count ?></span>
                <?php endif; ?>
            </a>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin'): ?>
            <a href="<?= SITE_URL ?>/admin.php" class="sidebar-link text-red-600 dark:text-red-400 <?= basename($_SERVER['PHP_SELF']) == 'admin.php' ? 'bg-primary-50 dark:bg-primary-900/20' : '' ?>">
                <i class="fas fa-users-cog"></i> لوحة التحكم
            </a>
            <a href="<?= SITE_URL ?>/admin_documents.php" class="sidebar-link <?= basename($_SERVER['PHP_SELF']) == 'admin_documents.php' ? 'bg-primary-50 dark:bg-primary-900/20 text-primary-600 dark:text-primary-400' : '' ?>">
                <i class="fas fa-file-alt"></i> المستندات
            </a>
            <?php endif; ?>
            
            <!-- ========================================== -->
            <!-- روابط الحساب -->
            <!-- ========================================== -->
            <div class="sidebar-divider"></div>
            
            <div class="sidebar-section-title">الحساب</div>
            
            <a href="<?= SITE_URL ?>/profile.php" class="sidebar-link <?= basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'bg-primary-50 dark:bg-primary-900/20 text-primary-600 dark:text-primary-400' : '' ?>">
                <i class="fas fa-user-circle"></i> ملفي الشخصي
            </a>
            <a href="<?= SITE_URL ?>/my_conversations.php" class="sidebar-link <?= basename($_SERVER['PHP_SELF']) == 'my_conversations.php' ? 'bg-primary-50 dark:bg-primary-900/20 text-primary-600 dark:text-primary-400' : '' ?>">
                <i class="fas fa-comments"></i> المحادثات
                <?php if ($chat_unread_count > 0): ?>
                <span class="badge-sidebar" style="background:#2563eb;"><?= $chat_unread_count > 9 ? '9+' : $chat_unread_count ?></span>
                <?php endif; ?>
            </a>
            <a href="<?= SITE_URL ?>/notifications.php" class="sidebar-link <?= basename($_SERVER['PHP_SELF']) == 'notifications.php' ? 'bg-primary-50 dark:bg-primary-900/20 text-primary-600 dark:text-primary-400' : '' ?>">
                <i class="fas fa-bell"></i> جميع الإشعارات
                <?php if ($unread_count > 0): ?>
                <span class="badge-sidebar"><?= $unread_count > 9 ? '9+' : $unread_count ?></span>
                <?php endif; ?>
            </a>
            <a href="<?= SITE_URL ?>/settings.php" class="sidebar-link <?= basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'bg-primary-50 dark:bg-primary-900/20 text-primary-600 dark:text-primary-400' : '' ?>">
                <i class="fas fa-cog"></i> الإعدادات
            </a>
            
            <!-- ========================================== -->
            <!-- تسجيل الخروج -->
            <!-- ========================================== -->
            <div class="sidebar-divider"></div>
            <a href="<?= SITE_URL ?>/logout.php" class="sidebar-link text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-900/20">
                <i class="fas fa-sign-out-alt"></i> تسجيل خروج
            </a>
            
        <?php else: ?>
            <!-- ========================================== -->
            <!-- للزوار غير المسجلين -->
            <!-- ========================================== -->
            <div class="sidebar-divider"></div>
            <a href="<?= SITE_URL ?>/login.php" class="sidebar-link bg-primary-50 text-primary-700 dark:bg-primary-900/30 dark:text-primary-400">
                <i class="fas fa-sign-in-alt"></i> تسجيل دخول
            </a>
            <a href="<?= SITE_URL ?>/register.php" class="sidebar-link bg-primary-600 text-white hover:bg-primary-700 hover:text-white dark:bg-primary-700 dark:hover:bg-primary-800">
                <i class="fas fa-user-plus"></i> إنشاء حساب
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- ============================================ -->
<!-- MAIN HEADER -->
<!-- ============================================ -->
<header class="header-glass" id="mainHeader">
    <div class="header-inner">
        
        <!-- LEFT: LOGO + MENU TOGGLE -->
        <div class="flex items-center gap-2">
            <button class="menu-toggle" id="menuToggle" aria-label="القائمة">
                <i class="fas fa-bars"></i>
            </button>
            
            <!-- ✅ رابط الشعار المعدل حسب نوع المستخدم -->
            <a href="<?= $home_url ?>" class="logo">
                <div class="logo-icon"><i class="fas fa-hammer"></i></div>
                <span class="logo-text">مزاد <span>البناء</span> <span class="logo-badge">PRO</span></span>
            </a>
        </div>
        
        <!-- CENTER: NAVIGATION LINKS -->
        <nav class="nav-links" id="navLinks">
            <!-- ✅ رابط الرئيسية المعدل حسب نوع المستخدم -->
            <a href="<?= $home_url ?>" class="nav-link <?= (basename($_SERVER['PHP_SELF']) == 'index.php' || basename($_SERVER['PHP_SELF']) == 'employer_dashboard.php' || basename($_SERVER['PHP_SELF']) == 'admin.php') ? 'active' : '' ?>">
                <i class="fas fa-home ml-1"></i> الرئيسية
            </a>
            <a href="<?= SITE_URL ?>/projects_list.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'projects_list.php' ? 'active' : '' ?>">
                <i class="fas fa-gavel ml-1"></i> العطاءات
            </a>
            <a href="<?= SITE_URL ?>/contractors_list.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'contractors_list.php' ? 'active' : '' ?>">
                <i class="fas fa-hard-hat ml-1"></i> المقاولون
            </a>
            <a href="<?= SITE_URL ?>/shops_list.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'shops_list.php' ? 'active' : '' ?>">
                <i class="fas fa-store ml-1"></i> المحلات
            </a>
            
            <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0): ?>
                <?php if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'employer'): ?>
                    <a href="<?= SITE_URL ?>/post_project.php" class="btn-primary mt-2 md:mt-0">
                        <i class="fas fa-plus-circle"></i> مشروع جديد
                    </a>
                <?php elseif (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'shop'): ?>
                    <a href="<?= SITE_URL ?>/add_product.php" class="btn-primary mt-2 md:mt-0" style="background: linear-gradient(135deg, #7c3aed, #6d28d9) !important; box-shadow: 0 4px 14px rgba(124,58,237,0.25) !important;">
                        <i class="fas fa-plus-circle"></i> إضافة منتج
                    </a>
                <?php endif; ?>
            <?php endif; ?>
        </nav>
        
        <!-- RIGHT: ACTIONS -->
        <div class="flex items-center gap-1 md:gap-2">
            
            <!-- Theme Toggle -->
            <button class="icon-btn" id="themeToggle" aria-label="تبديل الوضع">
                <i class="fas fa-moon" id="themeIcon"></i>
            </button>
            
            <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0 && $current_user): ?>
                
                <!-- Notifications -->
                <div style="position:relative;">
                    <button class="icon-btn" id="notifToggle" aria-label="الإشعارات">
                        <i class="fas fa-bell"></i>
                        <?php if ($unread_count > 0): ?>
                            <span class="badge"><?= $unread_count > 9 ? '9+' : $unread_count ?></span>
                        <?php endif; ?>
                    </button>
                    
                    <!-- Notifications Dropdown -->
                    <div class="dropdown-menu" id="notifDropdown">
                        <div class="dropdown-header">
                            <h3>الإشعارات</h3>
                            <?php if ($unread_count > 0): ?>
                                <button class="mark-all" onclick="markAllRead()">تعيين الكل كمقروء</button>
                            <?php endif; ?>
                        </div>
                        <div class="dropdown-list" id="notifList">
                            <?php if (empty($user_notifications)): ?>
                                <div class="dropdown-empty">
                                    <i class="fas fa-bell-slash"></i>
                                    <p>لا توجد إشعارات</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($user_notifications as $notif): ?>
                                    <a href="<?= $notif['link'] ?? '#' ?>" class="dropdown-item <?= $notif['is_read'] ? '' : 'unread' ?>" onclick="markNotifRead(<?= $notif['id'] ?>, this)">
                                        <div class="icon <?= $notif['type'] === 'success' ? 'bg-green-100 dark:bg-green-900/30 text-green-600 dark:text-green-400' : '' ?> <?= $notif['type'] === 'error' ? 'bg-red-100 dark:bg-red-900/30 text-red-600 dark:text-red-400' : '' ?> <?= $notif['type'] === 'warning' ? 'bg-amber-100 dark:bg-amber-900/30 text-amber-600 dark:text-amber-400' : '' ?> <?= $notif['type'] === 'info' ? 'bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400' : '' ?>">
                                            <?php
                                                $icon = 'fa-bell';
                                                if ($notif['type'] === 'success') $icon = 'fa-check-circle';
                                                if ($notif['type'] === 'error') $icon = 'fa-times-circle';
                                                if ($notif['type'] === 'warning') $icon = 'fa-exclamation-triangle';
                                            ?>
                                            <i class="fas <?= $icon ?>"></i>
                                        </div>
                                        <div class="content">
                                            <div class="title"><?= clean($notif['title']) ?></div>
                                            <div class="message"><?= clean($notif['message']) ?></div>
                                            <div class="time"><?= timeAgo($notif['created_at']) ?></div>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Messages -->
                <a href="<?= SITE_URL ?>/my_conversations.php" class="icon-btn" style="text-decoration:none;">
                    <i class="fas fa-comments"></i>
                    <?php if ($chat_unread_count > 0): ?>
                        <span class="badge" style="background: #2563eb;"><?= $chat_unread_count > 9 ? '9+' : $chat_unread_count ?></span>
                    <?php endif; ?>
                </a>
                
                <!-- Contractor Alerts -->
                <?php if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'contractor'): ?>
                    <a href="<?= SITE_URL ?>/alerts.php" class="icon-btn" style="text-decoration:none;">
                        <i class="fas fa-bell-exclamation"></i>
                        <?php if ($alert_unread_count > 0): ?>
                            <span class="badge" style="background: #f59e0b;"><?= $alert_unread_count > 9 ? '9+' : $alert_unread_count ?></span>
                        <?php endif; ?>
                    </a>
                <?php endif; ?>
                
                <!-- ========================================== -->
                <!-- صورة البروفايل - رابط مباشر -->
                <!-- ========================================== -->
                <a href="<?= SITE_URL ?>/profile.php" class="user-avatar" aria-label="الملف الشخصي">
                    <?php if (!empty($current_user['avatar']) && $current_user['avatar'] !== 'default-avatar.png'): ?>
                        <img src="<?= SITE_URL ?>/<?= $current_user['avatar'] ?>" alt="<?= clean($current_user['name']) ?>">
                    <?php else: ?>
                        <div class="fallback"><?= mb_substr($current_user['name'] ?? 'U', 0, 1) ?></div>
                    <?php endif; ?>
                </a>
                
            <?php else: ?>
                <!-- Guest Actions -->
                <a href="<?= SITE_URL ?>/login.php" class="btn-secondary">تسجيل الدخول</a>
                <a href="<?= SITE_URL ?>/register.php" class="btn-primary">انضم الآن</a>
            <?php endif; ?>
            
        </div>
    </div>
</header>

<!-- ============================================ -->
<!-- DROPDOWN OVERLAY (للإشعارات فقط) -->
<!-- ============================================ -->
<div class="dropdown-overlay" id="dropdownOverlay"></div>

<!-- ============================================ -->
<!-- BOTTOM NAVIGATION -->
<!-- ============================================ -->
<nav class="bottom-nav md:hidden" id="bottomNav" role="navigation" aria-label="القائمة السفلية">
    
    <?php if (!isLoggedIn()): ?>
        <!-- Guests -->
        <a href="<?= SITE_URL ?>/" class="bottom-nav-item <?= basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : '' ?>">
            <span class="nav-icon"><i class="fas fa-home"></i></span>
            <span class="nav-label">الرئيسية</span>
        </a>
        
        <a href="<?= SITE_URL ?>/projects_list.php" class="bottom-nav-item <?= basename($_SERVER['PHP_SELF']) == 'projects_list.php' ? 'active' : '' ?>">
            <span class="nav-icon"><i class="fas fa-gavel"></i></span>
            <span class="nav-label">العطاءات</span>
        </a>
        
        <a href="<?= SITE_URL ?>/" class="bottom-nav-item explore-btn <?= basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : '' ?>">
            <span class="explore-icon-wrapper">
                <span class="explore-ring"><i class="fas fa-compass"></i></span>
            </span>
            <span class="nav-label explore-label">استكشاف</span>
        </a>
        
        <a href="<?= SITE_URL ?>/contractors_list.php" class="bottom-nav-item <?= basename($_SERVER['PHP_SELF']) == 'contractors_list.php' ? 'active' : '' ?>">
            <span class="nav-icon"><i class="fas fa-hard-hat"></i></span>
            <span class="nav-label">مقاولون</span>
        </a>
        
        <a href="<?= SITE_URL ?>/shops_list.php" class="bottom-nav-item <?= basename($_SERVER['PHP_SELF']) == 'shops_list.php' ? 'active' : '' ?>">
            <span class="nav-icon"><i class="fas fa-store"></i></span>
            <span class="nav-label">محلات</span>
        </a>
        
    <?php elseif (isAdmin()): ?>
        <!-- Admin -->
        <a href="<?= $home_url ?>" class="bottom-nav-item <?= basename($_SERVER['PHP_SELF']) == 'admin.php' ? 'active' : '' ?>">
            <span class="nav-icon"><i class="fas fa-home"></i></span>
            <span class="nav-label">الرئيسية</span>
        </a>
        
        <a href="<?= SITE_URL ?>/admin.php" class="bottom-nav-item <?= basename($_SERVER['PHP_SELF']) == 'admin.php' ? 'active' : '' ?>">
            <span class="nav-icon"><i class="fas fa-users-cog"></i></span>
            <span class="nav-label">التحكم</span>
        </a>
        
        <a href="<?= SITE_URL ?>/" class="bottom-nav-item explore-btn <?= basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : '' ?>">
            <span class="explore-icon-wrapper">
                <span class="explore-ring"><i class="fas fa-compass"></i></span>
            </span>
            <span class="nav-label explore-label">استكشاف</span>
        </a>
        
        <a href="<?= SITE_URL ?>/profile.php" class="bottom-nav-item <?= basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : '' ?>">
            <span class="nav-icon"><i class="fas fa-user-shield"></i></span>
            <span class="nav-label">بروفايل</span>
        </a>
        
        <a href="<?= SITE_URL ?>/logout.php" class="bottom-nav-item logout-btn">
            <span class="nav-icon"><i class="fas fa-sign-out-alt"></i></span>
            <span class="nav-label">خروج</span>
        </a>
        
    <?php elseif (getUserType() === 'employer'): ?>
        <!-- Employer -->
        <a href="<?= $home_url ?>" class="bottom-nav-item <?= basename($_SERVER['PHP_SELF']) == 'employer_dashboard.php' ? 'active' : '' ?>">
            <span class="nav-icon"><i class="fas fa-home"></i></span>
            <span class="nav-label">الرئيسية</span>
        </a>
        
        <a href="<?= SITE_URL ?>/my_bids.php?filter=accepted" class="bottom-nav-item <?= basename($_SERVER['PHP_SELF']) == 'my_bids.php' ? 'active' : '' ?>">
            <span class="nav-icon"><i class="fas fa-project-diagram"></i></span>
            <span class="nav-label">مشاريعي</span>
        </a>
        
        <a href="<?= SITE_URL ?>/" class="bottom-nav-item explore-btn <?= basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : '' ?>">
            <span class="explore-icon-wrapper">
                <span class="explore-ring"><i class="fas fa-compass"></i></span>
            </span>
            <span class="nav-label explore-label">استكشاف</span>
        </a>
        
        <a href="<?= SITE_URL ?>/shops_list.php" class="bottom-nav-item <?= basename($_SERVER['PHP_SELF']) == 'shops_list.php' ? 'active' : '' ?>">
            <span class="nav-icon"><i class="fas fa-store"></i></span>
            <span class="nav-label">مواد بناء</span>
        </a>
        
        <a href="<?= SITE_URL ?>/profile.php" class="bottom-nav-item <?= basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : '' ?>">
            <span class="nav-icon"><i class="fas fa-user"></i></span>
            <span class="nav-label">بروفايل</span>
        </a>
        
    <?php elseif (getUserType() === 'contractor'): ?>
        <!-- Contractor -->
        <a href="<?= SITE_URL ?>/my_bids.php" class="bottom-nav-item <?= basename($_SERVER['PHP_SELF']) == 'my_bids.php' ? 'active' : '' ?>">
            <span class="nav-icon">
                <i class="fas fa-gavel"></i>
                <?php 
                    $active_bids = 0;
                    try {
                        $stmt = $pdo->prepare("SELECT COUNT(*) FROM bids WHERE contractor_id = ? AND status = 'pending'");
                        $stmt->execute([$_SESSION['user_id']]);
                        $active_bids = (int)$stmt->fetchColumn();
                    } catch (Exception $e) {}
                    if ($active_bids > 0): 
                ?>
                <span class="badge-nav"><?= $active_bids > 9 ? '9+' : $active_bids ?></span>
                <?php endif; ?>
            </span>
            <span class="nav-label">عطاءاتي</span>
        </a>
        
        <a href="<?= SITE_URL ?>/shops_list.php" class="bottom-nav-item <?= basename($_SERVER['PHP_SELF']) == 'shops_list.php' ? 'active' : '' ?>">
            <span class="nav-icon"><i class="fas fa-store"></i></span>
            <span class="nav-label">مواد بناء</span>
        </a>
        
        <a href="<?= SITE_URL ?>/" class="bottom-nav-item explore-btn <?= basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : '' ?>">
            <span class="explore-icon-wrapper">
                <span class="explore-ring"><i class="fas fa-compass"></i></span>
            </span>
            <span class="nav-label explore-label">استكشاف</span>
        </a>
        
        <a href="<?= SITE_URL ?>/alerts.php" class="bottom-nav-item <?= basename($_SERVER['PHP_SELF']) == 'alerts.php' ? 'active' : '' ?>">
            <span class="nav-icon">
                <i class="fas fa-bell"></i>
                <?php if ($alert_unread_count > 0): ?>
                <span class="badge-nav badge-amber"><?= $alert_unread_count > 9 ? '9+' : $alert_unread_count ?></span>
                <?php endif; ?>
            </span>
            <span class="nav-label">تنبيهات</span>
        </a>
        
        <a href="<?= SITE_URL ?>/profile.php" class="bottom-nav-item <?= basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : '' ?>">
            <span class="nav-icon"><i class="fas fa-user"></i></span>
            <span class="nav-label">بروفايل</span>
        </a>
        
    <?php elseif (getUserType() === 'shop'): ?>
        <!-- Shop -->
        <a href="<?= SITE_URL ?>/" class="bottom-nav-item <?= basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : '' ?>">
            <span class="nav-icon"><i class="fas fa-home"></i></span>
            <span class="nav-label">الرئيسية</span>
        </a>
        
        <a href="<?= SITE_URL ?>/my_products.php" class="bottom-nav-item <?= basename($_SERVER['PHP_SELF']) == 'my_products.php' ? 'active' : '' ?>">
            <span class="nav-icon">
                <i class="fas fa-boxes"></i>
                <?php 
                    $product_count = 0;
                    try {
                        $stmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE shop_id = ?");
                        $stmt->execute([$_SESSION['user_id']]);
                        $product_count = (int)$stmt->fetchColumn();
                    } catch (Exception $e) {}
                    if ($product_count > 0): 
                ?>
                <span class="badge-nav badge-blue"><?= $product_count > 9 ? '9+' : $product_count ?></span>
                <?php endif; ?>
            </span>
            <span class="nav-label">منتجاتي</span>
        </a>
        
        <a href="<?= SITE_URL ?>/" class="bottom-nav-item explore-btn <?= basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : '' ?>">
            <span class="explore-icon-wrapper">
                <span class="explore-ring"><i class="fas fa-compass"></i></span>
            </span>
            <span class="nav-label explore-label">استكشاف</span>
        </a>
        
        <a href="<?= SITE_URL ?>/shop_orders.php" class="bottom-nav-item <?= basename($_SERVER['PHP_SELF']) == 'shop_orders.php' ? 'active' : '' ?>">
            <span class="nav-icon">
                <i class="fas fa-shopping-cart"></i>
                <?php 
                    $new_orders = 0;
                    try {
                        $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE shop_id = ? AND status = 'pending'");
                        $stmt->execute([$_SESSION['user_id']]);
                        $new_orders = (int)$stmt->fetchColumn();
                    } catch (Exception $e) {}
                    if ($new_orders > 0): 
                ?>
                <span class="badge-nav"><?= $new_orders > 9 ? '9+' : $new_orders ?></span>
                <?php endif; ?>
            </span>
            <span class="nav-label">طلباتي</span>
        </a>
        
        <a href="<?= SITE_URL ?>/profile.php" class="bottom-nav-item <?= basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : '' ?>">
            <span class="nav-icon"><i class="fas fa-user"></i></span>
            <span class="nav-label">بروفايل</span>
        </a>
        
    <?php endif; ?>
    
</nav>

<!-- ============================================ -->
<!-- JAVASCRIPT -->
<!-- ============================================ -->
<script>
// ============================================
// DOM ELEMENTS
// ============================================
const header = document.getElementById('mainHeader');
const menuToggle = document.getElementById('menuToggle');
const navLinks = document.getElementById('navLinks');
const notifToggle = document.getElementById('notifToggle');
const notifDropdown = document.getElementById('notifDropdown');
const dropdownOverlay = document.getElementById('dropdownOverlay');
const themeToggle = document.getElementById('themeToggle');
const themeIcon = document.getElementById('themeIcon');
const bottomNav = document.getElementById('bottomNav');

// ============================================
// SIDEBAR CONTROLS
// ============================================
const sidebar = document.getElementById('sidebar');
const sidebarOverlay = document.getElementById('sidebar-overlay');

function openSidebar() {
    if (sidebar) {
        sidebar.classList.add('open');
        if (sidebarOverlay) sidebarOverlay.classList.add('open');
        document.body.style.overflow = 'hidden';
    }
}

function closeSidebar() {
    if (sidebar) {
        sidebar.classList.remove('open');
        if (sidebarOverlay) sidebarOverlay.classList.remove('open');
        document.body.style.overflow = '';
    }
}

// فتح القائمة عند الضغط على زر القائمة
menuToggle?.addEventListener('click', function(e) {
    e.stopPropagation();
    openSidebar();
});

// ============================================
// DROPDOWN CONTROLLER
// ============================================
let activeDropdown = null;

function closeAllDropdowns() {
    notifDropdown?.classList.remove('open');
    dropdownOverlay?.classList.remove('open');
    activeDropdown = null;
}

function toggleDropdown(dropdown, event) {
    event?.stopPropagation();
    
    if (activeDropdown === dropdown) {
        closeAllDropdowns();
        return;
    }
    
    closeAllDropdowns();
    dropdown?.classList.add('open');
    dropdownOverlay?.classList.add('open');
    activeDropdown = dropdown;
}

notifToggle?.addEventListener('click', function(e) {
    toggleDropdown(notifDropdown, e);
    if (notifDropdown.classList.contains('open')) {
        fetchNotifications();
    }
});

// ============================================
// CLOSE ON CLICK OUTSIDE - إغلاق عند الضغط خارج العنصر
// ============================================
document.addEventListener('click', function(e) {
    // إغلاق الإشعارات
    if (!e.target.closest('.dropdown-menu') && !e.target.closest('.icon-btn')) {
        closeAllDropdowns();
    }
    
    // إغلاق القائمة الجانبية عند الضغط خارجها
    if (sidebar && sidebar.classList.contains('open')) {
        if (!sidebar.contains(e.target) && !e.target.closest('.menu-toggle') && !e.target.closest('[onclick="openSidebar()"]')) {
            closeSidebar();
        }
    }
});

// إغلاق القائمة الجانبية عند الضغط على الـ overlay
sidebarOverlay?.addEventListener('click', closeSidebar);

// ============================================
// CLOSE ON LINK CLICK
// ============================================
document.querySelectorAll('.nav-link').forEach(link => {
    link.addEventListener('click', () => navLinks?.classList.remove('open'));
});

document.querySelectorAll('.sidebar-link').forEach(link => {
    link.addEventListener('click', () => {
        setTimeout(closeSidebar, 200);
    });
});

// ============================================
// KEYBOARD SHORTCUTS
// ============================================
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeAllDropdowns();
        navLinks?.classList.remove('open');
        closeSidebar();
    }
    if (e.ctrlKey && e.key === 'k') {
        e.preventDefault();
        themeToggle?.click();
    }
});

// ============================================
// HEADER SCROLL EFFECT
// ============================================
let lastScroll = 0;
window.addEventListener('scroll', function() {
    const currentScroll = window.pageYOffset || document.documentElement.scrollTop;
    if (currentScroll > 20) {
        header?.classList.add('scrolled');
    } else {
        header?.classList.remove('scrolled');
    }
    lastScroll = currentScroll;
}, { passive: true });

// ============================================
// THEME TOGGLE
// ============================================
let currentTheme = localStorage.getItem('theme') || 'light';

function setTheme(theme) {
    currentTheme = theme;
    localStorage.setItem('theme', theme);
    document.documentElement.classList.toggle('dark', theme === 'dark');
    themeIcon.className = theme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
    document.cookie = `theme=${theme}; path=/; max-age=${60*60*24*365}`;
}

themeToggle?.addEventListener('click', function() {
    setTheme(currentTheme === 'dark' ? 'light' : 'dark');
});

setTheme(currentTheme);

// ============================================
// NOTIFICATIONS
// ============================================
function fetchNotifications() {
    fetch('<?= SITE_URL ?>/api.php?action=get_notifications')
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success' && data.data) {
                const list = document.getElementById('notifList');
                if (list) {
                    if (data.data.length > 0) {
                        list.innerHTML = data.data.map(notif => `
                            <a href="${notif.link || '#'}" class="dropdown-item ${notif.is_read == 0 ? 'unread' : ''}" onclick="markNotifRead(${notif.id}, this)">
                                <div class="icon ${notif.type === 'success' ? 'bg-green-100 dark:bg-green-900/30 text-green-600 dark:text-green-400' : ''} ${notif.type === 'error' ? 'bg-red-100 dark:bg-red-900/30 text-red-600 dark:text-red-400' : ''} ${notif.type === 'warning' ? 'bg-amber-100 dark:bg-amber-900/30 text-amber-600 dark:text-amber-400' : ''} ${notif.type === 'info' ? 'bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400' : ''}">
                                    <i class="fas ${notif.icon || 'fa-bell'}"></i>
                                </div>
                                <div class="content">
                                    <div class="title">${notif.title}</div>
                                    <div class="message">${notif.message}</div>
                                    <div class="time">${notif.time_ago}</div>
                                </div>
                            </a>
                        `).join('');
                    } else {
                        list.innerHTML = `
                            <div class="dropdown-empty">
                                <i class="fas fa-bell-slash"></i>
                                <p>لا توجد إشعارات</p>
                            </div>
                        `;
                    }
                }
                const unread = data.data.filter(n => n.is_read == 0).length;
                updateBadge(unread);
            }
        })
        .catch(error => console.error('Error fetching notifications:', error));
}

function updateBadge(count) {
    const badge = document.querySelector('#notifToggle .badge');
    if (badge) {
        if (count > 0) {
            badge.textContent = count > 9 ? '9+' : count;
            badge.style.display = 'flex';
        } else {
            badge.style.display = 'none';
        }
    }
}

function markNotifRead(notifId, element) {
    fetch('<?= SITE_URL ?>/api.php?action=mark_read&notif_id=' + notifId)
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                element?.classList.remove('unread');
                const badge = document.querySelector('#notifToggle .badge');
                if (badge && badge.style.display !== 'none') {
                    let count = parseInt(badge.textContent) - 1;
                    if (count > 0) {
                        badge.textContent = count > 9 ? '9+' : count;
                    } else {
                        badge.style.display = 'none';
                    }
                }
            }
        })
        .catch(error => console.error('Error marking notification as read:', error));
}

function markAllRead() {
    fetch('<?= SITE_URL ?>/api.php?action=mark_all_read')
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                document.querySelectorAll('#notifList .unread').forEach(el => el.classList.remove('unread'));
                updateBadge(0);
                showToast('تم تعيين جميع الإشعارات كمقروءة', 'success');
            }
        })
        .catch(error => console.error('Error marking all as read:', error));
}

// ============================================
// TOAST SYSTEM
// ============================================
function showToast(message, type = 'success') {
    const container = document.getElementById('toast-container');
    if (!container) return;
    
    const types = { success: 'toast-success', error: 'toast-error', warning: 'toast-warning', info: 'toast-info' };
    const icons = { success: 'fa-check-circle', error: 'fa-times-circle', warning: 'fa-exclamation-triangle', info: 'fa-info-circle' };
    
    const toast = document.createElement('div');
    toast.className = `toast ${types[type] || types.info}`;
    toast.innerHTML = `<i class="fas ${icons[type] || icons.info}"></i> ${message}`;
    container.appendChild(toast);
    
    setTimeout(() => {
        toast.style.animation = 'slideOut 0.3s ease forwards';
        setTimeout(() => toast.remove(), 300);
    }, 4000);
}

// ============================================
// BOTTOM NAV CONTROLLER
// ============================================
class BottomNavController {
    constructor() {
        this.nav = document.getElementById('bottomNav');
        this.lastScroll = 0;
        this.hideTimeout = null;
        this.isHidden = false;
        this.isVisible = true;
        
        if (this.nav) {
            this.init();
        }
    }
    
    init() {
        this.handleScroll = this.handleScroll.bind(this);
        window.addEventListener('scroll', this.handleScroll, { passive: true });
        this.addRippleEffect();
        this.updateActiveState();
    }
    
    handleScroll() {
        const currentScroll = window.pageYOffset || document.documentElement.scrollTop;
        
        if (currentScroll > this.lastScroll && currentScroll > 80) {
            this.hide();
        } else if (currentScroll < this.lastScroll) {
            this.show();
        }
        
        this.lastScroll = currentScroll;
    }
    
    hide() {
        if (this.isHidden) return;
        this.isHidden = true;
        this.isVisible = false;
        this.nav.classList.add('hidden-nav');
        if (this.hideTimeout) clearTimeout(this.hideTimeout);
    }
    
    show() {
        if (!this.isHidden) return;
        this.isHidden = false;
        this.isVisible = true;
        this.nav.classList.remove('hidden-nav');
        if (this.hideTimeout) clearTimeout(this.hideTimeout);
        
        this.hideTimeout = setTimeout(() => this.hide(), 4000);
    }
    
    addRippleEffect() {
        this.nav.querySelectorAll('.bottom-nav-item').forEach(item => {
            item.addEventListener('click', function(e) {
                const rect = this.getBoundingClientRect();
                const size = Math.max(rect.width, rect.height);
                const x = e.clientX - rect.left - size / 2;
                const y = e.clientY - rect.top - size / 2;
                
                const ripple = document.createElement('span');
                ripple.className = 'ripple-effect';
                ripple.style.width = ripple.style.height = size + 'px';
                ripple.style.left = x + 'px';
                ripple.style.top = y + 'px';
                
                this.appendChild(ripple);
                setTimeout(() => ripple.remove(), 600);
            });
        });
    }
    
    updateActiveState() {
        const currentPath = window.location.pathname;
        const currentPage = currentPath.split('/').pop() || 'index.php';
        
        this.nav.querySelectorAll('.bottom-nav-item').forEach(item => {
            const href = item.getAttribute('href');
            if (href) {
                const hrefPage = href.split('/').pop();
                if (hrefPage === currentPage || (currentPage === '' && hrefPage === 'index.php')) {
                    item.classList.add('active');
                } else {
                    item.classList.remove('active');
                }
            }
        });
    }
}

// ============================================
// INITIALIZE
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    const bottomNavController = new BottomNavController();
    
    <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0): ?>
    setInterval(fetchNotifications, 30000);
    <?php endif; ?>
    
    console.log('🚀 Header loaded successfully!');
    console.log('📱 Sidebar with logout button is ready!');
    console.log('🔄 Sidebar closes when clicking outside');
});
</script>

</body>
</html>
<?php ob_end_flush(); ?>