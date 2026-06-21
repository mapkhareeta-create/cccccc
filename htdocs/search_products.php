<?php
require_once 'config.php';

$search = clean($_GET['search'] ?? '');
$country_id = (int)($_GET['country_id'] ?? 0);
$city_id = (int)($_GET['city_id'] ?? 0);

$page_title = 'البحث عن منتجات - مزاد البناء';
include 'includes/header.php';

// جلب الدول للفلتر
$countries = getCountries();

// جلب المدن إذا تم اختيار دولة
$cities = [];
if ($country_id > 0) {
    $cities = getCities($country_id);
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>البحث عن منتجات - مزاد البناء</title>
    <script src="https://cdn.tailwindcss.com/3.4.17"></script>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;900&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/lucide@0.263.0/dist/umd/lucide.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        * { font-family: 'DM Sans', sans-serif; box-sizing: border-box; }
        
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
        
        .search-box {
            background: rgba(255,255,255,0.06);
            border-radius: 16px;
            padding: 16px;
            border: 1px solid rgba(255,255,255,0.06);
            margin-bottom: 20px;
        }
        .search-box input {
            width: 100%;
            padding: 12px 16px;
            border-radius: 12px;
            font-size: 15px;
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.08);
            color: #ffffff;
            transition: all 0.25s ease;
        }
        .search-box input:focus {
            border-color: #2563eb;
            outline: none;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
            background: rgba(255,255,255,0.1);
        }
        .search-box input::placeholder {
            color: rgba(255,255,255,0.3);
        }
        .search-box select {
            width: 100%;
            padding: 12px 16px;
            border-radius: 12px;
            font-size: 14px;
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.08);
            color: #ffffff;
            transition: all 0.25s ease;
        }
        .search-box select:focus {
            border-color: #2563eb;
            outline: none;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
            background: rgba(255,255,255,0.1);
        }
        .search-box select option {
            background: #1a0e0a;
            color: #ffffff;
        }
        .search-box .btn-search {
            background: #2563eb;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 15px;
            cursor: pointer;
            transition: all 0.25s ease;
            min-height: 50px;
        }
        .search-box .btn-search:hover {
            background: #1d4ed8;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.2);
        }
        
        /* ===== كروت المنتجات ===== */
        .product-card {
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
        }
        .product-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
            border-color: #d0d8e0;
        }
        
        .product-card .product-image {
            height: 160px;
            background: #f8fafc;
            position: relative;
            overflow: hidden;
        }
        .product-card .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .product-card .product-image .no-image {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #cbd5e1;
            font-size: 40px;
        }
        
        .product-card .product-body {
            padding: 14px 16px;
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        .product-card .product-name {
            color: #0f172a;
            font-weight: 700;
            font-size: 15px;
        }
        .product-card .product-price {
            color: #2563eb;
            font-weight: 700;
            font-size: 16px;
            margin-top: 4px;
        }
        .product-card .product-price .currency {
            color: #94a3b8;
            font-weight: 400;
            font-size: 12px;
        }
        .product-card .shop-name-link {
            color: #64748b;
            font-size: 12px;
            margin-top: 4px;
        }
        .product-card .shop-name-link a {
            color: #2563eb;
            text-decoration: none;
        }
        .product-card .shop-name-link a:hover {
            text-decoration: underline;
        }
        .product-card .shop-location {
            color: #94a3b8;
            font-size: 11px;
            display: flex;
            align-items: center;
            gap: 4px;
            margin-top: 2px;
        }
        
        .product-card .product-actions {
            display: flex;
            gap: 6px;
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid #f1f5f9;
        }
        .product-card .btn-whatsapp {
            flex: 1;
            background: #25D366;
            color: white;
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
        }
        .product-card .btn-whatsapp:hover {
            background: #1da851;
            transform: translateY(-1px);
        }
        .product-card .btn-details {
            flex: 1;
            background: #2563eb;
            color: white;
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
        }
        .product-card .btn-details:hover {
            background: #1d4ed8;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.25);
        }
        
        .results-count {
            color: #d4b8a0;
            font-size: 13px;
        }
        .results-count strong { color: #ffffff; }
        
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
        
        @media (max-width: 480px) {
            .product-card .product-image { height: 120px; }
            .product-card .product-name { font-size: 13px; }
            .product-card .product-price { font-size: 14px; }
            .search-box input { font-size: 13px; padding: 10px 14px; }
            .search-box .btn-search { font-size: 13px; padding: 10px 18px; min-height: 44px; }
        }
    </style>
</head>
<body>

<div class="main-content">
    <div class="max-w-7xl mx-auto px-3 md:px-6">
        
        <!-- عنوان الصفحة -->
        <div class="page-title text-center mb-6">
            <h1 class="flex items-center justify-center gap-2">
                <i data-lucide="search" style="width:26px;height:26px;"></i>
                البحث عن منتجات
            </h1>
            <p>ابحث عن أي منتج بناء في منطقتك</p>
        </div>
        
        <!-- صندوق البحث + الفلاتر -->
        <div class="search-box">
            <form method="GET" action="" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                <div class="lg:col-span-1">
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="اكتب اسم المنتج..." class="w-full">
                </div>
                
                <div>
                    <select name="country_id" id="search-country" onchange="loadCities('search-country', 'search-city')">
                        <option value="0">🌍 جميع الدول</option>
                        <?php foreach ($countries as $country): ?>
                        <option value="<?= $country['id'] ?>" <?= $country_id == $country['id'] ? 'selected' : '' ?>>
                            <?= $country['flag'] ?? '' ?> <?= $country['name_ar'] ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <select name="city_id" id="search-city">
                        <option value="0">🏙️ جميع المدن</option>
                        <?php foreach ($cities as $city): ?>
                        <option value="<?= $city['id'] ?>" <?= $city_id == $city['id'] ? 'selected' : '' ?>>
                            <?= clean($city['name_ar']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <button type="submit" class="btn-search w-full">
                        <i data-lucide="search" style="width:18px;height:18px;"></i> بحث
                    </button>
                </div>
            </form>
        </div>
        
        <?php if (!empty($search)): 
            // ✅ جلب المنتجات مع فلتر الدولة والمدينة
            $sql = "
                SELECT p.*, s.shop_name, s.id as shop_id, s.whatsapp, u.phone as user_phone, u.whatsapp as user_whatsapp,
                       c.name_ar as country_name, ci.name_ar as city_name,
                       (SELECT pi.image_path FROM product_images pi WHERE pi.product_id = p.id AND pi.is_main = 1 LIMIT 1) as main_image
                FROM products p
                JOIN shops s ON p.shop_id = s.id
                JOIN users u ON s.user_id = u.id
                LEFT JOIN countries c ON s.country_id = c.id
                LEFT JOIN cities ci ON s.city_id = ci.id
                WHERE s.status = 'active'
                AND (
                    p.name LIKE ? 
                    OR p.description LIKE ? 
                    OR p.category LIKE ?
                )
            ";
            
            $params = ["%$search%", "%$search%", "%$search%"];
            
            // ✅ فلتر الدولة
            if ($country_id > 0) {
                $sql .= " AND s.country_id = ?";
                $params[] = $country_id;
            }
            
            // ✅ فلتر المدينة
            if ($city_id > 0) {
                $sql .= " AND s.city_id = ?";
                $params[] = $city_id;
            }
            
            $sql .= " ORDER BY p.created_at DESC";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $products = $stmt->fetchAll();
        ?>
        
        <!-- الفلاتر النشطة -->
        <?php if ($country_id > 0 || $city_id > 0): ?>
        <div class="active-filters">
            <span class="label"><i data-lucide="tags" style="width:12px;height:12px;"></i> الموقع:</span>
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
        </div>
        <?php endif; ?>
        
        <!-- عدد النتائج -->
        <div class="results-count flex justify-between items-center mb-4 flex-wrap gap-2">
            <p><i data-lucide="package" style="width:14px;height:14px;display:inline;"></i> <strong><?= count($products) ?></strong> منتج</p>
            <p class="text-gray-400 text-xs">نتائج البحث عن: "<?= htmlspecialchars($search) ?>"</p>
        </div>
        
        <!-- قائمة المنتجات -->
        <?php if (empty($products)): ?>
        <div class="empty-state">
            <i data-lucide="search-x" style="width:48px;height:48px;margin:0 auto 12px;display:block;color:rgba(255,255,255,0.15);"></i>
            <p>لا توجد منتجات تطابق بحثك في هذا الموقع</p>
            <a href="<?= SITE_URL ?>/search_products.php" class="text-blue-400 hover:text-blue-300 mt-4 inline-block">بحث جديد</a>
        </div>
        <?php else: ?>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
            <?php foreach ($products as $product): 
                $image_path = !empty($product['main_image']) && file_exists(__DIR__ . '/' . $product['main_image']) 
                             ? SITE_URL . '/' . $product['main_image'] 
                             : null;
            ?>
            <div class="product-card">
                <div class="product-image">
                    <?php if ($image_path): ?>
                        <img src="<?= $image_path ?>" alt="<?= clean($product['name']) ?>" loading="lazy">
                    <?php else: ?>
                        <div class="no-image"><i class="fas fa-box"></i></div>
                    <?php endif; ?>
                </div>
                <div class="product-body">
                    <h3 class="product-name"><?= clean($product['name']) ?></h3>
                    <?php if ($product['price']): ?>
                    <div class="product-price"><?= number_format($product['price']) ?> <span class="currency">ر.س</span></div>
                    <?php else: ?>
                    <div class="product-price" style="color:#94a3b8;font-size:13px;">السعر عند الاستفسار</div>
                    <?php endif; ?>
                    <div class="shop-name-link">
                        <i data-lucide="store" style="width:12px;height:12px;display:inline;"></i>
                        <a href="<?= SITE_URL ?>/shop_detail.php?id=<?= $product['shop_id'] ?>"><?= clean($product['shop_name']) ?></a>
                    </div>
                    <div class="shop-location">
                        <i data-lucide="map-pin" style="width:12px;height:12px;"></i>
                        <span><?= clean($product['city_name'] ?? '') ?>، <?= clean($product['country_name'] ?? '') ?></span>
                    </div>
                    
                    <div class="product-actions">
                        <?php $whatsapp = $product['whatsapp'] ?? $product['user_whatsapp'] ?? ''; ?>
                        <?php if (!empty($whatsapp)): ?>
                        <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $whatsapp) ?>?text=<?= urlencode('مرحباً، أريد الاستفسار عن: ' . $product['name']) ?>" target="_blank" class="btn-whatsapp">
                            <i data-lucide="message-circle" style="width:12px;height:12px;"></i> استفسار
                        </a>
                        <?php endif; ?>
                        <a href="<?= SITE_URL ?>/shop_detail.php?id=<?= $product['shop_id'] ?>" class="btn-details">
                            <i data-lucide="eye" style="width:12px;height:12px;"></i> عرض المحل
                        </a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
        <?php else: ?>
        <!-- إذا لم يكتب بحث بعد -->
        <div class="empty-state">
            <i data-lucide="search" style="width:48px;height:48px;margin:0 auto 12px;display:block;color:rgba(255,255,255,0.15);"></i>
            <p>ابحث عن أي منتج بناء</p>
            <p class="text-gray-500 text-sm mt-1">مثل: اسمنت، حديد، طابوق، دهان، خشب، سيراميك</p>
        </div>
        <?php endif; ?>
        
    </div>
</div>

<script>
    lucide.createIcons();

    function loadCities(countrySelectId, citySelectId) {
        const countrySelect = document.getElementById(countrySelectId);
        const citySelect = document.getElementById(citySelectId);
        const countryId = countrySelect.value;
        
        citySelect.innerHTML = '<option value="0">جاري التحميل...</option>';
        if (!countryId || countryId == 0) {
            citySelect.innerHTML = '<option value="0">🏙️ جميع المدن</option>';
            return;
        }
        
        fetch('<?= SITE_URL ?>/api.php?action=get_cities&country_id=' + countryId)
            .then(response => response.json())
            .then(data => {
                citySelect.innerHTML = '<option value="0">🏙️ جميع المدن</option>';
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
                citySelect.innerHTML = '<option value="0">🏙️ جميع المدن</option>';
            });
    }
</script>

<?php include 'includes/footer.php'; ?>
</body>
</html>