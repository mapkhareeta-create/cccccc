<?php
require_once 'config.php';

// جلب الفلاتر من URL
$country_id = (int)($_GET['country_id'] ?? 0);
$city_id = (int)($_GET['city_id'] ?? 0);
$category = clean($_GET['category'] ?? '');

// بناء استعلام المشاريع مع الفلاتر
$sql = "
    SELECT p.*, u.name as employer_name, 
           c.name_ar as country_name, ci.name_ar as city_name,
           co.currency_ar, co.currency_code,
           (SELECT COUNT(*) FROM bids WHERE project_id = p.id) as bids_count,
           (SELECT pi.image_path FROM project_images pi WHERE pi.project_id = p.id ORDER BY pi.id ASC LIMIT 1) as main_image
    FROM projects p
    JOIN users u ON p.employer_id = u.id
    LEFT JOIN countries c ON p.country_id = c.id
    LEFT JOIN cities ci ON p.city_id = ci.id
    LEFT JOIN countries co ON p.country_id = co.id
    WHERE p.status = 'open'
";

$params = [];

if ($country_id > 0) {
    $sql .= " AND p.country_id = ?";
    $params[] = $country_id;
}

if ($city_id > 0) {
    $sql .= " AND p.city_id = ?";
    $params[] = $city_id;
}

if (!empty($category)) {
    $sql .= " AND p.category = ?";
    $params[] = $category;
}

$sql .= " ORDER BY p.created_at DESC LIMIT 30";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$projects = $stmt->fetchAll();

// جلب الدول والتخصصات للفلتر
$countries = getCountries();
$categories = getProjectCategories();

// جلب المدن إذا تم اختيار دولة
$cities = [];
if ($country_id > 0) {
    $cities = getCities($country_id);
}

$has_active_filters = ($country_id > 0 || $city_id > 0 || !empty($category));

