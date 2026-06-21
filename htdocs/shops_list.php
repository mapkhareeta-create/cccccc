<?php
require_once 'config.php';

// جلب الفلاتر من URL
$country_id = (int)($_GET['country_id'] ?? 0);
$city_id = (int)($_GET['city_id'] ?? 0);
$search = clean($_GET['search'] ?? '');

// ✅ استعلام محسن مع البحث في المنتجات
$sql = "
    SELECT DISTINCT s.*, 
           u.name as owner_name, 
           u.avatar as owner_avatar,
           c.name_ar as country_name, 
           ci.name_ar as city_name,
           (SELECT COUNT(*) FROM products WHERE shop_id = s.id) as products_count
    FROM shops s
    JOIN users u ON s.user_id = u.id
    LEFT JOIN countries c ON s.country_id = c.id
    LEFT JOIN cities ci ON s.city_id = ci.id
    WHERE s.status = 'active'
";

$params = [];

// فلتر الدولة
if ($country_id > 0) {
    $sql .= " AND s.country_id = ?";
    $params[] = $country_id;
}

// فلتر المدينة
if ($city_id > 0) {
    $sql .= " AND s.city_id = ?";
    $params[] = $city_id;
}

// ✅ فلتر البحث في المنتجات (اسم المنتج، وصفه، تصنيفه)
if (!empty($search)) {
    $sql .= " AND EXISTS (
        SELECT 1 FROM products p 
        WHERE p.shop_id = s.id 
        AND (
            p.name LIKE ? 
            OR p.description LIKE ? 
            OR p.category LIKE ?
        )
    )";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

// ✅ GROUP BY لمنع التكرار
$sql .= " GROUP BY s.id ORDER BY s.rating DESC, s.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$shops = $stmt->fetchAll();

// جلب الدول للفلتر
$countries = getCountries();

// جلب المدن إذا تم اختيار دولة
$cities = [];
if ($country_id > 0) {
    $cities = getCities($country_id);
}

