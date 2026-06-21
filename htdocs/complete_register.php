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
           ci.name_ar as city_name,
           (SELECT COUNT(*) FROM reviews WHERE reviewed_id = u.id) as reviews_count
    FROM users u
    LEFT JOIN countries c ON u.country_id = c.id
    LEFT JOIN cities ci ON u.city_id = ci.id
    WHERE u.user_type = 'contractor' AND (u.status = 'active' OR u.status IS NULL)
";

$params = [];

// فلتر البحث بالاسم
if (!empty($search)) {
    $sql .= " AND u.name LIKE ?";
    $params[] = "%$search%";
}

// فلتر التخصص
if (!empty($specialization)) {
    $sql .= " AND EXISTS (SELECT 1 FROM contractor_specs WHERE user_id = u.id AND spec = ?)";
    $params[] = $specialization;
}

// فلتر تصنيف المقاول
if (!empty($contractor_class) && $contractor_class != 'all') {
    $sql .= " AND u.contractor_class = ?";
    $params[] = $contractor_class;
}

// فلتر الدولة
if ($country_id > 0) {
    $sql .= " AND u.country_id = ?";
    $params[] = $country_id;
}

// فلتر المدينة
if ($city_id > 0) {
    $sql .= " AND u.city_id = ?";
    $params[] = $city_id;
}

// فلتر الحساب الموثق
if ($is_verified == 1) {
    $sql .= " AND u.verification_status = 'approved'";
}

// فلتر التقييم الأدنى
if ($min_rating > 0) {
    $sql .= " AND u.rating >= ?";
    $params[] = $min_rating;
}

// ترتيب النتائج
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

// التحقق من وجود فلاتر نشطة
$has_active_filters = ($search || $specialization || ($contractor_class != '' && $contractor_class != 'all') || $country_id > 0 || $city_id > 0 || $is_verified == 1 || $min_rating > 0);