$page_title = 'جميع العطاءات - مزاد البناء';
include 'includes/header.php';
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>جميع العطاءات - مزاد البناء</title>
    <script src="https://cdn.tailwindcss.com/3.4.17"></script>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700;900&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/lucide@0.263.0/dist/umd/lucide.min.js"></script>
    <style>
        /* ===== RESET & OVERRIDE ===== */
        * { font-family: 'DM Sans', sans-serif; }
        
        /* إلغاء أي خلفية من header أو footer */
        body, html {
            min-height: 100vh !important;
            margin: 0 !important;
            padding: 0 !important;
            background: #254163 !important;
            position: relative !important;
            overflow-x: hidden !important;
        }
        
        /* إلغاء أي خلفية من containers أخرى */
        .main-wrapper, .container, .page-wrapper, #app, .site-wrapper {
            background: transparent !important;
        }
        
        .main-content {
            position: relative;
            z-index: 1;
            padding: 90px 0 60px 0 !important; /* زيادة المسافة العلوية */
            min-height: 100vh;
            background: transparent !important;
        }
        
        .page-title {
            text-align: center;
            padding: 30px 0 20px 0 !important; /* زيادة padding */
            margin-top: 10px !important;
        }
        .page-title h1 {
            font-size: 32px !important;
            font-weight: 900 !important;
            color: #ffffff !important;
            text-shadow: 0 2px 10px rgba(0,0,0,0.3);
            margin-bottom: 8px !important;
        }
        .page-title h1 i { color: #60a5fa; }
        .page-title p {
            font-size: 16px !important;
            color: rgba(255,255,255,0.7) !important;
            text-shadow: 0 1px 8px rgba(0,0,0,0.2);
        }
        
        .filter-controls {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin: 15px 0 20px 0 !important;
        }
        .btn-filter {
            background: linear-gradient(135deg, #2563eb, #1d4ed8) !important;
            color: white !important;
            border: none !important;
            padding: 10px 22px !important;
            border-radius: 12px !important;
            font-weight: 700 !important;
            font-size: 14px !important;
            cursor: pointer !important;
            transition: all 0.3s ease !important;
            box-shadow: 0 4px 15px rgba(37, 99, 235, 0.3) !important;
            display: inline-flex !important;
            align-items: center !important;
            gap: 8px !important;
        }
        .btn-filter:hover { transform: translateY(-2px) !important; box-shadow: 0 8px 25px rgba(37, 99, 235, 0.4) !important; }
        .btn-filter.active {
            background: linear-gradient(135deg, #ef4444, #dc2626) !important;
            box-shadow: 0 4px 15px rgba(239, 68, 68, 0.3) !important;
        }
        .btn-clear {
            background: rgba(239, 68, 68, 0.15) !important;
            color: #fca5a5 !important;
            border: 1px solid rgba(239, 68, 68, 0.2) !important;
            padding: 8px 16px !important;
            border-radius: 10px !important;
            font-weight: 600 !important;
            font-size: 13px !important;
            cursor: pointer !important;
            transition: all 0.3s ease !important;
            display: inline-flex !important;
            align-items: center !important;
            gap: 6px !important;
            text-decoration: none !important;
        }
        .btn-clear:hover { background: rgba(239, 68, 68, 0.25) !important; transform: translateY(-2px) !important; }
        .badge-filters {
            background: rgba(37, 99, 235, 0.2) !important;
            color: #93bbfc !important;
            padding: 6px 14px !important;
            border-radius: 20px !important;
            font-size: 12px !important;
            font-weight: 600 !important;
            display: inline-flex !important;
            align-items: center !important;
            gap: 6px !important;
            border: 1px solid rgba(37, 99, 235, 0.15) !important;
        }
        
        .filter-panel {
            display: none;
            animation: slideDown 0.4s ease;
        }
        .filter-panel.show { display: block; }
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-15px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .filter-panel {
            background: rgba(255,255,255,0.1) !important;
            backdrop-filter: blur(12px) !important;
            -webkit-backdrop-filter: blur(12px) !important;
            border-radius: 16px !important;
            padding: 18px !important;
            border: 1px solid rgba(255,255,255,0.08) !important;
            margin-bottom: 16px !important;
        }
        
        .filter-panel label {
            color: rgba(255,255,255,0.6) !important;
            font-size: 11px !important;
            font-weight: 500 !important;
            display: block !important;
            margin-bottom: 3px !important;
        }
        
        .filter-panel select,
        .filter-panel input {
            width: 100% !important;
            padding: 7px 10px !important;
            border-radius: 10px !important;
            font-size: 12px !important;
            background: rgba(255,255,255,0.08) !important;
            border: 1px solid rgba(255,255,255,0.08) !important;
            color: #ffffff !important;
            transition: all 0.3s ease !important;
        }
        
        .filter-panel select option {
            background: #1e293b !important;
            color: #ffffff !important;
        }
        
        .filter-panel select:focus,
        .filter-panel input:focus {
            border-color: rgba(59, 130, 246, 0.4) !important;
            outline: none !important;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1) !important;
        }
        
        .filter-panel .btn-search {
            background: linear-gradient(135deg, #2563eb, #1d4ed8) !important;
            color: white !important;
            border: none !important;
            padding: 7px 14px !important;
            border-radius: 10px !important;
            font-weight: 600 !important;
            font-size: 12px !important;
            cursor: pointer !important;
            transition: all 0.3s ease !important;
            flex: 1 !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            gap: 4px !important;
        }
        
        .filter-panel .btn-search:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 4px 15px rgba(37, 99, 235, 0.3) !important;
        }
        
        .filter-panel .btn-close-filter {
            background: rgba(255,255,255,0.06) !important;
            color: rgba(255,255,255,0.6) !important;
            border: 1px solid rgba(255,255,255,0.06) !important;
            padding: 7px 14px !important;
            border-radius: 10px !important;
            font-weight: 600 !important;
            font-size: 12px !important;
            cursor: pointer !important;
            transition: all 0.3s ease !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            gap: 4px !important;
        }
        
        .filter-panel .btn-close-filter:hover {
            background: rgba(255,255,255,0.1) !important;
        }
        
        .active-filters {
            display: flex !important;
            flex-wrap: wrap !important;
            align-items: center !important;
            gap: 4px !important;
            padding: 6px 12px !important;
            background: rgba(255,255,255,0.05) !important;
            border-radius: 10px !important;
            border: 1px solid rgba(255,255,255,0.04) !important;
            margin-bottom: 12px !important;
        }
        
        .active-filters .label {
            color: rgba(255,255,255,0.4) !important;
            font-size: 11px !important;
            font-weight: 500 !important;
        }
        
        .active-filter-tag {
            background: rgba(59, 130, 246, 0.2) !important;
            color: #93bbfc !important;
            padding: 2px 8px !important;
            border-radius: 12px !important;
            font-size: 10px !important;
            display: inline-flex !important;
            align-items: center !important;
            gap: 3px !important;
            border: 1px solid rgba(59, 130, 246, 0.1) !important;
        }
        
        .active-filter-tag a {
            color: rgba(255,255,255,0.3) !important;
            text-decoration: none !important;
            font-weight: 700 !important;
            transition: color 0.3s ease !important;
            font-size: 12px !important;
            line-height: 1 !important;
        }
        
        .active-filter-tag a:hover { color: #ef4444 !important; }
        
        .results-count {
            display: flex !important;
            justify-content: space-between !important;
            align-items: center !important;
            margin-bottom: 16px !important;
            flex-wrap: wrap !important;
            gap: 8px !important;
        }
        .results-count p {
            color: rgba(255,255,255,0.7) !important;
            font-size: 13px !important;
        }
        .results-count p strong { color: #93bbfc !important; }
        
        /* ===== PROJECT CARDS ===== */
        .project-card {
            background: #ffffff !important;
            border-radius: 14px !important;
            overflow: hidden !important;
            transition: all 0.3s ease !important;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2) !important;
            cursor: pointer !important;
            border: 1px solid rgba(255,255,255,0.1) !important;
            height: 100% !important;
            display: flex !important;
            flex-direction: column !important;
        }
        
        .project-card:hover {
            transform: translateY(-4px) !important;
            box-shadow: 0 12px 30px rgba(0,0,0,0.35) !important;
            border-color: rgba(59, 130, 246, 0.3) !important;
        }
        
        .project-card:active {
            transform: scale(0.97) !important;
        }
        
        .project-card-inner {
            display: flex !important;
            flex-direction: column !important;
            padding: 12px !important;
            flex: 1 !important;
            gap: 4px !important;
        }
        
        .project-image {
            width: 100% !important;
            height: 110px !important;
            border-radius: 10px !important;
            overflow: hidden !important;
            background: #e5e7eb !important;
            border: 1px solid #e5e7eb !important;
            flex-shrink: 0 !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
        }
        
        .project-image img {
            width: 100% !important;
            height: 100% !important;
            object-fit: cover !important;
        }
        
        .project-image .no-image {
            width: 100% !important;
            height: 100% !important;
            display: flex !important;
            flex-direction: column !important;
            align-items: center !important;
            justify-content: center !important;
            background: #e5e7eb !important;
            color: #9ca3af !important;
        }
        
        .project-image .no-image i {
            font-size: 28px !important;
            margin-bottom: 2px !important;
        }
        
        .project-image .no-image span {
            font-size: 9px !important;
            color: #9ca3af !important;
        }
        
        .project-header {
            display: flex !important;
            justify-content: space-between !important;
            align-items: center !important;
            flex-wrap: wrap !important;
            gap: 3px !important;
            margin-top: 4px !important;
        }
        
        .project-category {
            background: #f1f5f9 !important;
            color: #475569 !important;
            border-radius: 16px !important;
            font-size: 9px !important;
            padding: 2px 8px !important;
            font-weight: 500 !important;
            border: 1px solid #e5e7eb !important;
        }
        
        .project-status {
            background: #dcfce7 !important;
            color: #166534 !important;
            border-radius: 16px !important;
            font-size: 9px !important;
            padding: 2px 8px !important;
            font-weight: 500 !important;
            border: 1px solid #bbf7d0 !important;
        }
        
        .project-title {
            color: #1e293b !important;
            font-weight: 700 !important;
            font-size: 14px !important;
            margin: 2px 0 !important;
            display: -webkit-box !important;
            -webkit-line-clamp: 1 !important;
            -webkit-box-orient: vertical !important;
            overflow: hidden !important;
            line-height: 1.3 !important;
        }
        
        .project-description {
            color: #64748b !important;
            font-size: 11px !important;
            line-height: 1.4 !important;
            display: -webkit-box !important;
            -webkit-line-clamp: 2 !important;
            -webkit-box-orient: vertical !important;
            overflow: hidden !important;
            flex: 1 !important;
            min-height: 28px !important;
        }
        
        .project-budget {
            background: #fefce8 !important;
            border-radius: 8px !important;
            padding: 4px 10px !important;
            display: inline-flex !important;
            align-items: center !important;
            gap: 4px !important;
            border: 1px solid #fef08a !important;
            align-self: flex-start !important;
            margin-top: 2px !important;
        }
        
        .project-budget i {
            color: #eab308 !important;
            font-size: 11px !important;
        }
        
        .project-budget span {
            color: #854d0e !important;
            font-size: 11px !important;
            font-weight: 600 !important;
        }
        
        .project-location {
            color: #94a3b8 !important;
            font-size: 10px !important;
            display: flex !important;
            align-items: center !important;
            gap: 3px !important;
        }
        
        .project-location i {
            color: #94a3b8 !important;
            font-size: 11px !important;
        }
        
        .project-footer {
            display: flex !important;
            align-items: center !important;
            justify-content: space-between !important;
            padding-top: 8px !important;
            border-top: 1px solid #e5e7eb !important;
            margin-top: auto !important;
        }
        
        .project-stats {
            color: #94a3b8 !important;
            font-size: 10px !important;
            display: flex !important;
            align-items: center !important;
            gap: 8px !important;
        }
        
        .project-stats i {
            font-size: 10px !important;
        }
        
        .project-stats span {
            display: flex !important;
            align-items: center !important;
            gap: 2px !important;
        }
        
        .btn-details {
            background: #f1f5f9 !important;
            color: #1e293b !important;
            font-weight: 600 !important;
            font-size: 10px !important;
            padding: 4px 12px !important;
            border-radius: 6px !important;
            transition: all 0.3s ease !important;
            text-decoration: none !important;
            border: 1px solid #e5e7eb !important;
            display: inline-flex !important;
            align-items: center !important;
            gap: 3px !important;
        }
        
        .btn-details:hover {
            background: #e5e7eb !important;
        }
        
        .empty-state {
            background: rgba(255,255,255,0.08) !important;
            backdrop-filter: blur(12px) !important;
            border-radius: 16px !important;
            padding: 50px !important;
            text-align: center !important;
            border: 1px solid rgba(255,255,255,0.06) !important;
        }
        .empty-state p { color: rgba(255,255,255,0.5) !important; font-size: 15px !important; }
        .empty-state a { color: #60a5fa !important; text-decoration: none !important; margin-top: 8px !important; display: inline-block !important; }
        .empty-state a:hover { color: #93bbfc !important; }
        
        .cta-section {
            background: rgba(255,255,255,0.06) !important;
            backdrop-filter: blur(12px) !important;
            border-radius: 16px !important;
            padding: 32px !important;
            text-align: center !important;
            border: 1px solid rgba(255,255,255,0.06) !important;
            margin-top: 40px !important;
        }
        .cta-section h3 { color: #ffffff !important; font-size: 24px !important; font-weight: 900 !important; }
        .cta-section p { color: rgba(255,255,255,0.7) !important; margin-bottom: 16px !important; }
        .cta-section .btn-cta {
            background: linear-gradient(135deg, #2563eb, #1d4ed8) !important;
            color: white !important;
            font-weight: 700 !important;
            padding: 10px 24px !important;
            border-radius: 12px !important;
            text-decoration: none !important;
            display: inline-block !important;
            transition: all 0.3s ease !important;
            box-shadow: 0 4px 15px rgba(37, 99, 235, 0.3) !important;
        }
        .cta-section .btn-cta:hover { transform: translateY(-2px) !important; box-shadow: 0 8px 25px rgba(37, 99, 235, 0.4) !important; }
        
        @media (max-width: 768px) {
            .page-title h1 { font-size: 22px !important; }
            .filter-panel .grid { grid-template-columns: 1fr 1fr !important; }
            .project-image { height: 90px !important; }
            .main-content { padding: 80px 0 60px 0 !important; }
        }
        @media (max-width: 480px) {
            .filter-controls { flex-direction: column !important; align-items: stretch !important; }
            .filter-panel .grid { grid-template-columns: 1fr !important; }
            .results-count { flex-direction: column !important; align-items: flex-start !important; gap: 6px !important; }
            .project-image { height: 80px !important; }
            .project-title { font-size: 13px !important; }
            .main-content { padding: 70px 0 60px 0 !important; }
            .page-title { padding: 20px 0 15px 0 !important; }
            .page-title h1 { font-size: 20px !important; }
            .page-title p { font-size: 14px !important; }
        }
    </style>
</head>
<body>

<div class="main-content">
    <div class="max-w-7xl mx-auto px-3 md:px-6">
        
        <!-- عنوان الصفحة -->
        <div class="page-title">
            <h1 class="flex items-center justify-center gap-2">
                <i data-lucide="gavel" style="width:28px;height:28px;"></i>
                جميع العطاءات
            </h1>
            <p>استعرض أحدث المشاريع واختر العطاء المناسب لك</p>
        </div>
        
        <!-- Filter Controls -->
        <div class="filter-controls">
            <div class="flex gap-2 flex-wrap items-center">
                <button id="filterToggleBtn" onclick="toggleFilter()" class="btn-filter <?= $has_active_filters ? 'active' : '' ?>">
                    <i data-lucide="sliders-horizontal" style="width:16px;height:16px;"></i>
                    <span id="filterBtnText"><?= $has_active_filters ? 'إخفاء الفلترة' : 'عرض الفلترة' ?></span>
                </button>
                <?php if ($has_active_filters): ?>
                <a href="<?= SITE_URL ?>/projects_list.php" class="btn-clear">
                    <i data-lucide="eraser" style="width:14px;height:14px;"></i> تنظيف
                </a>
                <?php endif; ?>
            </div>
            <?php if ($has_active_filters): ?>
            <span class="badge-filters">
                <i data-lucide="filter" style="width:12px;height:12px;"></i> فلاتر نشطة
            </span>
            <?php endif; ?>
        </div>

        <!-- Filter Panel -->
        <div id="filterPanel" class="filter-panel <?= $has_active_filters ? 'show' : '' ?>">
            <form method="GET" action="" id="filterForm" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
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
                            <?= clean($city['name_ar']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label>📌 التخصص</label>
                    <select name="category">
                        <option value="">جميع التخصصات</option>
                        <?php foreach ($categories as $cat => $icon): ?>
                        <option value="<?= $cat ?>" <?= $category == $cat ? 'selected' : '' ?>>
                            <?= $icon ?> <?= $cat ?>
                        </option>
                        <?php endforeach; ?>
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
            <?php if ($country_id > 0): 
                foreach ($countries as $c) { if ($c['id'] == $country_id) { $country_name = $c['name_ar']; break; } }
            ?>
            <span class="active-filter-tag">
                🌍 <?= $country_name ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['country_id' => 0])) ?>">&times;</a>
            </span>
            <?php endif; ?>
            <?php if ($city_id > 0): 
                foreach ($cities as $c) { if ($c['id'] == $city_id) { $city_name = $c['name_ar']; break; } }
            ?>
            <span class="active-filter-tag">
                🏙️ <?= $city_name ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['city_id' => 0])) ?>">&times;</a>
            </span>
            <?php endif; ?>
            <?php if (!empty($category)): ?>
            <span class="active-filter-tag">
                📌 <?= $category ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['category' => ''])) ?>">&times;</a>
            </span>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <!-- عدد النتائج -->
        <div class="results-count">
            <p><i data-lucide="briefcase" style="width:14px;height:14px;display:inline;"></i> <strong><?= count($projects) ?></strong> مشروع</p>
        </div>

        <!-- قائمة المشاريع -->
        <?php if (empty($projects)): ?>
        <div class="empty-state">
            <i data-lucide="inbox" style="width:48px;height:48px;margin:0 auto 16px;display:block;color:rgba(255,255,255,0.15);"></i>
            <p>لا توجد مشاريع مطابقة لبحثك</p>
            <a href="<?= SITE_URL ?>/projects_list.php">عرض جميع المشاريع</a>
        </div>
        <?php else: ?>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
            <?php foreach ($projects as $project): ?>
            <a href="<?= SITE_URL ?>/project_detail.php?id=<?= $project['id'] ?>" class="project-card">
                <div class="project-card-inner">
                    <!-- صورة المشروع -->
                    <div class="project-image">
                        <?php 
                        $image_path = !empty($project['main_image']) && file_exists($project['main_image']) 
                                    ? SITE_URL . '/' . $project['main_image'] 
                                    : null;
                        ?>
                        <?php if ($image_path): ?>
                            <img src="<?= $image_path ?>" alt="<?= clean($project['title']) ?>">
                        <?php else: ?>
                            <div class="no-image">
                                <i data-lucide="building-2" style="width:28px;height:28px;"></i>
                                <span>لا توجد صورة</span>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- رأس البطاقة -->
                    <div class="project-header">
                        <span class="project-category">
                            <?= $categories[$project['category']] ?? '🏗️' ?> <?= clean($project['category']) ?>
                        </span>
                        <span class="project-status">
                            <i data-lucide="circle" style="width:7px;height:7px;fill:currentColor;"></i> مفتوح
                        </span>
                    </div>
                    
                    <h3 class="project-title"><?= clean($project['title']) ?></h3>
                    <p class="project-description"><?= clean(substr($project['description'], 0, 70)) ?>...</p>
                    
                    <!-- الميزانية -->
                    <div class="project-budget">
                        <i data-lucide="coins" style="width:12px;height:12px;"></i>
                        <span>
                            <?php if ($project['budget_min'] && $project['budget_max']): ?>
                                <?= number_format($project['budget_min']) ?> - <?= number_format($project['budget_max']) ?> <?= clean($project['currency_ar'] ?? '') ?>
                            <?php elseif ($project['budget_min']): ?>
                                من <?= number_format($project['budget_min']) ?> <?= clean($project['currency_ar'] ?? '') ?>
                            <?php else: ?>
                                حسب الاتفاق
                            <?php endif; ?>
                        </span>
                    </div>
                    
                    <!-- الموقع -->
                    <div class="project-location">
                        <i data-lucide="map-pin" style="width:11px;height:11px;"></i>
                        <span><?= clean($project['city_name'] ?? '') ?>، <?= clean($project['country_name'] ?? '') ?></span>
                    </div>
                    
                    <!-- التذييل -->
                    <div class="project-footer">
                        <div class="project-stats">
                            <span><i data-lucide="gavel" style="width:10px;height:10px;"></i> <?= $project['bids_count'] ?? 0 ?></span>
                            <span><i data-lucide="eye" style="width:10px;height:10px;"></i> <?= $project['views'] ?? 0 ?></span>
                        </div>
                        <span class="btn-details">
                            تفاصيل <i data-lucide="arrow-left" style="width:10px;height:10px;"></i>
                        </span>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
        <!-- CTA للتسجيل -->
        <?php if (!isLoggedIn()): ?>
        <div class="cta-section">
            <h3 class="flex items-center justify-center gap-2">
                <i data-lucide="briefcase" style="width:28px;height:28px;"></i>
                هل لديك مشروع؟
            </h3>
            <p>انضم إلينا واطرح مشروعك الآن</p>
            <a href="<?= SITE_URL ?>/register.php" class="btn-cta">
                <i data-lucide="user-plus" style="width:16px;height:16px;display:inline;"></i> سجل الآن مجاناً
            </a>
        </div>
        <?php endif; ?>

    </div>
</div>

<script>
    lucide.createIcons();

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
</script>

<?php include 'includes/footer.php'; ?>
</body>
</html>