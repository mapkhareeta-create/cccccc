<?php
require_once 'config.php';

// جلب الفلاتر من URL
$search = isset($_GET['search']) ? clean($_GET['search']) : '';
$specialization = isset($_GET['specialization']) ? clean($_GET['specialization']) : '';
$contractor_class = isset($_GET['class']) ? clean($_GET['class']) : '';
$country_id = isset($_GET['country_id']) ? (int)$_GET['country_id'] : 0;
$city_id = isset($_GET['city_id']) ? (int)$_GET['city_id'] : 0;
$is_verified = isset($_GET['verified']) ? (int)$_GET['verified'] : 0;
$min_rating = isset($_GET['min_rating']) ? (float)$_GET['min_rating'] : 0;

// بناء استعلام البحث
$sql = "
    SELECT DISTINCT u.*, 
           c.name_ar as country_name, 
           ci.name_ar as city_name
    FROM users u
    LEFT JOIN countries c ON u.country_id = c.id
    LEFT JOIN cities ci ON u.city_id = ci.id
    WHERE u.user_type = 'contractor' AND (u.status = 'active' OR u.status IS NULL)
";

$params = [];

if (!empty($search)) {
    $sql .= " AND u.name LIKE ?";
    $params[] = "%$search%";
}

if (!empty($specialization)) {
    $sql .= " AND EXISTS (SELECT 1 FROM contractor_specs WHERE user_id = u.id AND spec = ?)";
    $params[] = $specialization;
}

if (!empty($contractor_class) && $contractor_class != 'all') {
    $sql .= " AND u.contractor_class = ?";
    $params[] = $contractor_class;
}

if ($country_id > 0) {
    $sql .= " AND u.country_id = ?";
    $params[] = $country_id;
}

if ($city_id > 0) {
    $sql .= " AND u.city_id = ?";
    $params[] = $city_id;
}

if ($is_verified == 1) {
    $sql .= " AND u.verification_status = 'approved'";
}

if ($min_rating > 0) {
    $sql .= " AND u.rating >= ?";
    $params[] = $min_rating;
}

$sql .= " ORDER BY u.is_featured DESC, u.rating DESC, u.created_at DESC LIMIT 30";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $contractors = $stmt->fetchAll();
} catch (PDOException $e) {
    $contractors = [];
}

// جلب التخصصات للفلتر
$categories = getProjectCategories();
$all_specializations = array_keys($categories);

// جلب الدول
$countries = getCountries();
$cities = [];
if ($country_id > 0) {
    $cities = getCities($country_id);
}

$has_active_filters = ($search || $specialization || ($contractor_class != '' && $contractor_class != 'all') || $country_id > 0 || $city_id > 0 || $is_verified == 1 || $min_rating > 0);

$contractor_classes = [
    'all' => ['name' => 'جميع التصنيفات', 'icon' => 'fa-chart-line', 'color' => 'gray', 'badge' => 'bg-gray-100 text-gray-600'],
    'A' => ['name' => 'تصنيف أ', 'icon' => 'fa-crown', 'color' => 'green', 'badge' => 'bg-green-100 text-green-700'],
    'B' => ['name' => 'تصنيف ب', 'icon' => 'fa-medal', 'color' => 'blue', 'badge' => 'bg-blue-100 text-blue-700'],
    'C' => ['name' => 'تصنيف ج', 'icon' => 'fa-chart-line', 'color' => 'amber', 'badge' => 'bg-amber-100 text-amber-700'],
    'D' => ['name' => 'تصنيف د', 'icon' => 'fa-seedling', 'color' => 'orange', 'badge' => 'bg-orange-100 text-orange-700']
];

