<?php
require_once 'config.php';

if (!isLoggedIn() || getUserType() !== 'shop') {
    redirect('login.php');
}

// جلب بيانات المحل
 $stmt = $pdo->prepare("SELECT * FROM shops WHERE user_id = ?");
 $stmt->execute([$_SESSION['user_id']]);
 $shop = $stmt->fetch();

if (!$shop) redirect('profile.php');

// معالجة الحذف
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $product_id = (int)$_GET['id'];
    // التأكد إن المنتج يتبع لهذا المحل فقط
    $stmt = $pdo->prepare("DELETE FROM products WHERE id = ? AND shop_id = ?");
    $stmt->execute([$product_id, $shop['id']]);
    redirect('my_products.php');
}

// معالجة تغيير حالة التوفر
if (isset($_GET['action']) && in_array($_GET['action'], ['available', 'unavailable']) && isset($_GET['id'])) {
    $product_id = (int)$_GET['id'];
    $is_available = ($_GET['action'] === 'available') ? 1 : 0;
    $stmt = $pdo->prepare("UPDATE products SET is_available = ? WHERE id = ? AND shop_id = ?");
    $stmt->execute([$is_available, $product_id, $shop['id']]);
    redirect('my_products.php');
}

// جلب منتجات المحل
 $stmt = $pdo->prepare("
    SELECT p.*, 
           (SELECT pi.image_path FROM product_images pi WHERE pi.product_id = p.id AND pi.is_main = 1 LIMIT 1) as main_image
    FROM products p
    WHERE p.shop_id = ?
    ORDER BY p.is_available DESC, p.created_at DESC
");
 $stmt->execute([$shop['id']]);
 $products = $stmt->fetchAll();

 $page_title = 'إدارة منتجاتي';
include 'includes/header.php';
?>

<div class="py-8">
    <div class="max-w-6xl mx-auto px-4">
        
        <div class="flex items-center justify-between mb-8">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">إدارة منتجاتي</h1>
                <p class="text-gray-500 text-sm mt-1">إدارة وتعديل منتجات محل: <?= clean($shop['shop_name']) ?></p>
            </div>
            <a href="<?= SITE_URL ?>/add_product.php" class="bg-green-600 hover:bg-green-700 text-white font-bold px-5 py-2.5 rounded-xl transition-all hover:shadow-lg flex items-center gap-2">
                <i class="fas fa-plus-circle"></i> إضافة منتج جديد
            </a>
        </div>

        <?php if (empty($products)): ?>
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-12 text-center">
            <i class="fas fa-boxes text-5xl text-gray-200 mb-4"></i>
            <p class="text-gray-400 text-lg mb-4">لم تضف أي منتجات بعد</p>
            <a href="<?= SITE_URL ?>/add_product.php" class="text-green-600 font-bold hover:text-green-700">أضف أول منتج الآن</a>
        </div>
        <?php else: ?>
        
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 border-b border-gray-100">
                        <tr>
                            <th class="text-right px-6 py-4 font-medium text-gray-500">المنتج</th>
                            <th class="text-right px-6 py-4 font-medium text-gray-500">التصنيف</th>
                            <th class="text-right px-6 py-4 font-medium text-gray-500">السعر</th>
                            <th class="text-right px-6 py-4 font-medium text-gray-500">الحالة</th>
                            <th class="text-right px-6 py-4 font-medium text-gray-500">إجراءات</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <?php foreach ($products as $product): ?>
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-12 h-12 bg-gray-100 rounded-lg overflow-hidden flex-shrink-0">
                                        <?php if ($product['main_image'] && file_exists($product['main_image'])): ?>
                                        <img src="<?= SITE_URL ?>/<?= $product['main_image'] ?>" alt="<?= clean($product['name']) ?>" class="w-full h-full object-cover">
                                        <?php else: ?>
                                        <div class="w-full h-full flex items-center justify-center"><i class="fas fa-box text-gray-300"></i></div>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <p class="font-bold text-gray-800"><?= clean($product['name']) ?></p>
                                        <p class="text-xs text-gray-400 mt-0.5"><?= clean($product['unit'] ?? '') ?></p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-gray-600"><?= clean($product['category'] ?? '-') ?></td>
                            <td class="px-6 py-4 font-bold text-primary-600">
                                <?= $product['price'] ? number_format($product['price']) : '<span class="text-gray-400 font-normal">عند الاستفسار</span>' ?>
                            </td>
                            <td class="px-6 py-4">
                                <?php if ($product['is_available']): ?>
                                <span class="bg-green-100 text-green-700 px-3 py-1 rounded-full text-xs font-bold">متوفر</span>
                                <?php else: ?>
                                <span class="bg-red-100 text-red-700 px-3 py-1 rounded-full text-xs font-bold">غير متوفر</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex gap-2">
                                    <?php if ($product['is_available']): ?>
                                    <a href="my_products.php?action=unavailable&id=<?= $product['id'] ?>" 
                                       class="bg-amber-100 text-amber-700 px-3 py-1.5 rounded-lg text-xs font-medium hover:bg-amber-200 transition-colors"
                                       onclick="return confirm('تعيين كغير متوفر؟')">
                                        <i class="fas fa-eye-slash ml-1"></i> إخفاء
                                    </a>
                                    <?php else: ?>
                                    <a href="my_products.php?action=available&id=<?= $product['id'] ?>" 
                                       class="bg-green-100 text-green-700 px-3 py-1.5 rounded-lg text-xs font-medium hover:bg-green-200 transition-colors"
                                       onclick="return confirm('تعيين كمتوفر؟')">
                                        <i class="fas fa-eye ml-1"></i> إظهار
                                    </a>
                                    <?php endif; ?>
                                    
                                    <a href="my_products.php?action=delete&id=<?= $product['id'] ?>" 
                                       class="bg-red-100 text-red-700 px-3 py-1.5 rounded-lg text-xs font-medium hover:bg-red-200 transition-colors"
                                       onclick="return confirm('هل أنت متأكد من حذف هذا المنتج نهائياً؟')">
                                        <i class="fas fa-trash ml-1"></i> حذف
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>