$has_active_filters = ($country_id > 0 || $city_id > 0 || !empty($search));
$page_title = 'محلات مواد البناء';
include 'includes/header.php';
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>محلات مواد البناء - مزاد البناء</title>
    <script src="https://cdn.tailwindcss.com/3.4.17"></script>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;900&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/lucide@0.263.0/dist/umd/lucide.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        /* ============================================ */
        /* التنسيقات العامة */
        /* ============================================ */
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
        
        .page-title h1 {
            color: #ffffff;
            font-size: 22px;
            font-weight: 900;
            text-shadow: 0 2px 12px rgba(0,0,0,0.3);
        }
        .page-title h1 i { color: #93bbfc; }
        .page-title p { 
            color: #d4b8a0;
            font-size: 13px; 
        }
        
        /* ============================================ */
        /* أزرار رئيسية */
        /* ============================================ */
        .btn-action {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            color: white;
            font-weight: 700;
            font-size: 16px;
            padding: 14px 28px;
            border-radius: 14px;
            box-shadow: 0 4px 16px rgba(37,99,235,0.2);
            transition: all 0.3s ease;
            text-decoration: none;
            border: none;
            cursor: pointer;
            min-width: 200px;
        }
        .btn-action:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(37,99,235,0.3);
        }
        .btn-action:active {
            transform: scale(0.97);
        }
        .btn-action .icon { font-size: 20px; }
        .btn-action .badge {
            background: rgba(255,255,255,0.2);
            padding: 2px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }
        .btn-action.active {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
            box-shadow: 0 4px 16px rgba(220,38,38,0.2);
        }
        .btn-action.active:hover {
            box-shadow: 0 8px 24px rgba(220,38,38,0.3);
        }
        
        .btn-search-products {
            background: linear-gradient(135deg, #7c3aed, #6d28d9);
            box-shadow: 0 4px 16px rgba(124,58,237,0.2);
        }
        .btn-search-products:hover {
            box-shadow: 0 8px 24px rgba(124,58,237,0.3);
        }
        
        @media (max-width: 768px) {
            .btn-action {
                font-size: 14px;
                padding: 12px 20px;
                min-width: 160px;
                gap: 8px;
            }
            .btn-action .icon { font-size: 18px; }
            .btn-action .badge { font-size: 10px; padding: 1px 10px; }
        }
        @media (max-width: 480px) {
            .btn-action {
                font-size: 13px;
                padding: 10px 16px;
                min-width: 140px;
                flex-direction: column;
                text-align: center;
                gap: 4px;
                border-radius: 12px;
            }
            .btn-action .icon { font-size: 16px; }
            .btn-action .badge { font-size: 9px; padding: 1px 8px; }
        }
        
        /* ===== أزرار الفلتر ===== */
        .btn-filter {
            background: rgba(255,255,255,0.08);
            color: #d4b8a0;
            border: 1px solid rgba(255,255,255,0.08);
            padding: 7px 16px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.25s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            text-decoration: none;
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
            padding: 6px 14px;
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
        .btn-clear:hover { background: rgba(239, 68, 68, 0.25); }
        
        .badge-filters {
            background: rgba(37, 99, 235, 0.15);
            color: #93bbfc;
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            border: 1px solid rgba(37, 99, 235, 0.15);
        }
        
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
        .filter-panel select {
            width: 100%;
            padding: 8px 12px;
            border-radius: 10px;
            font-size: 13px;
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.08);
            color: #ffffff;
            transition: all 0.25s ease;
        }
        .filter-panel select:focus {
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
        
        .results-count {
            color: #d4b8a0;
            font-size: 13px;
        }
        .results-count strong { color: #ffffff; }
        
        /* ============================================ */
        /* كروت المحلات */
        /* ============================================ */
        .shop-card {
            background: #ffffff;
            border-radius: 16px;
            border: 1px solid #e8edf2;
            transition: all 0.25s ease;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
            overflow: hidden;
            height: 100%;
            display: flex;
            flex-direction: column;
            text-decoration: none;
            color: inherit;
            cursor: pointer;
        }
        
        .shop-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
            border-color: #d0d8e0;
        }
        
        .shop-card:active {
            transform: scale(0.98);
        }
        
        .shop-card .shop-body {
            padding: 16px;
            display: flex;
            flex-direction: column;
            flex: 1;
        }
        
        .shop-card .owner-avatar {
            width: 56px;
            height: 56px;
            border-radius: 14px;
            overflow: hidden;
            flex-shrink: 0;
            background: #e8edf2;
            border: 1px solid #e8edf2;
            margin-bottom: 12px;
        }
        .shop-card .owner-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .shop-card .owner-avatar .no-image {
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
        
        .shop-card .shop-name {
            color: #0f172a;
            font-weight: 700;
            font-size: 15px;
            line-height: 1.3;
        }
        
        .shop-card .shop-owner {
            color: #94a3b8;
            font-size: 11px;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        
        .shop-card .shop-rating {
            display: flex;
            align-items: center;
            gap: 2px;
            margin: 4px 0 6px 0;
        }
        .shop-card .shop-rating i {
            font-size: 12px;
            color: #e2e8f0;
        }
        .shop-card .shop-rating i.active {
            color: #f59e0b;
        }
        
        .shop-card .shop-location {
            color: #94a3b8;
            font-size: 11px;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        
        .shop-card .shop-products {
            color: #94a3b8;
            font-size: 11px;
            display: flex;
            align-items: center;
            gap: 4px;
            margin-top: 2px;
        }
        
        /* ✅ أزرار الكرت */
        .shop-card .shop-actions {
            display: flex;
            gap: 6px;
            margin-top: auto;
            padding-top: 10px;
            border-top: 1px solid #f1f5f9;
        }
        
        .shop-card .btn-action-card {
            flex: 1;
            font-size: 11px;
            font-weight: 600;
            padding: 6px 10px;
            border-radius: 8px;
            text-align: center;
            transition: all 0.25s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
            text-decoration: none;
            border: none;
            min-height: 34px;
        }
        
        .shop-card .btn-whatsapp {
            background: #25D366;
            color: white;
        }
        .shop-card .btn-whatsapp:hover { 
            background: #1da851; 
            transform: translateY(-1px); 
        }
        
        /* زر اذهب للمحل - يفتح خرائط جوجل */
        .shop-card .btn-details {
            background: #2563eb;
            color: white;
        }
        .shop-card .btn-details:hover { 
            background: #1d4ed8; 
            transform: translateY(-1px); 
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.25); 
        }
        
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
        
        @media (max-width: 480px) {
            .shop-card .owner-avatar { width: 44px; height: 44px; }
            .shop-card .shop-name { font-size: 13px; }
            .shop-card .shop-actions { flex-direction: column; gap: 4px; }
            .shop-card .btn-action-card { font-size: 10px; padding: 5px 8px; min-height: 30px; }
            .filter-panel { padding: 12px; }
            .filter-panel .grid { gap: 8px; }
        }
    </style>
</head>
<body>

<div class="main-content">
    <div class="max-w-7xl mx-auto px-3 md:px-6">
        
        <!-- عنوان الصفحة -->
        <div class="page-title text-center mb-6">
            <h1 class="flex items-center justify-center gap-2">
                <i data-lucide="store" style="width:26px;height:26px;"></i>
                محلات مواد البناء
            </h1>
            <p>اعثر على أفضل محلات مواد البناء في منطقتك</p>
            
            <div class="flex flex-wrap justify-center gap-3 mt-4">
                <button id="filterToggleBtn" onclick="toggleFilter()" class="btn-action <?= $has_active_filters ? 'active' : '' ?>">
                    <span class="icon">📍</span>
                    <span>اعثر على محل</span>
                    <span class="badge">فلتر</span>
                </button>
                
                <a href="<?= SITE_URL ?>/search_products.php" class="btn-action btn-search-products">
                    <span class="icon">🔍</span>
                    <span>ابحث عن منتج</span>
                    <span class="badge">اسمنت · حديد</span>
                </a>
            </div>
            
            <?php if (isLoggedIn() && getUserType() === 'shop'): ?>
            <div class="mt-3 flex flex-wrap gap-2 justify-center">
                <a href="<?= SITE_URL ?>/add_product.php" class="inline-flex items-center gap-2 bg-emerald-500 hover:bg-emerald-600 text-white px-4 py-2 rounded-xl transition-all text-sm font-medium">
                    <i data-lucide="plus" style="width:16px;height:16px;"></i>
                    إضافة منتج جديد
                </a>
                <a href="<?= SITE_URL ?>/my_products.php" class="inline-flex items-center gap-2 bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-xl transition-all text-sm font-medium">
                    <i data-lucide="box" style="width:16px;height:16px;"></i>
                    منتجاتي
                </a>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Filter Panel -->
        <div id="filterPanel" class="filter-panel <?= $has_active_filters ? 'show' : '' ?>">
            <form method="GET" action="" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
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
            <?php if (!empty($search)): ?>
            <span class="active-filter-tag">
                🔍 "<?= htmlspecialchars($search) ?>"
                <a href="?<?= http_build_query(array_merge($_GET, ['search' => ''])) ?>">&times;</a>
            </span>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <!-- عدد النتائج -->
        <div class="results-count flex justify-between items-center mb-4 flex-wrap gap-2">
            <p><i data-lucide="store" style="width:14px;height:14px;display:inline;"></i> <strong><?= count($shops) ?></strong> محل</p>
            <?php if (!empty($search)): ?>
            <p class="text-gray-400 text-xs">نتائج البحث عن: "<?= htmlspecialchars($search) ?>"</p>
            <?php endif; ?>
        </div>
        
        <!-- ✅ قائمة المحلات -->
        <?php if (empty($shops)): ?>
        <div class="empty-state">
            <i data-lucide="store" style="width:48px;height:48px;margin:0 auto 12px;display:block;color:rgba(255,255,255,0.15);"></i>
            <p>لا توجد محلات تطابق بحثك</p>
            <a href="<?= SITE_URL ?>/shops_list.php" class="text-blue-400 hover:text-blue-300 mt-4 inline-block">عرض جميع المحلات</a>
        </div>
        <?php else: ?>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <?php foreach ($shops as $shop):
                $avatar_path = null;
                if (!empty($shop['owner_avatar'])) {
                    $full_path = $_SERVER['DOCUMENT_ROOT'] . '/' . $shop['owner_avatar'];
                    if (file_exists($full_path)) {
                        $avatar_path = SITE_URL . '/' . $shop['owner_avatar'];
                    }
                }
                
                // بناء رابط خرائط جوجل
                $google_maps_url = '';
                if ($shop['latitude'] && $shop['longitude']) {
                    $google_maps_url = 'https://www.google.com/maps/dir/?api=1&destination=' . $shop['latitude'] . ',' . $shop['longitude'];
                } elseif ($shop['address']) {
                    $google_maps_url = 'https://www.google.com/maps/dir/?api=1&destination=' . urlencode($shop['address']);
                }
            ?>
            <div class="shop-card" onclick="window.location.href='<?= SITE_URL ?>/shop_detail.php?id=<?= $shop['id'] ?>'">
                <div class="shop-body">
                    <div class="owner-avatar">
                        <?php if ($avatar_path): ?>
                            <img src="<?= $avatar_path ?>" alt="<?= clean($shop['owner_name']) ?>" loading="lazy">
                        <?php else: ?>
                            <div class="no-image"><?= mb_substr($shop['owner_name'] ?? 'م', 0, 1, 'UTF-8') ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <h3 class="shop-name"><?= clean($shop['shop_name']) ?></h3>
                    <p class="shop-owner">
                        <i data-lucide="user" style="width:10px;height:10px;"></i>
                        <?= clean($shop['owner_name']) ?>
                    </p>
                    
                    <div class="shop-rating">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                        <i class="fas fa-star <?= $i <= ($shop['rating'] ?? 0) ? 'active' : '' ?>"></i>
                        <?php endfor; ?>
                    </div>
                    
                    <div class="shop-location">
                        <i data-lucide="map-pin" style="width:12px;height:12px;"></i>
                        <span><?= clean($shop['city_name'] ?? '') ?>، <?= clean($shop['country_name'] ?? '') ?></span>
                    </div>
                    
                    <div class="shop-products">
                        <i data-lucide="package" style="width:12px;height:12px;"></i>
                        <span><?= $shop['products_count'] ?? 0 ?> منتج</span>
                    </div>
                    
                    <!-- ✅ أزرار الكرت -->
                    <div class="shop-actions">
                        <?php if ($shop['whatsapp'] || $shop['user_whatsapp']): ?>
                        <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $shop['whatsapp'] ?: $shop['user_whatsapp']) ?>" target="_blank" class="btn-action-card btn-whatsapp" onclick="event.stopPropagation();">
                            <i data-lucide="message-circle" style="width:14px;height:14px;"></i> واتساب
                        </a>
                        <?php endif; ?>
                        
                        <?php if ($google_maps_url): ?>
                        <a href="<?= $google_maps_url ?>" target="_blank" class="btn-action-card btn-details" onclick="event.stopPropagation();">
                            <i data-lucide="map-pin" style="width:14px;height:14px;"></i> اذهب للمحل
                        </a>
                        <?php else: ?>
                        <span class="btn-action-card btn-details" style="opacity:0.5;cursor:default;background:#94a3b8;" onclick="event.stopPropagation();">
                            <i data-lucide="map-pin" style="width:14px;height:14px;"></i> لا يوجد موقع
                        </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
    </div>
</div>

<script>
    lucide.createIcons();

    function toggleFilter() {
        const panel = document.getElementById('filterPanel');
        const btn = document.getElementById('filterToggleBtn');
        panel.classList.toggle('show');
        btn.classList.toggle('active');
    }

    function closeFilterPanel() {
        const panel = document.getElementById('filterPanel');
        const btn = document.getElementById('filterToggleBtn');
        panel.classList.remove('show');
        btn.classList.remove('active');
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