$page_title = 'قائمة المقاولين - مزاد البناء';
include 'includes/header.php';
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>قائمة المقاولين - مزاد البناء</title>
    <script src="https://cdn.tailwindcss.com/3.4.17"></script>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;900&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/lucide@0.263.0/dist/umd/lucide.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        * { 
            font-family: 'DM Sans', sans-serif; 
            box-sizing: border-box;
        }
        
        body {
            min-height: 100vh;
            margin: 0;
            padding: 0;
            background: #1a0e0a;
            position: relative;
            overflow-x: hidden;
        }
        
        .main-content {
            position: relative;
            z-index: 1;
            padding: 16px 0 60px 0;
            min-height: 100vh;
        }
        
        /* ============================================ */
        /* كروت المقاولين - تصميم خفيف مثل تطبيق عقار */
        /* ============================================ */
        .contractor-card {
            background: #ffffff;
            border-radius: 16px;
            border: 1px solid #e8edf2;
            transition: all 0.25s ease;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
            cursor: pointer;
            overflow: hidden;
            padding: 16px;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .contractor-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
            border-color: #d0d8e0;
        }
        
        .contractor-card:active {
            transform: scale(0.98);
        }
        
        /* ===== صورة المقاول ===== */
        .contractor-avatar {
            width: 52px;
            height: 52px;
            border-radius: 12px;
            overflow: hidden;
            flex-shrink: 0;
            background: #e8edf2;
            border: 1px solid #e8edf2;
        }
        
        @media (max-width: 480px) {
            .contractor-avatar {
                width: 44px;
                height: 44px;
                border-radius: 10px;
            }
        }
        
        .contractor-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .contractor-avatar .no-image {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #dbeafe;
            color: #2563eb;
            font-size: 20px;
            font-weight: 700;
        }
        
        /* ===== الأسماء والنصوص ===== */
        .contractor-name {
            color: #0f172a;
            font-weight: 700;
            font-size: 15px;
            line-height: 1.3;
        }
        
        @media (max-width: 480px) {
            .contractor-name {
                font-size: 13px;
            }
        }
        
        .contractor-badge {
            display: inline-flex;
            align-items: center;
            gap: 3px;
            padding: 1px 10px;
            border-radius: 20px;
            font-size: 9px;
            font-weight: 600;
            white-space: nowrap;
        }
        
        .contractor-location {
            color: #94a3b8;
            font-size: 11px;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        
        .contractor-location i {
            font-size: 12px;
        }
        
        /* ===== التخصصات ===== */
        .contractor-spec {
            display: inline-flex;
            padding: 2px 10px;
            border-radius: 20px;
            font-size: 9px;
            font-weight: 500;
            background: #f1f5f9;
            color: #475569;
        }
        
        .contractor-spec-more {
            color: #94a3b8;
            font-size: 9px;
            font-weight: 500;
        }
        
        /* ===== الأزرار ===== */
        .btn-view-profile {
            background: #2563eb;
            color: #ffffff;
            font-weight: 600;
            font-size: 11px;
            padding: 7px 16px;
            border-radius: 10px;
            text-align: center;
            transition: all 0.25s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
            flex: 1;
            text-decoration: none;
            border: none;
        }
        
        .btn-view-profile:hover {
            background: #1d4ed8;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.25);
        }
        
        .btn-chat {
            background: #f1f5f9;
            color: #475569;
            font-size: 11px;
            padding: 7px 12px;
            border-radius: 10px;
            text-align: center;
            transition: all 0.25s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
            text-decoration: none;
            border: 1px solid #e8edf2;
        }
        
        .btn-chat:hover {
            background: #e8edf2;
            transform: translateY(-1px);
        }
        
        /* ===== تسجيل الدخول ===== */
        .login-prompt {
            background: #f8fafc;
            border-radius: 10px;
            padding: 6px 10px;
            text-align: center;
            border: 1px solid #e8edf2;
        }
        
        .login-prompt p {
            font-size: 9px;
            color: #94a3b8;
            margin: 0 0 3px 0;
        }
        
        .login-prompt .btn-group {
            display: flex;
            gap: 4px;
        }
        
        .login-prompt .btn-group a {
            flex: 1;
            padding: 3px 8px;
            border-radius: 6px;
            font-size: 9px;
            font-weight: 600;
            text-align: center;
            text-decoration: none;
            transition: all 0.25s ease;
        }
        
        .login-prompt .btn-register {
            background: #2563eb;
            color: white;
        }
        
        .login-prompt .btn-register:hover {
            background: #1d4ed8;
        }
        
        .login-prompt .btn-login {
            background: #f1f5f9;
            color: #475569;
        }
        
        .login-prompt .btn-login:hover {
            background: #e8edf2;
        }
        
        /* ===== POPUP ===== */
        .popup-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.6);
            backdrop-filter: blur(8px);
            z-index: 9999;
            justify-content: center;
            align-items: center;
            animation: fadeIn 0.3s ease;
        }
        .popup-overlay.active { display: flex; }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        @keyframes slideUp {
            from { transform: translateY(30px) scale(0.95); opacity: 0; }
            to { transform: translateY(0) scale(1); opacity: 1; }
        }
        
        .popup-box {
            background: #ffffff;
            border-radius: 24px;
            padding: 36px 32px;
            max-width: 400px;
            width: 90%;
            text-align: center;
            box-shadow: 0 30px 80px rgba(0,0,0,0.3);
            animation: slideUp 0.4s ease;
            position: relative;
        }
        
        .popup-box .popup-icon {
            width: 72px;
            height: 72px;
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
            box-shadow: 0 8px 24px rgba(37, 99, 235, 0.25);
        }
        
        .popup-box h2 {
            color: #0f172a;
            font-size: 20px;
            font-weight: 800;
            margin-bottom: 6px;
        }
        
        .popup-box p {
            color: #64748b;
            font-size: 14px;
            line-height: 1.6;
            margin-bottom: 20px;
        }
        
        .popup-box .btn-group {
            display: flex;
            gap: 10px;
        }
        
        .popup-box .btn-group a {
            flex: 1;
            padding: 11px 16px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 14px;
            text-decoration: none;
            transition: all 0.25s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }
        
        .popup-box .btn-register {
            background: #2563eb;
            color: white;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.2);
        }
        
        .popup-box .btn-register:hover {
            background: #1d4ed8;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(37, 99, 235, 0.3);
        }
        
        .popup-box .btn-login {
            background: #f1f5f9;
            color: #475569;
        }
        
        .popup-box .btn-login:hover {
            background: #e8edf2;
            transform: translateY(-2px);
        }
        
        .popup-close {
            position: absolute;
            top: 12px;
            left: 12px;
            background: #f1f5f9;
            border: none;
            color: #94a3b8;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            cursor: pointer;
            transition: all 0.25s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .popup-close:hover {
            background: #e8edf2;
            color: #475569;
            transform: rotate(90deg);
        }
        
        .popup-box .features {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 6px;
            margin: 12px 0 16px;
            text-align: right;
        }
        
        .popup-box .features span {
            color: #64748b;
            font-size: 12px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .popup-box .features span i {
            color: #2563eb;
        }
        
        /* ===== عنوان الصفحة ===== */
        .page-title h1 {
            color: #ffffff;
            font-size: 22px;
            font-weight: 900;
            text-shadow: 0 2px 12px rgba(0,0,0,0.3);
        }
        
        @media (max-width: 480px) {
            .page-title h1 {
                font-size: 18px;
            }
        }
        
        .page-title h1 i { color: #93bbfc; }
        .page-title p { 
            color: #d4b8a0;
            font-size: 13px; 
        }
        
        @media (max-width: 480px) {
            .page-title p {
                font-size: 12px;
            }
        }
        
        /* ===== أزرار الفلتر ===== */
        .btn-filter {
            background: rgba(255,255,255,0.08);
            color: #d4b8a0;
            border: 1px solid rgba(255,255,255,0.08);
            padding: 7px 14px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.25s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .btn-filter:hover {
            background: rgba(255,255,255,0.12);
            border-color: rgba(255,255,255,0.15);
        }
        
        .btn-filter.active {
            background: rgba(37, 99, 235, 0.2);
            border-color: #2563eb;
            color: #93bbfc;
        }
        
        .btn-clear {
            background: rgba(239, 68, 68, 0.15);
            color: #fca5a5;
            border: 1px solid rgba(239, 68, 68, 0.15);
            padding: 6px 12px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 11px;
            cursor: pointer;
            transition: all 0.25s ease;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            text-decoration: none;
        }
        
        .btn-clear:hover {
            background: rgba(239, 68, 68, 0.25);
        }
        
        .badge-filters {
            background: rgba(37, 99, 235, 0.15);
            color: #93bbfc;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            border: 1px solid rgba(37, 99, 235, 0.15);
        }
        
        .results-count {
            color: #d4b8a0;
            font-size: 13px;
        }
        
        .results-count strong { color: #ffffff; }
        
        /* ===== لوحة الفلتر ===== */
        .filter-panel {
            background: rgba(26, 14, 10, 0.95);
            border-radius: 16px;
            padding: 16px;
            border: 1px solid rgba(255,255,255,0.06);
            display: none;
            animation: slideDown 0.3s ease;
            margin-bottom: 16px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.4);
        }
        
        .filter-panel.show { display: block; }
        
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .filter-panel label {
            color: #d4b8a0;
            font-size: 11px;
            font-weight: 600;
            display: block;
            margin-bottom: 3px;
        }
        
        .filter-panel select,
        .filter-panel input {
            width: 100%;
            padding: 8px 12px;
            border-radius: 10px;
            font-size: 13px;
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.08);
            color: #ffffff;
            transition: all 0.25s ease;
        }
        
        .filter-panel select:focus,
        .filter-panel input:focus {
            border-color: #2563eb;
            outline: none;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
            background: rgba(255,255,255,0.1);
        }
        
        .filter-panel select option {
            background: #1a0e0a;
            color: #ffffff;
        }
        
        .filter-panel .btn-search {
            background: #2563eb;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.25s ease;
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }
        
        .filter-panel .btn-search:hover {
            background: #1d4ed8;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.2);
        }
        
        .filter-panel .btn-close-filter {
            background: rgba(255,255,255,0.06);
            color: #d4b8a0;
            border: 1px solid rgba(255,255,255,0.06);
            padding: 8px 14px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.25s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
        }
        
        .filter-panel .btn-close-filter:hover {
            background: rgba(255,255,255,0.1);
        }
        
        .active-filters {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 4px;
            padding: 6px 12px;
            background: rgba(255,255,255,0.04);
            border-radius: 10px;
            border: 1px solid rgba(255,255,255,0.04);
            margin-bottom: 12px;
        }
        
        .active-filters .label {
            color: #d4b8a0;
            font-size: 11px;
            font-weight: 500;
        }
        
        .active-filter-tag {
            background: rgba(37, 99, 235, 0.15);
            color: #93bbfc;
            padding: 2px 8px;
            border-radius: 16px;
            font-size: 10px;
            display: inline-flex;
            align-items: center;
            gap: 3px;
            border: 1px solid rgba(37, 99, 235, 0.1);
        }
        
        .active-filter-tag a {
            color: rgba(255,255,255,0.3);
            text-decoration: none;
            font-weight: 700;
            transition: color 0.25s ease;
            font-size: 12px;
            line-height: 1;
        }
        
        .active-filter-tag a:hover { color: #fca5a5; }
        
        .empty-state {
            background: rgba(255,255,255,0.04);
            border-radius: 16px;
            padding: 40px 20px;
            text-align: center;
            border: 1px solid rgba(255,255,255,0.04);
        }
        
        .empty-state i { color: rgba(255,255,255,0.15); }
        .empty-state p { color: #d4b8a0; font-size: 15px; }
        .empty-state a { color: #93bbfc; text-decoration: none; margin-top: 8px; display: inline-block; font-weight: 600; }
        .empty-state a:hover { color: #ffffff; }
        
        /* ===== التجاوب ===== */
        @media (max-width: 480px) {
            .contractor-card {
                padding: 12px;
                border-radius: 12px;
            }
            .filter-panel {
                padding: 12px;
            }
            .filter-panel .grid {
                gap: 8px;
            }
            .popup-box {
                padding: 28px 20px;
            }
            .popup-box h2 {
                font-size: 18px;
            }
            .btn-view-profile {
                font-size: 10px;
                padding: 6px 12px;
            }
            .btn-chat {
                font-size: 10px;
                padding: 6px 10px;
            }
        }
    </style>
</head>
<body>

<!-- ===== MAIN CONTENT ===== -->
<div class="main-content">
    <div class="max-w-7xl mx-auto px-3 md:px-6">
        
        <!-- عنوان الصفحة -->
        <div class="page-title text-center mb-6">
            <h1 class="flex items-center justify-center gap-2">
                <i data-lucide="hard-hat" style="width:26px;height:26px;"></i>
                قائمة المقاولين
            </h1>
            <p>اختر أفضل المقاولين لمشروعك حسب التخصص والتصنيف والموقع</p>
        </div>
        
        <!-- Filter Controls -->
        <div class="flex flex-wrap items-center justify-between gap-2 mb-4">
            <div class="flex gap-2 flex-wrap items-center">
                <button id="filterToggleBtn" onclick="toggleFilter()" class="btn-filter <?= $has_active_filters ? 'active' : '' ?>">
                    <i data-lucide="sliders-horizontal" style="width:14px;height:14px;"></i>
                    <span id="filterBtnText"><?= $has_active_filters ? 'إخفاء الفلترة' : 'عرض الفلترة' ?></span>
                </button>
                <?php if ($has_active_filters): ?>
                <a href="<?= SITE_URL ?>/contractors_list.php" class="btn-clear">
                    <i data-lucide="eraser" style="width:12px;height:12px;"></i> تنظيف
                </a>
                <?php endif; ?>
            </div>
            <?php if ($has_active_filters): ?>
            <span class="badge-filters">
                <i data-lucide="filter" style="width:10px;height:10px;"></i> فلاتر نشطة
            </span>
            <?php endif; ?>
        </div>

        <!-- Filter Panel -->
        <div id="filterPanel" class="filter-panel <?= $has_active_filters ? 'show' : '' ?>">
            <form method="GET" action="" id="filterForm" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                <div>
                    <label>🔍 بحث بالاسم</label>
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="اسم المقاول...">
                </div>
                
                <div>
                    <label>📌 التخصص</label>
                    <select name="specialization">
                        <option value="">جميع التخصصات</option>
                        <?php foreach ($all_specializations as $spec): ?>
                        <option value="<?= htmlspecialchars($spec) ?>" <?= $specialization == $spec ? 'selected' : '' ?>>
                            <?= htmlspecialchars($spec) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label>🏆 تصنيف المقاول</label>
                    <select name="class">
                        <?php foreach ($contractor_classes as $key => $class): ?>
                        <option value="<?= $key ?>" <?= $contractor_class == $key ? 'selected' : '' ?>>
                            <?= $class['icon'] ?> <?= $class['name'] ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label>🌍 الدولة</label>
                    <select name="country_id" id="filter-country" onchange="loadCities('filter-country', 'filter-city')">
                        <option value="0">جميع الدول</option>
                        <?php foreach ($countries as $country): ?>
                        <option value="<?= $country['id'] ?>" <?= $country_id == $country['id'] ? 'selected' : '' ?>>
                            <?= $country['flag'] ?? '' ?> <?= $country['name_ar'] ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label>🏙️ المدينة</label>
                    <select name="city_id" id="filter-city">
                        <option value="0">جميع المدن</option>
                        <?php foreach ($cities as $city): ?>
                        <option value="<?= $city['id'] ?>" <?= $city_id == $city['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($city['name_ar']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label>✅ التوثيق</label>
                    <select name="verified">
                        <option value="0" <?= $is_verified == 0 ? 'selected' : '' ?>>الجميع</option>
                        <option value="1" <?= $is_verified == 1 ? 'selected' : '' ?>>موثقون فقط</option>
                    </select>
                </div>
                
                <div>
                    <label>⭐ الحد الأدنى</label>
                    <select name="min_rating">
                        <option value="0" <?= $min_rating == 0 ? 'selected' : '' ?>>الجميع</option>
                        <option value="4.5" <?= $min_rating == 4.5 ? 'selected' : '' ?>>4.5+</option>
                        <option value="4.0" <?= $min_rating == 4.0 ? 'selected' : '' ?>>4.0+</option>
                        <option value="3.5" <?= $min_rating == 3.5 ? 'selected' : '' ?>>3.5+</option>
                        <option value="3.0" <?= $min_rating == 3.0 ? 'selected' : '' ?>>3.0+</option>
                    </select>
                </div>
                
                <div class="flex gap-2 items-end">
                    <button type="submit" class="btn-search">
                        <i data-lucide="search" style="width:14px;height:14px;"></i> بحث
                    </button>
                    <button type="button" onclick="closeFilterPanel()" class="btn-close-filter">
                        <i data-lucide="x" style="width:14px;height:14px;"></i>
                    </button>
                </div>
            </form>
        </div>
        
        <!-- الفلاتر النشطة -->
        <?php if ($has_active_filters): ?>
        <div class="active-filters">
            <span class="label"><i data-lucide="tags" style="width:12px;height:12px;"></i> الفلاتر:</span>
            <?php if ($search): ?>
            <span class="active-filter-tag">
                🔍 <?= htmlspecialchars($search) ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['search' => ''])) ?>">&times;</a>
            </span>
            <?php endif; ?>
            <?php if ($specialization): ?>
            <span class="active-filter-tag">
                📌 <?= htmlspecialchars($specialization) ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['specialization' => ''])) ?>">&times;</a>
            </span>
            <?php endif; ?>
            <?php if ($contractor_class && $contractor_class != 'all'): ?>
            <span class="active-filter-tag">
                🏆 <?= $contractor_classes[$contractor_class]['name'] ?? $contractor_class ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['class' => 'all'])) ?>">&times;</a>
            </span>
            <?php endif; ?>
            <?php if ($country_id > 0): 
                foreach ($countries as $c) { if ($c['id'] == $country_id) { $country_name = $c['name_ar']; break; } }
            ?>
            <span class="active-filter-tag">
                🌍 <?= $country_name ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['country_id' => 0])) ?>">&times;</a>
            </span>
            <?php endif; ?>
            <?php if ($city_id > 0): 
                $city_cities = getCities($country_id > 0 ? $country_id : 1);
                foreach ($city_cities as $ct) { if ($ct['id'] == $city_id) { $city_name = $ct['name_ar']; break; } }
            ?>
            <span class="active-filter-tag">
                🏙️ <?= $city_name ?? 'مدينة' ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['city_id' => 0])) ?>">&times;</a>
            </span>
            <?php endif; ?>
            <?php if ($is_verified == 1): ?>
            <span class="active-filter-tag">
                ✅ موثق فقط
                <a href="?<?= http_build_query(array_merge($_GET, ['verified' => 0])) ?>">&times;</a>
            </span>
            <?php endif; ?>
            <?php if ($min_rating > 0): ?>
            <span class="active-filter-tag">
                ⭐ <?= $min_rating ?>+
                <a href="?<?= http_build_query(array_merge($_GET, ['min_rating' => 0])) ?>">&times;</a>
            </span>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <!-- عدد النتائج -->
        <div class="results-count flex justify-between items-center mb-4 flex-wrap gap-2">
            <p><i data-lucide="users" style="width:14px;height:14px;display:inline;"></i> <strong><?= count($contractors) ?></strong> مقاول</p>
            <?php if (!isLoggedIn() || getUserType() !== 'employer'): ?>
            <span class="bg-amber-500/10 text-amber-400 text-[10px] px-3 py-1 rounded-full border border-amber-500/10">
                <i data-lucide="lock" style="width:10px;height:10px;display:inline;"></i> سجل كصاحب عمل للتواصل
            </span>
            <?php endif; ?>
        </div>

        <!-- قائمة المقاولين -->
        <?php if (empty($contractors)): ?>
        <div class="empty-state">
            <i data-lucide="hard-hat" style="width:48px;height:48px;margin:0 auto 12px;display:block;color:rgba(255,255,255,0.15);"></i>
            <p>لا توجد مقاولين مطابقين لبحثك</p>
            <a href="<?= SITE_URL ?>/contractors_list.php">عرض جميع المقاولين</a>
        </div>
        <?php else: ?>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <?php foreach ($contractors as $contractor): 
            $specs = getContractorSpecializations($contractor['id']);
            $class_info = $contractor_classes[$contractor['contractor_class'] ?? 'all'];
            $rating = round($contractor['rating'] ?? 0, 1);
            $avatar_path = !empty($contractor['avatar']) && file_exists(__DIR__ . '/' . $contractor['avatar']) 
                         ? SITE_URL . '/' . $contractor['avatar'] 
                         : null;
            ?>
            <div class="contractor-card" onclick="handleCardClick(<?= $contractor['id'] ?>, event)">
                <!-- رأس البطاقة -->
                <div class="flex items-start gap-3">
                    <div class="contractor-avatar">
                        <?php if ($avatar_path): ?>
                            <img src="<?= $avatar_path ?>" alt="<?= htmlspecialchars($contractor['name']) ?>" loading="lazy">
                        <?php else: ?>
                            <div class="no-image"><?= mb_substr($contractor['name'], 0, 1, 'UTF-8') ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="contractor-name"><?= htmlspecialchars($contractor['name']) ?></div>
                        <div class="flex flex-wrap gap-1 mt-0.5">
                            <span class="contractor-badge <?= $class_info['badge'] ?>">
                                <i class="fas <?= $class_info['icon'] ?>" style="font-size:8px;"></i>
                                <?= $class_info['name'] ?>
                            </span>
                            <?php if ($contractor['verification_status'] === 'approved'): ?>
                            <span class="contractor-badge bg-green-50 text-green-700 border border-green-200">موثق</span>
                            <?php endif; ?>
                            <?php if ($contractor['is_featured']): ?>
                            <span class="contractor-badge bg-amber-50 text-amber-700 border border-amber-200">مميز</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- التقييم -->
                <div class="flex items-center gap-1">
                    <div class="flex gap-0.5">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                        <i data-lucide="star" class="w-3.5 h-3.5 <?= $i <= round($rating) ? 'text-amber-400 fill-current' : 'text-gray-300' ?>"></i>
                        <?php endfor; ?>
                    </div>
                    <?php if ($rating > 0): ?>
                    <span class="text-amber-500 text-[12px] font-bold mr-0.5"><?= number_format($rating, 1) ?></span>
                    <?php endif; ?>
                    <span class="text-gray-400 text-[10px]">(<?= $contractor['reviews_count'] ?? 0 ?>)</span>
                </div>

                <!-- الموقع -->
                <div class="contractor-location">
                    <i data-lucide="map-pin" style="width:12px;height:12px;"></i>
                    <span><?= htmlspecialchars($contractor['city_name'] ?? '') ?>, <?= htmlspecialchars($contractor['country_name'] ?? '') ?></span>
                </div>

                <!-- التخصصات -->
                <?php if (!empty($specs)): ?>
                <div class="flex flex-wrap gap-1">
                    <?php foreach (array_slice($specs, 0, 3) as $spec): ?>
                    <span class="contractor-spec"><?= htmlspecialchars($spec) ?></span>
                    <?php endforeach; ?>
                    <?php if (count($specs) > 3): ?>
                    <span class="contractor-spec-more">+<?= count($specs) - 3 ?></span>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <!-- الأزرار -->
                <?php if (isLoggedIn() && getUserType() === 'employer'): ?>
                <div class="flex gap-2 mt-1 pt-2 border-t border-gray-100">
                    <a href="<?= SITE_URL ?>/profile.php?id=<?= $contractor['id'] ?>" class="btn-view-profile">
                        <i data-lucide="eye" style="width:12px;height:12px;"></i> عرض الملف
                    </a>
                    <a href="<?= SITE_URL ?>/chat.php?project_id=0&contractor_id=<?= $contractor['id'] ?>" class="btn-chat">
                        <i data-lucide="message-circle" style="width:12px;height:12px;"></i>
                    </a>
                </div>
                <?php else: ?>
                <div class="login-prompt mt-1">
                    <p><i data-lucide="lock" style="width:10px;height:10px;display:inline;"></i> سجل كصاحب عمل للتواصل</p>
                    <div class="btn-group">
                        <a href="<?= SITE_URL ?>/register.php" class="btn-register">تسجيل</a>
                        <a href="<?= SITE_URL ?>/login.php" class="btn-login">دخول</a>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

    </div>
