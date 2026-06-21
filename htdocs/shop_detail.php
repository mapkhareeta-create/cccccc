<?php
require_once 'config.php';

$shop_id = (int)($_GET['id'] ?? 0);
if (!$shop_id) redirect('shops.php');

// جلب بيانات المحل
$stmt = $pdo->prepare("
    SELECT s.*, u.name as owner_name, u.phone as user_phone, u.whatsapp as user_whatsapp, u.email,
           c.name_ar as country_name, ci.name_ar as city_name, co.currency_ar
    FROM shops s
    JOIN users u ON s.user_id = u.id
    LEFT JOIN countries c ON s.country_id = c.id
    LEFT JOIN cities ci ON s.city_id = ci.id
    LEFT JOIN countries co ON s.country_id = co.id
    WHERE s.id = ? AND s.status = 'active'
");
$stmt->execute([$shop_id]);
$shop = $stmt->fetch();

if (!$shop) redirect('shops.php');

// جلب منتجات المحل
$stmt = $pdo->prepare("
    SELECT p.*, 
           (SELECT pi.image_path FROM product_images pi WHERE pi.product_id = p.id AND pi.is_main = 1 LIMIT 1) as main_image
    FROM products p
    WHERE p.shop_id = ?
    ORDER BY p.is_available DESC, p.created_at DESC
");
$stmt->execute([$shop_id]);
$products = $stmt->fetchAll();

$page_title = clean($shop['shop_name']);
include 'includes/header.php';
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= clean($shop['shop_name']) ?> - مزاد البناء</title>
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
        /* عنوان المحل - مثل البروفايل */
        /* ============================================ */
        .shop-header-card {
            background: #ffffff;
            border-radius: 16px;
            border: 1px solid #e8edf2;
            overflow: hidden;
            margin-bottom: 24px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
        }
        
        .shop-header-card .cover {
            height: 100px;
            background: linear-gradient(135deg, #1a3a6a, #2563eb);
            position: relative;
        }
        
        .shop-header-card .shop-avatar {
            position: absolute;
            bottom: -16px;
            right: 20px;
            width: 56px;
            height: 56px;
            background: #ffffff;
            border-radius: 14px;
            border: 2px solid #e8edf2;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        }
        
        .shop-header-card .shop-avatar i {
            color: #2563eb;
            font-size: 24px;
        }
        
        .shop-header-card .body {
            padding: 22px 20px 16px 20px;
        }
        
        .shop-header-card .shop-name {
            color: #0f172a;
            font-weight: 700;
            font-size: 20px;
        }
        
        .shop-header-card .shop-meta {
            color: #94a3b8;
            font-size: 13px;
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 4px;
        }
        
        .shop-header-card .shop-meta i {
            font-size: 13px;
        }
        
        .shop-header-card .shop-desc {
            color: #475569;
            font-size: 14px;
            line-height: 1.6;
            margin-top: 10px;
        }
        
        /* ✅ أزرار مثل البروفايل */
        .shop-header-card .shop-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 14px;
            padding-top: 14px;
            border-top: 1px solid #f1f5f9;
        }
        
        .shop-header-card .btn-action {
            padding: 10px 20px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 14px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.25s ease;
            border: none;
            cursor: pointer;
            flex: 1;
            justify-content: center;
            min-width: 120px;
        }
        
        .shop-header-card .btn-whatsapp {
            background: #25D366;
            color: white;
            box-shadow: 0 4px 12px rgba(37, 211, 102, 0.3);
        }
        .shop-header-card .btn-whatsapp:hover { 
            background: #1da851; 
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(37, 211, 102, 0.4);
        }
        
        .shop-header-card .btn-phone {
            background: #2563eb;
            color: white;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
        }
        .shop-header-card .btn-phone:hover { 
            background: #1d4ed8; 
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(37, 99, 235, 0.4);
        }
        
        .shop-header-card .btn-map {
            background: #f1f5f9;
            color: #475569;
            border: 1px solid #e8edf2;
            box-shadow: none;
        }
        .shop-header-card .btn-map:hover { 
            background: #e8edf2; 
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.06);
        }
        
        /* ============================================ */
        /* قسم المنتجات */
        /* ============================================ */
        .section-title {
            color: #ffffff;
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 16px;
        }
        .section-title span { color: #93bbfc; }
        
        .product-card {
            background: #ffffff;
            border-radius: 14px;
            border: 1px solid #e8edf2;
            overflow: hidden;
            transition: all 0.25s ease;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        
        .product-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
            border-color: #d0d8e0;
        }
        
        .product-card .image {
            height: 140px;
            background: #f8fafc;
            position: relative;
            overflow: hidden;
        }
        
        .product-card .image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .product-card .image .no-image {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #cbd5e1;
            font-size: 32px;
        }
        
        .product-card .image .badge-category {
            position: absolute;
            top: 8px;
            right: 8px;
            background: rgba(37, 99, 235, 0.9);
            color: white;
            font-size: 9px;
            font-weight: 600;
            padding: 2px 10px;
            border-radius: 12px;
        }
        
        .product-card .image .badge-unavailable {
            position: absolute;
            inset: 0;
            background: rgba(0,0,0,0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 14px;
        }
        
        .product-card .body {
            padding: 12px 14px;
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        
        .product-card .product-name {
            color: #0f172a;
            font-weight: 700;
            font-size: 14px;
        }
        
        .product-card .product-desc {
            color: #94a3b8;
            font-size: 12px;
            line-height: 1.4;
            margin-top: 2px;
            flex: 1;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .product-card .product-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: 8px;
            padding-top: 8px;
            border-top: 1px solid #f1f5f9;
        }
        
        .product-card .product-price {
            color: #2563eb;
            font-weight: 700;
            font-size: 14px;
        }
        
        .product-card .product-price .currency {
            color: #94a3b8;
            font-weight: 400;
            font-size: 11px;
        }
        
        .product-card .btn-inquire {
            background: #25D366;
            color: white;
            font-size: 11px;
            font-weight: 600;
            padding: 4px 12px;
            border-radius: 8px;
            text-decoration: none;
            transition: all 0.25s ease;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .product-card .btn-inquire:hover { background: #1da851; transform: translateY(-1px); }
        
        /* ============================================ */
        /* حالة عدم وجود منتجات */
        /* ============================================ */
        .empty-state {
            background: rgba(255,255,255,0.04);
            border-radius: 16px;
            padding: 40px 20px;
            text-align: center;
            border: 1px solid rgba(255,255,255,0.04);
        }
        .empty-state i { color: rgba(255,255,255,0.15); }
        .empty-state p { color: #d4b8a0; font-size: 15px; }
        
        /* ============================================ */
        /* مودال الخريطة مع زر خرائط جوجل */
        /* ============================================ */
        .map-modal {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 9999;
            background: rgba(0,0,0,0.7);
            backdrop-filter: blur(8px);
            align-items: center;
            justify-content: center;
            padding: 16px;
        }
        .map-modal.active { display: flex; }
        
        .map-modal .modal-box {
            background: #ffffff;
            border-radius: 16px;
            max-width: 640px;
            width: 100%;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0,0,0,0.4);
            animation: slideUp 0.3s ease;
        }
        
        @keyframes slideUp {
            from { transform: translateY(20px) scale(0.95); opacity: 0; }
            to { transform: translateY(0) scale(1); opacity: 1; }
        }
        
        .map-modal .modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 14px 20px;
            border-bottom: 1px solid #e8edf2;
        }
        
        .map-modal .modal-header h3 {
            color: #0f172a;
            font-weight: 700;
            font-size: 16px;
        }
        
        .map-modal .modal-header button {
            background: none;
            border: none;
            color: #94a3b8;
            font-size: 22px;
            cursor: pointer;
            transition: color 0.25s ease;
            padding: 4px 8px;
            border-radius: 6px;
        }
        .map-modal .modal-header button:hover { 
            color: #475569;
            background: #f1f5f9;
        }
        
        .map-modal .modal-body {
            padding: 20px;
        }
        
        .map-modal .modal-body .map-container {
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid #e8edf2;
            position: relative;
        }
        
        .map-modal .modal-body .map-container iframe {
            width: 100%;
            height: 280px;
            display: block;
            border: none;
        }
        
        .map-modal .modal-body .address {
            color: #475569;
            font-size: 14px;
            margin-top: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 14px;
            background: #f8fafc;
            border-radius: 10px;
            border: 1px solid #e8edf2;
        }
        
        .map-modal .btn-google-maps {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            background: #4285F4;
            color: white;
            font-weight: 700;
            font-size: 15px;
            padding: 12px 24px;
            border-radius: 12px;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            width: 100%;
            margin-top: 12px;
            box-shadow: 0 4px 12px rgba(66, 133, 244, 0.3);
        }
        .map-modal .btn-google-maps:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(66, 133, 244, 0.4);
        }
        .map-modal .btn-google-maps:active {
            transform: scale(0.97);
        }
        .map-modal .btn-google-maps i {
            font-size: 20px;
        }
        
        .map-modal .modal-footer {
            padding: 0 20px 20px 20px;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        @media (max-width: 480px) {
            .shop-header-card .cover { height: 70px; }
            .shop-header-card .shop-avatar { width: 44px; height: 44px; bottom: -12px; right: 14px; }
            .shop-header-card .shop-avatar i { font-size: 18px; }
            .shop-header-card .body { padding: 16px 12px 12px 12px; }
            .shop-header-card .shop-name { font-size: 16px; }
            .shop-header-card .shop-meta { font-size: 11px; gap: 8px; }
            .shop-header-card .shop-desc { font-size: 13px; }
            .shop-header-card .btn-action { font-size: 12px; padding: 8px 14px; min-width: 80px; }
            .product-card .image { height: 100px; }
            .product-card .product-name { font-size: 12px; }
            .map-modal .modal-body .map-container iframe { height: 180px; }
            .map-modal .btn-google-maps { font-size: 13px; padding: 10px 16px; }
        }
    </style>
</head>
<body>

<div class="main-content">
    <div class="max-w-7xl mx-auto px-3 md:px-6">
        
        <!-- ===== Header Shop ===== -->
        <div class="shop-header-card">
            <div class="cover">
                <div class="shop-avatar">
                    <i class="fas fa-store"></i>
                </div>
            </div>
            
            <div class="body">
                <div class="flex flex-col md:flex-row md:items-start md:justify-between">
                    <div>
                        <h1 class="shop-name"><?= clean($shop['shop_name']) ?></h1>
                        <div class="shop-meta">
                            <span><i class="fas fa-map-marker-alt text-red-400"></i> <?= clean($shop['city_name'] ?? '') ?>، <?= clean($shop['country_name'] ?? '') ?></span>
                            <?php if ($shop['working_hours']): ?>
                            <span><i class="fas fa-clock text-blue-400"></i> <?= clean($shop['working_hours']) ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($shop['description']): ?>
                        <div class="shop-desc"><?= nl2br(clean($shop['description'])) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- ===== أزرار مثل البروفايل ===== -->
                <div class="shop-actions">
                    <?php if ($shop['whatsapp'] || $shop['user_whatsapp']): ?>
                    <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $shop['whatsapp'] ?: $shop['user_whatsapp']) ?>" target="_blank" class="btn-action btn-whatsapp">
                        <i data-lucide="message-circle" style="width:18px;height:18px;"></i> واتساب
                    </a>
                    <?php endif; ?>
                    
                    <?php if ($shop['phone'] || $shop['user_phone']): ?>
                    <a href="tel:<?= $shop['phone'] ?: $shop['user_phone'] ?>" class="btn-action btn-phone">
                        <i data-lucide="phone" style="width:18px;height:18px;"></i> اتصال
                    </a>
                    <?php endif; ?>
                    
                    <?php if ($shop['address'] || ($shop['latitude'] && $shop['longitude'])): ?>
                    <button onclick="showMap()" class="btn-action btn-map">
                        <i data-lucide="map" style="width:18px;height:18px;"></i> الموقع
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- ===== منتجات المحل ===== -->
        <h2 class="section-title">📦 المنتجات المتوفرة <span>(<?= count($products) ?>)</span></h2>
        
        <?php if (empty($products)): ?>
        <div class="empty-state">
            <i data-lucide="box" style="width:48px;height:48px;margin:0 auto 12px;display:block;color:rgba(255,255,255,0.15);"></i>
            <p>لا توجد منتجات مضافة حالياً</p>
        </div>
        <?php else: ?>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <?php foreach ($products as $product): 
                $image_path = !empty($product['main_image']) && file_exists(__DIR__ . '/' . $product['main_image']) 
                             ? SITE_URL . '/' . $product['main_image'] 
                             : null;
            ?>
            <div class="product-card">
                <div class="image">
                    <?php if ($image_path): ?>
                    <img src="<?= $image_path ?>" alt="<?= clean($product['name']) ?>" loading="lazy">
                    <?php else: ?>
                    <div class="no-image"><i class="fas fa-box"></i></div>
                    <?php endif; ?>
                    
                    <?php if ($product['category']): ?>
                    <span class="badge-category"><?= clean($product['category']) ?></span>
                    <?php endif; ?>
                    
                    <?php if (!$product['is_available']): ?>
                    <div class="badge-unavailable">❌ غير متوفر</div>
                    <?php endif; ?>
                </div>
                
                <div class="body">
                    <h4 class="product-name"><?= clean($product['name']) ?></h4>
                    <?php if ($product['description']): ?>
                    <p class="product-desc"><?= clean($product['description']) ?></p>
                    <?php endif; ?>
                    
                    <div class="product-footer">
                        <?php if ($product['price']): ?>
                        <span class="product-price"><?= number_format($product['price']) ?> <span class="currency"><?= clean($shop['currency_ar'] ?? '') ?></span></span>
                        <?php else: ?>
                        <span class="text-gray-400 text-xs">السعر عند الاستفسار</span>
                        <?php endif; ?>
                        
                        <?php if ($shop['whatsapp'] || $shop['user_whatsapp']): ?>
                        <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $shop['whatsapp'] ?: $shop['user_whatsapp']) ?>?text=<?= urlencode('مرحباً، أريد الاستفسار عن: ' . $product['name']) ?>" target="_blank" class="btn-inquire">
                            <i data-lucide="message-circle" style="width:12px;height:12px;"></i> استفسار
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
    </div>
</div>

<!-- ===== Map Modal مع زر خرائط جوجل ===== -->
<div id="mapModal" class="map-modal" onclick="if(event.target === this) closeMap()">
    <div class="modal-box">
        <div class="modal-header">
            <h3>📍 موقع المحل</h3>
            <button onclick="closeMap()">&times;</button>
        </div>
        <div class="modal-body">
            <div class="map-container">
                <?php if ($shop['latitude'] && $shop['longitude']): ?>
                    <iframe src="https://maps.google.com/maps?q=<?= $shop['latitude'] ?>,<?= $shop['longitude'] ?>&z=15&output=embed" allowfullscreen loading="lazy"></iframe>
                <?php elseif ($shop['address']): ?>
                    <iframe src="https://maps.google.com/maps?q=<?= urlencode($shop['address']) ?>&z=15&output=embed" allowfullscreen loading="lazy"></iframe>
                <?php endif; ?>
            </div>
            <div class="address">
                <i class="fas fa-map-pin text-red-400"></i>
                <span><?= clean($shop['address']) ?></span>
            </div>
        </div>
        <div class="modal-footer">
            <?php 
            $google_maps_url = '';
            if ($shop['latitude'] && $shop['longitude']) {
                $google_maps_url = 'https://www.google.com/maps/dir/?api=1&destination=' . $shop['latitude'] . ',' . $shop['longitude'];
            } elseif ($shop['address']) {
                $google_maps_url = 'https://www.google.com/maps/dir/?api=1&destination=' . urlencode($shop['address']);
            }
            ?>
            <?php if ($google_maps_url): ?>
            <a href="<?= $google_maps_url ?>" target="_blank" class="btn-google-maps">
                <i class="fas fa-map-signs"></i>
                فتح في خرائط جوجل
                <i class="fas fa-external-link-alt" style="font-size:14px;"></i>
            </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    lucide.createIcons();

    function showMap() {
        document.getElementById('mapModal').classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeMap() {
        document.getElementById('mapModal').classList.remove('active');
        document.body.style.overflow = '';
    }

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeMap();
    });
</script>

<?php include 'includes/footer.php'; ?>
</body>
</html>