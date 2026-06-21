<?php
require_once 'config.php';

if (!isLoggedIn() || getUserType() !== 'shop') {
    redirect('login.php');
}

// جلب بيانات المحل
$stmt = $pdo->prepare("SELECT * FROM shops WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$shop = $stmt->fetch();

// إذا لم يكن للمستخدم محل، أنشئ له محل تلقائياً
if (!$shop) {
    $shop_name = 'محل ' . ($_SESSION['user_name'] ?? 'المستخدم');
    $stmt = $pdo->prepare("INSERT INTO shops (user_id, shop_name, status) VALUES (?, ?, 'active')");
    $stmt->execute([$_SESSION['user_id'], $shop_name]);
    
    // جلب بيانات المحل الجديد
    $stmt = $pdo->prepare("SELECT * FROM shops WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $shop = $stmt->fetch();
}

$product_categories = getProductCategories();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = clean($_POST['name'] ?? '');
    $description = clean($_POST['description'] ?? '');
    $price = !empty($_POST['price']) ? (float)$_POST['price'] : null;
    $category = clean($_POST['category'] ?? '');
    $unit = clean($_POST['unit'] ?? '');
    $is_available = isset($_POST['is_available']) ? 1 : 0;
    
    if (empty($name)) {
        $error = 'يرجى إدخال اسم المنتج';
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO products (shop_id, name, description, price, category, unit, is_available) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        if ($stmt->execute([$shop['id'], $name, $description, $price, $category, $unit, $is_available])) {
            $product_id = $pdo->lastInsertId();
            
            // رفع الصور
            if (isset($_FILES['images'])) {
                foreach ($_FILES['images']['tmp_name'] as $key => $tmp_name) {
                    if ($_FILES['images']['error'][$key] === UPLOAD_ERR_OK) {
                        $file = [
                            'name' => $_FILES['images']['name'][$key],
                            'type' => $_FILES['images']['type'][$key],
                            'tmp_name' => $tmp_name,
                            'error' => $_FILES['images']['error'][$key],
                            'size' => $_FILES['images']['size'][$key]
                        ];
                        $path = uploadImage($file, 'products');
                        if ($path) {
                            $is_main = ($key === 0) ? 1 : 0;
                            $pdo->prepare("INSERT INTO product_images (product_id, image_path, is_main, sort_order) VALUES (?, ?, ?, ?)")
                                ->execute([$product_id, $path, $is_main, $key]);
                        }
                    }
                }
            }
            
            $success = 'تم إضافة المنتج بنجاح!';
            $_POST = [];
        } else {
            $error = 'حدث خطأ أثناء إضافة المنتج';
        }
    }
}

$page_title = 'إضافة منتج جديد';
include 'includes/header.php';
?>

<!-- باقي الكود كما هو (HTML) -->
<div class="py-12">
    <div class="max-w-3xl mx-auto px-4">
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="bg-green-600 text-white p-6">
                <h1 class="text-2xl font-bold"><i class="fas fa-plus-circle ml-2"></i> إضافة منتج جديد</h1>
                <p class="text-green-100 mt-1">أضف بضاعتك إلى متجر <?= clean($shop['shop_name']) ?></p>
            </div>
            
            <div class="p-8">
                <?php if ($error): ?>
                <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl mb-6 flex items-center gap-2">
                    <i class="fas fa-exclamation-circle"></i><span><?= $error ?></span>
                </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-xl mb-6 flex items-center gap-2">
                    <i class="fas fa-check-circle"></i><span><?= $success ?></span>
                </div>
                <?php endif; ?>
                
                <form method="POST" enctype="multipart/form-data">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">اسم المنتج <span class="text-red-500">*</span></label>
                            <input type="text" name="name" required value="<?= clean($_POST['name'] ?? '') ?>"
                                class="w-full border border-gray-300 rounded-xl px-4 py-3 focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-all" placeholder="مثال: أسمنت بورتلاندي 50 كجم">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">التصنيف</label>
                            <select name="category" class="w-full border border-gray-300 rounded-xl px-4 py-3 bg-white focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-all">
                                <option value="">اختر التصنيف</option>
                                <?php foreach ($product_categories as $cat => $icon): ?>
                                <option value="<?= $cat ?>" <?= (($_POST['category'] ?? '') === $cat) ? 'selected' : '' ?>><?= $icon ?> <?= $cat ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">وصف المنتج</label>
                        <textarea name="description" rows="3"
                            class="w-full border border-gray-300 rounded-xl px-4 py-3 focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-all resize-none" 
                            placeholder="وصف تفصيلي للمنتج، المواصفات، العلامة التجارية..."><?= clean($_POST['description'] ?? '') ?></textarea>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">السعر</label>
                            <input type="number" name="price" value="<?= $_POST['price'] ?? '' ?>" min="0" step="0.01"
                                class="w-full border border-gray-300 rounded-xl px-4 py-3 focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-all" placeholder="اتركه فارغاً إذا السعر عند الاستفسار" dir="ltr">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">الوحدة</label>
                            <input type="text" name="unit" value="<?= clean($_POST['unit'] ?? '') ?>"
                                class="w-full border border-gray-300 rounded-xl px-4 py-3 focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-all" placeholder="مثال: كيس، متر، طن، قطعة">
                        </div>
                    </div>
                    
                    <div class="mb-6">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" name="is_available" value="1" checked class="w-5 h-5 text-green-600 rounded focus:ring-green-500">
                            <span class="text-sm font-medium text-gray-700">المنتج متوفر حالياً</span>
                        </label>
                    </div>
                    
                    <!-- الصور -->
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-1">صور المنتج</label>
                        <div class="border-2 border-dashed border-gray-300 rounded-xl p-6 text-center hover:border-green-400 transition-colors cursor-pointer" onclick="document.getElementById('product-images').click()">
                            <i class="fas fa-images text-3xl text-gray-300 mb-2"></i>
                            <p class="text-sm text-gray-500">اضغط لرفع صور المنتج (يمكنك رفع أكثر من صورة)</p>
                            <p class="text-xs text-gray-400 mt-1">الصورة الأولى ستكون الصورة الرئيسية</p>
                            <input type="file" name="images[]" id="product-images" multiple accept="image/*" class="hidden" onchange="previewImages(this, 'product-images-preview')">
                        </div>
                        <div id="product-images-preview" class="grid grid-cols-3 md:grid-cols-5 gap-3 mt-3"></div>
                    </div>
                    
                    <button type="submit" class="w-full bg-green-600 hover:bg-green-700 text-white font-bold py-3 rounded-xl transition-all hover:shadow-lg text-lg">
                        <i class="fas fa-plus ml-2"></i> إضافة المنتج
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function previewImages(input, containerId) {
    const container = document.getElementById(containerId);
    container.innerHTML = '';
    
    if (input.files) {
        Array.from(input.files).forEach(file => {
            const reader = new FileReader();
            reader.onload = function(e) {
                const div = document.createElement('div');
                div.className = 'relative rounded-xl overflow-hidden aspect-square bg-gray-100 border border-gray-200';
                div.innerHTML = `
                    <img src="${e.target.result}" class="w-full h-full object-cover">
                    <div class="absolute bottom-0 left-0 right-0 bg-black/50 text-white text-xs text-center py-1 truncate px-2">
                        ${file.name}
                    </div>
                `;
                container.appendChild(div);
            };
            reader.readAsDataURL(file);
        });
    }
}
</script>

<?php include 'includes/footer.php'; ?>