</div>

<!-- ===== POPUP ===== -->
<div id="loginPopup" class="popup-overlay" onclick="closePopup(event)">
    <div class="popup-box" onclick="event.stopPropagation()">
        <button class="popup-close" onclick="closePopup()">
            <i data-lucide="x" style="width:18px;height:18px;"></i>
        </button>
        
        <div class="popup-icon">
            <i data-lucide="user-plus" style="width:32px;height:32px;"></i>
        </div>
        
        <h2>مرحباً بك في مزاد البناء</h2>
        <p>للتواصل مع المقاولين ومشاهدة تفاصيل ملفاتهم، يرجى تسجيل الدخول أو إنشاء حساب جديد</p>
        
        <div class="features">
            <span><i data-lucide="check-circle" style="width:14px;height:14px;"></i> تصفح المقاولين</span>
            <span><i data-lucide="check-circle" style="width:14px;height:14px;"></i> التواصل المباشر</span>
            <span><i data-lucide="check-circle" style="width:14px;height:14px;"></i> عروض حصرية</span>
            <span><i data-lucide="check-circle" style="width:14px;height:14px;"></i> مشاريع مضمونة</span>
        </div>
        
        <div class="btn-group">
            <a href="<?= SITE_URL ?>/register.php" class="btn-register">
                <i data-lucide="user-plus" style="width:16px;height:16px;"></i> إنشاء حساب
            </a>
            <a href="<?= SITE_URL ?>/login.php" class="btn-login">
                <i data-lucide="log-in" style="width:16px;height:16px;"></i> تسجيل دخول
            </a>
        </div>
    </div>