// تعريف فئات التصنيف
$contractor_classes = [
    'all' => ['name' => 'جميع التصنيفات', 'icon' => 'fa-chart-line', 'color' => 'gray'],
    'A' => ['name' => 'تصنيف أ', 'icon' => 'fa-crown', 'color' => 'green'],
    'B' => ['name' => 'تصنيف ب', 'icon' => 'fa-medal', 'color' => 'blue'],
    'C' => ['name' => 'تصنيف ج', 'icon' => 'fa-chart-line', 'color' => 'amber'],
    'D' => ['name' => 'تصنيف د', 'icon' => 'fa-seedling', 'color' => 'orange']
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
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700;900&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/lucide@0.263.0/dist/umd/lucide.min.js"></script>
    <style>
        * { font-family: 'DM Sans', sans-serif; }
        .hero-bg { position: relative; overflow: hidden; }
        .hero-bg::before {
            content: ''; position: absolute; inset: 0;
            background: linear-gradient(135deg, rgba(15,23,42,0.88), rgba(30,64,175,0.75));
            z-index: 1;
        }
        .hero-bg img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; }
        .hero-content { position: relative; z-index: 2; }
        .contractor-card {
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 50%, #1e40af 100%);
            border-radius: 14px; overflow: hidden;
            transition: transform 0.3s, box-shadow 0.3s;
            box-shadow: 0 4px 15px rgba(37,99,235,0.3);
            cursor: pointer;
        }
        .contractor-card:hover { transform: translateY(-4px); box-shadow: 0 12px 30px rgba(37,99,235,0.4); }
        .filter-panel { display: none; animation: slideDown 0.4s ease; }
        .filter-panel.show { display: block; }
        @keyframes slideDown { from { opacity:0; transform:translateY(-15px); } to { opacity:1; transform:translateY(0); } }
        .star-active { color: #fbbf24; }
        .star-inactive { color: rgba(255,255,255,0.2); }
        
        /* ===== POPUP STYLES ===== */
        .popup-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.7);
            backdrop-filter: blur(8px);
            z-index: 9999;
            justify-content: center;
            align-items: center;
            animation: fadeIn 0.3s ease;
        }
        .popup-overlay.active {
            display: flex;
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        @keyframes slideUp {
            from { transform: translateY(30px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        .popup-box {
            background: linear-gradient(145deg, #1e293b, #0f172a);
            border-radius: 24px;
            padding: 40px;
            max-width: 440px;
            width: 90%;
            text-align: center;
            box-shadow: 0 30px 80px rgba(0,0,0,0.6);
            border: 1px solid rgba(255,255,255,0.08);
            animation: slideUp 0.4s ease;
            position: relative;
        }
        .popup-box .popup-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            box-shadow: 0 8px 30px rgba(59,130,246,0.4);
        }
        .popup-box .popup-icon i {
            color: white;
            width: 36px;
            height: 36px;
        }
        .popup-box h2 {
            color: white;
            font-size: 22px;
            font-weight: 900;
            margin-bottom: 8px;
        }
        .popup-box p {
            color: rgba(255,255,255,0.6);
            font-size: 14px;
            line-height: 1.6;
            margin-bottom: 24px;
        }
        .popup-box .btn-group {
            display: flex;
            gap: 10px;
        }
        .popup-box .btn-group a {
            flex: 1;
            padding: 12px 20px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 14px;
            text-decoration: none;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .popup-box .btn-register {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: white;
            box-shadow: 0 4px 15px rgba(59,130,246,0.3);
        }
        .popup-box .btn-register:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(59,130,246,0.4);
        }
        .popup-box .btn-login {
            background: rgba(255,255,255,0.08);
            color: rgba(255,255,255,0.8);
            border: 1px solid rgba(255,255,255,0.1);
        }
        .popup-box .btn-login:hover {
            background: rgba(255,255,255,0.15);
            transform: translateY(-2px);
        }
        .popup-close {
            position: absolute;
            top: 16px;
            left: 16px;
            background: rgba(255,255,255,0.06);
            border: none;
            color: rgba(255,255,255,0.4);
            width: 36px;
            height: 36px;
            border-radius: 50%;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .popup-close:hover {
            background: rgba(255,255,255,0.12);
            color: white;
            transform: rotate(90deg);
        }
        .popup-box .features {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
            margin: 16px 0 20px;
            text-align: right;
        }
        .popup-box .features span {
            color: rgba(255,255,255,0.5);
            font-size: 12px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .popup-box .features span i {
            color: #60a5fa;
            width: 14px;
            height: 14px;
        }
        
        /* تنسيق الكرت - إزالة خانة تسجيل الدخول */
        .card-link {
            text-decoration: none;
            color: inherit;
            display: block;
        }
        .contractor-card .card-actions {
            display: flex;
            gap: 8px;
            margin-top: 8px;
            padding-top: 10px;
            border-top: 1px solid rgba(255,255,255,0.12);
        }
        .contractor-card .btn-view {
            flex: 1;
            background: white;
            color: #1d4ed8;
            font-weight: 600;
            font-size: 10px;
            padding: 7px 12px;
            border-radius: 10px;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
            pointer-events: none;
        }
        .contractor-card .btn-chat {
            background: rgba(255,255,255,0.12);
            color: white;
            font-size: 10px;
            padding: 7px 12px;
            border-radius: 10px;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
            border: 1px solid rgba(255,255,255,0.08);
            pointer-events: none;
        }
    </style>
</head>
<body class="min-h-screen">

<!-- Hero -->
<header class="hero-bg" style="min-height: 28vh;">
    <img src="https://images.unsplash.com/photo-1541888946425-d81bb19240f5?w=1920&q=80" alt="خلفية البناء" loading="lazy">
    <div class="hero-content flex flex-col items-center justify-center text-center px-4 py-14 md:py-18">
        <h1 class="text-white font-black text-2xl md:text-4xl mb-2 drop-shadow-lg flex items-center gap-2">
            <i data-lucide="hard-hat" style="width:28px;height:28px;"></i>
            قائمة المقاولين
        </h1>
        <p class="text-blue-100 text-sm md:text-base max-w-xl">اختر أفضل المقاولين لمشروعك حسب التخصص والتصنيف والموقع</p>
    </div>
</header>

<main class="max-w-7xl mx-auto px-3 md:px-6 -mt-6 relative z-10 pb-16">
    
    <!-- Filter Controls -->
    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <div class="flex gap-3 flex-wrap items-center">
            <button id="filterToggleBtn" onclick="toggleFilter()" class="bg-gradient-to-l from-blue-600 to-blue-800 text-white border-none px-5 py-2.5 rounded-xl font-bold text-sm cursor-pointer shadow-lg shadow-blue-500/30 hover:-translate-y-0.5 transition-all inline-flex items-center gap-2">
                <i data-lucide="sliders-horizontal" style="width:16px;height:16px;"></i>
                <span id="filterBtnText"><?= $has_active_filters ? 'إخفاء الفلترة' : 'عرض الفلترة' ?></span>
            </button>
            <?php if ($has_active_filters): ?>
            <a href="<?= SITE_URL ?>/contractors_list.php" class="bg-red-500/20 text-red-300 text-xs font-semibold px-4 py-2.5 rounded-xl border border-red-500/20 hover:bg-red-500/30 transition-all inline-flex items-center gap-2">
                <i data-lucide="eraser" style="width:14px;height:14px;"></i> تنظيف الفلاتر
            </a>
            <?php endif; ?>
        </div>
        <?php if ($has_active_filters): ?>
        <span class="bg-blue-500/20 text-blue-300 text-xs font-semibold px-4 py-2 rounded-full border border-blue-500/20 inline-flex items-center gap-2">
            <i data-lucide="filter" style="width:12px;height:12px;"></i> فلاتر نشطة
        </span>
        <?php endif; ?>
    </div>

    <!-- Filter Panel -->
    <div id="filterPanel" class="filter-panel <?= $has_active_filters ? 'show' : '' ?> bg-white/95 backdrop-blur-xl rounded-2xl p-5 border border-white/20 shadow-xl mb-5">
        <form method="GET" action="" id="filterForm" class="grid grid-cols-2 md:grid-cols-4 gap-3">
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">🌍 الدولة</label>
                <select name="country_id" id="filter-country" onchange="loadCities('filter-country', 'filter-city')" class="w-full border-2 border-slate-200 rounded-xl px-3 py-2.5 text-xs bg-white text-slate-700 focus:border-blue-500 focus:outline-none">
                    <option value="0">جميع الدول</option>
                    <?php foreach ($countries as $country): ?>
                    <option value="<?= $country['id'] ?>" <?= $country_id == $country['id'] ? 'selected' : '' ?>>
                        <?= $country['flag'] ?? '' ?> <?= $country['name_ar'] ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">📌 التخصص</label>
                <select name="specialization" class="w-full border-2 border-slate-200 rounded-xl px-3 py-2.5 text-xs bg-white text-slate-700 focus:border-blue-500 focus:outline-none">
                    <option value="">جميع التخصصات</option>
                    <?php foreach ($all_specializations as $spec): ?>
                    <option value="<?= htmlspecialchars($spec) ?>" <?= $specialization == $spec ? 'selected' : '' ?>>
                        <?= htmlspecialchars($spec) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">🏆 التصنيف</label>
                <select name="class" class="w-full border-2 border-slate-200 rounded-xl px-3 py-2.5 text-xs bg-white text-slate-700 focus:border-blue-500 focus:outline-none">
                    <?php foreach ($contractor_classes as $key => $class): ?>
                    <option value="<?= $key ?>" <?= $contractor_class == $key ? 'selected' : '' ?>>
                        <?= $class['icon'] ?> <?= $class['name'] ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">⭐ الحد الأدنى</label>
                <select name="min_rating" class="w-full border-2 border-slate-200 rounded-xl px-3 py-2.5 text-xs bg-white text-slate-700 focus:border-blue-500 focus:outline-none">
                    <option value="0" <?= $min_rating == 0 ? 'selected' : '' ?>>الجميع</option>
                    <option value="4.5" <?= $min_rating == 4.5 ? 'selected' : '' ?>>4.5 نجوم فما فوق</option>
                    <option value="4.0" <?= $min_rating == 4.0 ? 'selected' : '' ?>>4.0 نجوم فما فوق</option>
                    <option value="3.5" <?= $min_rating == 3.5 ? 'selected' : '' ?>>3.5 نجوم فما فوق</option>
                    <option value="3.0" <?= $min_rating == 3.0 ? 'selected' : '' ?>>3.0 نجوم فما فوق</option>
                </select>
            </div>
            
            <div class="col-span-2 md:col-span-4 flex gap-3">
                <button type="submit" class="flex-1 bg-blue-700 hover:bg-blue-800 text-white font-bold py-2.5 rounded-xl text-sm transition-colors flex items-center justify-center gap-2">
                    <i data-lucide="search" style="width:14px;height:14px;"></i> بحث
                </button>
                <button type="button" onclick="closeFilterPanel()" class="flex-1 bg-slate-100 hover:bg-slate-200 text-slate-600 font-semibold py-2.5 rounded-xl text-sm transition-colors flex items-center justify-center gap-2">
                    <i data-lucide="x" style="width:14px;height:14px;"></i> إغلاق
                </button>
            </div>
        </form>
    </div>

    <!-- Section Header -->
    <div class="flex items-center justify-between mb-5">
        <h2 class="text-white font-black text-lg md:text-xl flex items-center gap-2">
            <i data-lucide="users" style="width:20px;height:20px;"></i>
            <span>المقاولون</span>
            <span class="text-sm font-normal text-blue-300">(<?= count($contractors) ?>)</span>
        </h2>
    </div>

    <!-- Contractor Grid -->
    <?php if (empty($contractors)): ?>
    <div class="bg-white/10 backdrop-blur-xl rounded-2xl p-12 text-center border border-white/10">
        <i data-lucide="hard-hat" style="width:48px;height:48px;color:rgba(255,255,255,0.2);margin:0 auto 16px;"></i>
        <p class="text-white/40 text-base">لا توجد مقاولين مطابقين لبحثك</p>
        <a href="<?= SITE_URL ?>/contractors_list.php" class="text-blue-400 hover:text-blue-300 mt-4 inline-block">عرض جميع المقاولين</a>
    </div>
    <?php else: ?>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
        <?php foreach ($contractors as $contractor): 
        $specs = getContractorSpecializations($contractor['id']);
        $class_info = $contractor_classes[$contractor['contractor_class'] ?? 'all'];
        $rating = round($contractor['rating'] ?? 0, 1);
        $avatar_path = !empty($contractor['avatar']) && file_exists($contractor['avatar']) 
                     ? SITE_URL . '/' . $contractor['avatar'] 
                     : null;
        ?>
        <div class="contractor-card p-4 flex flex-col gap-2" onclick="handleCardClick(<?= $contractor['id'] ?>, event)">
            <!-- رأس البطاقة -->
            <div class="flex items-center gap-3">
                <div class="w-11 h-11 rounded-xl overflow-hidden flex-shrink-0 border-2 border-white/15">
                    <?php if ($avatar_path): ?>
                        <img src="<?= $avatar_path ?>" class="w-full h-full object-cover" alt="<?= htmlspecialchars($contractor['name']) ?>">
                    <?php else: ?>
                        <div class="w-full h-full flex items-center justify-center bg-white/10 text-white font-bold text-lg">
                            <?= mb_substr($contractor['name'], 0, 1, 'UTF-8') ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="flex-1 min-w-0">
                    <h3 class="text-white font-bold text-sm truncate"><?= htmlspecialchars($contractor['name']) ?></h3>
                    <div class="flex gap-1 flex-wrap mt-0.5">
                        <span class="bg-white/20 text-white text-[9px] px-2 py-0.5 rounded-full inline-flex items-center gap-1">
                            <i data-lucide="crown" style="width:10px;height:10px;"></i>
                            <?= $class_info['name'] ?>
                        </span>
                        <?php if ($contractor['verification_status'] === 'approved'): ?>
                        <span class="bg-green-400/30 text-green-200 text-[9px] px-2 py-0.5 rounded-full inline-flex items-center gap-1">
                            <i data-lucide="check-circle" style="width:10px;height:10px;"></i> موثق
                        </span>
                        <?php endif; ?>
                        <?php if ($contractor['is_featured']): ?>
                        <span class="bg-yellow-400/30 text-yellow-200 text-[9px] px-2 py-0.5 rounded-full inline-flex items-center gap-1">
                            <i data-lucide="star" style="width:10px;height:10px;"></i> مميز
                        </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- التقييم -->
            <div class="flex items-center gap-1">
                <div class="flex gap-0.5">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                    <i data-lucide="star" class="w-3 h-3 <?= $i <= round($rating) ? 'text-yellow-400' : 'text-white/20' ?> fill-current"></i>
                    <?php endfor; ?>
                </div>
                <?php if ($rating > 0): ?>
                <span class="text-yellow-400 text-[11px] font-bold mr-1"><?= number_format($rating, 1) ?></span>
                <?php endif; ?>
                <span class="text-white/50 text-[10px]">(<?= $contractor['reviews_count'] ?? 0 ?>)</span>
            </div>

            <!-- الموقع -->
            <div class="flex items-center gap-1 text-[11px] text-white/70">
                <i data-lucide="map-pin" style="width:10px;height:10px;"></i>
                <span><?= htmlspecialchars($contractor['city_name'] ?? '') ?>, <?= htmlspecialchars($contractor['country_name'] ?? '') ?></span>
            </div>

            <!-- التخصصات -->
            <?php if (!empty($specs)): ?>
            <div class="flex flex-wrap gap-1">
                <?php foreach (array_slice($specs, 0, 3) as $spec): ?>
                <span class="bg-white/10 text-white/80 text-[9px] px-2 py-0.5 rounded-full border border-white/5"><?= htmlspecialchars($spec) ?></span>
                <?php endforeach; ?>
                <?php if (count($specs) > 3): ?>
                <span class="text-white/40 text-[9px] flex items-center">+<?= count($specs) - 3 ?></span>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- الأزرار -->
            <div class="card-actions">
                <span class="btn-view">
                    <i data-lucide="eye" style="width:12px;height:12px;"></i> عرض الملف
                </span>
                <span class="btn-chat">
                    <i data-lucide="message-circle" style="width:12px;height:12px;"></i>
                </span>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

</main>

<!-- ===== POPUP ===== -->
<div id="loginPopup" class="popup-overlay" onclick="closePopup(event)">
    <div class="popup-box" onclick="event.stopPropagation()">
        <button class="popup-close" onclick="closePopup()">
            <i data-lucide="x" style="width:18px;height:18px;"></i>
        </button>
        
        <div class="popup-icon">
            <i data-lucide="user-plus" style="width:36px;height:36px;"></i>
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
    // تفعيل أيقونات Lucide
    lucide.createIcons();

    // ===== دوال البوب اب =====
    function openPopup() {
        document.getElementById('loginPopup').classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closePopup(event) {
        if (event && event.target !== event.currentTarget) return;
        document.getElementById('loginPopup').classList.remove('active');
        document.body.style.overflow = '';
    }

    // ===== معالج النقر على الكرت =====
    function handleCardClick(contractorId, event) {
        <?php if (isLoggedIn() && getUserType() === 'employer'): ?>
            // المستخدم مسجل - انتقل إلى صفحة الملف الشخصي
            window.location.href = '<?= SITE_URL ?>/profile.php?id=' + contractorId;
        <?php else: ?>
            // المستخدم غير مسجل - اعرض البوب اب
            openPopup();
        <?php endif; ?>
    }

    // ===== دوال الفلترة =====
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

    // ===== تحميل المدن =====
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

    // إغلاق البوب اب عند الضغط على ESC
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closePopup();
        }
    });

    console.log('✅ contractors_list.php loaded successfully');
</script>

<?php include 'includes/footer.php'; ?>
</body>
</html>