</div>

<script>
    lucide.createIcons();

    // ===== POPUP =====
    function openPopup() {
        document.getElementById('loginPopup').classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closePopup(event) {
        if (event && event.target !== event.currentTarget) return;
        document.getElementById('loginPopup').classList.remove('active');
        document.body.style.overflow = '';
    }

    // ===== CARD CLICK =====
    function handleCardClick(contractorId, event) {
        <?php if (isLoggedIn() && getUserType() === 'employer'): ?>
            window.location.href = '<?= SITE_URL ?>/profile.php?id=' + contractorId;
        <?php else: ?>
            openPopup();
        <?php endif; ?>
    }

    // ===== FILTER =====
    function toggleFilter() {
        const panel = document.getElementById('filterPanel');
        const btnText = document.getElementById('filterBtnText');
        panel.classList.toggle('show');
        btnText.textContent = panel.classList.contains('show') ? 'إخفاء الفلترة' : 'عرض الفلترة';
    }

    function closeFilterPanel() {
        const panel = document.getElementById('filterPanel');
        const btnText = document.getElementById('filterBtnText');
        panel.classList.remove('show');
        btnText.textContent = 'عرض الفلترة';
    }

    // ===== LOAD CITIES =====
    function loadCities(countrySelectId, citySelectId) {
        const countrySelect = document.getElementById(countrySelectId);
        const citySelect = document.getElementById(citySelectId);
        const countryId = countrySelect.value;
        
        citySelect.innerHTML = '<option value="0">جاري التحميل...</option>';
        if (!countryId || countryId == 0) {
            citySelect.innerHTML = '<option value="0">جميع المدن</option>';
            return;
        }
        
        fetch('<?= SITE_URL ?>/api.php?action=get_cities&country_id=' + countryId)
            .then(response => response.json())
            .then(data => {
                citySelect.innerHTML = '<option value="0">جميع المدن</option>';
                if (data.status === 'success' && data.data && data.data.length > 0) {
                    data.data.forEach(city => {
                        const option = document.createElement('option');
                        option.value = city.id;
                        option.textContent = city.name_ar;
                        citySelect.appendChild(option);
                    });
                }
            })
            .catch(error => {
                console.error('Error:', error);
                citySelect.innerHTML = '<option value="0">جميع المدن</option>';
            });
    }

    // ===== ESC KEY =====
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closePopup();
    });
</script>

<?php include 'includes/footer.php'; ?>
</body>
</html>