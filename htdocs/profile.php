<?php
/**
 * ============================================================
 * PROFILE.PHP - الملف الشخصي للمستخدم
 * ============================================================
 */

require_once 'config.php';

// دالة مساعدة لتحويل الوقت
if (!function_exists('timeAgo')) {
    function timeAgo($timestamp) {
        if (!is_numeric($timestamp)) {
            $timestamp = strtotime($timestamp);
        }
        $diff = time() - $timestamp;
        if ($diff < 60) return 'الآن';
        if ($diff < 3600) return round($diff / 60) . ' دقيقة';
        if ($diff < 86400) return round($diff / 3600) . ' ساعة';
        if ($diff < 2592000) return round($diff / 86400) . ' يوم';
        return date('d/m/Y', $timestamp);
    }
}

// ========== قراءة ID المستخدم المطلوب من الرابط ==========
$requested_id = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : 0;

// إذا كان هناك ID في الرابط، اعرض ذلك المستخدم، وإلا اعرض المستخدم الحالي
if ($requested_id > 0) {
    $view_user_id = $requested_id;
} else {
    $view_user_id = $_SESSION['user_id'] ?? 0;
}

// التحقق من صحة ID المستخدم
if ($view_user_id == 0) {
    redirect('');
}

// ========== جلب بيانات المستخدم المطلوب عرض ملفه ==========
$profile_user = getUser($view_user_id);

// إذا كان المستخدم غير موجود، ارجع للرئيسية
if (!$profile_user) {
    redirect('');
}

// التحقق ما إذا كان هذا الملف الشخصي مملوكاً للمستخدم الحالي
$is_own_profile = (isset($_SESSION['user_id']) && $view_user_id == $_SESSION['user_id']);
$user_type = $profile_user['user_type'];
$verification_status = $profile_user['verification_status'] ?? 'pending';

$shop_data = null;
$contractor_specs = [];
$projects = [];
$bids = [];
$products = [];
$documents = [];
$approved_docs_count = 0;

// --- جلب البيانات حسب نوع المستخدم ---
if ($user_type === 'shop') {
    $stmt = $pdo->prepare("SELECT * FROM shops WHERE user_id = ?");
    $stmt->execute([$view_user_id]);
    $shop_data = $stmt->fetch();
    
    if ($shop_data) {
        $stmt = $pdo->prepare("
            SELECT p.*, 
                   (SELECT pi.image_path FROM product_images pi WHERE pi.product_id = p.id AND pi.is_main = 1 LIMIT 1) as main_image
            FROM products p
            WHERE p.shop_id = ?
            ORDER BY p.is_available DESC, p.created_at DESC
        ");
        $stmt->execute([$shop_data['id']]);
        $products = $stmt->fetchAll();
    }
}

if ($user_type === 'contractor') {
    // جلب التخصصات
    $stmt = $pdo->prepare("SELECT spec FROM contractor_specs WHERE user_id = ?");
    $stmt->execute([$view_user_id]);
    $contractor_specs = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // جلب العطاءات التي قدمها هذا المقاول
    $stmt = $pdo->prepare("
        SELECT b.*, p.title as project_title, p.status as project_status
        FROM bids b
        JOIN projects p ON b.project_id = p.id
        WHERE b.contractor_id = ?
        ORDER BY b.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$view_user_id]);
    $bids = $stmt->fetchAll();
    
    // جلب المستندات (للمالك أو الأدمن فقط)
    $is_admin = (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin');
    if ($is_own_profile || $is_admin) {
        $stmt = $pdo->prepare("SELECT * FROM contractor_documents WHERE contractor_id = ? ORDER BY uploaded_at DESC");
        $stmt->execute([$view_user_id]);
        $documents = $stmt->fetchAll();
    }
    
    // جلب عدد المستندات المعتمدة
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM contractor_documents WHERE contractor_id = ? AND status = 'approved'");
    $stmt->execute([$view_user_id]);
    $approved_docs_count = $stmt->fetchColumn();
}

if ($user_type === 'employer') {
    // جلب مشاريع صاحب العمل هذا
    $stmt = $pdo->prepare("
        SELECT p.*, c.name_ar as country_name, ci.name_ar as city_name
        FROM projects p
        LEFT JOIN countries c ON p.country_id = c.id
        LEFT JOIN cities ci ON p.city_id = ci.id
        WHERE p.employer_id = ?
        ORDER BY p.created_at DESC
    ");
    $stmt->execute([$view_user_id]);
    $projects = $stmt->fetchAll();
}

// جلب التقييمات الخاصة بهذا المستخدم
$stmt = $pdo->prepare("
    SELECT r.*, u.name as reviewer_name 
    FROM reviews r
    JOIN users u ON r.reviewer_id = u.id
    WHERE r.reviewed_id = ?
    ORDER BY r.created_at DESC
    LIMIT 10
");
$stmt->execute([$view_user_id]);
$reviews = $stmt->fetchAll();

// --- المتغيرات المساعدة للواجهة ---
$type_labels = [
    'employer' => 'صاحب عمل',
    'contractor' => 'مقاول',
    'shop' => 'محل مواد بناء'
];

$type_icons = [
    'employer' => 'fa-building',
    'contractor' => 'fa-hard-hat',
    'shop' => 'fa-store'
];

$type_colors = [
    'employer' => 'primary',
    'contractor' => 'accent',
    'shop' => 'emerald'
];

$color = $type_colors[$user_type];

$contractor_classes = [
    'pending' => ['name' => 'قيد التصنيف', 'color' => 'gray', 'icon' => 'fa-hourglass-half', 'desc' => ''],
    'A' => ['name' => 'تصنيف أ (متقدم)', 'color' => 'green', 'icon' => 'fa-crown', 'desc' => 'أعلى تصنيف - شركات كبرى'],
    'B' => ['name' => 'تصنيف ب (جيد جداً)', 'color' => 'blue', 'icon' => 'fa-medal', 'desc' => 'شركات متميزة'],
    'C' => ['name' => 'تصنيف ج (متوسط)', 'color' => 'amber', 'icon' => 'fa-chart-line', 'desc' => 'مقاولون متوسطو الخبرة'],
    'D' => ['name' => 'تصنيف د (مبتدئ)', 'color' => 'orange', 'icon' => 'fa-seedling', 'desc' => 'مبتدئ أو تحت التصنيف']
];

$current_class = $profile_user['contractor_class'] ?? 'pending';
$class_info = $contractor_classes[$current_class] ?? $contractor_classes['pending'];

$page_title = clean($profile_user['name']);
include 'includes/header.php';
?>

<style>
.status-badge {
    display: inline-block;
    padding: 2px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 700;
}
</style>

<div class="py-8">
    <div class="max-w-6xl mx-auto px-4">
        
        <!-- ========== PROFILE HEADER ========== -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden mb-8">
            <div class="h-40 md:h-52 bg-gradient-to-l from-slate-700 via-slate-800 to-slate-900 relative">
                <div class="absolute inset-0 opacity-10">
                    <div class="absolute top-5 right-10 w-40 h-40 border border-white rounded-full"></div>
                    <div class="absolute bottom-5 left-20 w-60 h-60 border border-white rounded-full"></div>
                </div>
                
                <?php if ($is_own_profile): ?>
                <a href="edit_profile.php" class="absolute top-4 left-4 bg-white/20 backdrop-blur-sm hover:bg-white/30 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                    <i class="fas fa-pen ml-1"></i> تعديل الملف
                </a>
                <?php endif; ?>
            </div>
            
            <div class="px-6 md:px-10 pb-8 relative">
                <!-- صورة البروفايل -->
                <div class="absolute -top-14 right-6 md:right-10 w-28 h-28 bg-slate-200 rounded-2xl shadow-lg flex items-center justify-center border-4 border-white overflow-hidden">
                    <?php if ($profile_user['avatar'] && $profile_user['avatar'] !== 'default-avatar.png'): ?>
                    <img src="<?= SITE_URL ?>/<?= $profile_user['avatar'] ?>" alt="<?= clean($profile_user['name']) ?>" class="w-full h-full object-cover">
                    <?php else: ?>
                    <i class="fas <?= $type_icons[$user_type] ?> text-<?= $color ?>-500 text-4xl"></i>
                    <?php endif; ?>
                </div>
                
                <div class="pt-16 md:pt-4 md:pr-36 flex flex-col md:flex-row md:items-end md:justify-between">
                    <div>
                        <div class="flex items-center gap-3 flex-wrap">
                            <h1 class="text-2xl md:text-3xl font-bold text-gray-800"><?= clean($profile_user['name']) ?></h1>
                            <span class="bg-<?= $color ?>-100 text-<?= $color ?>-700 px-3 py-1 rounded-full text-xs font-bold flex items-center gap-1">
                                <i class="fas <?= $type_icons[$user_type] ?>"></i> <?= $type_labels[$user_type] ?>
                            </span>
                            
                            <?php if ($user_type === 'contractor'): ?>
                                <span class="bg-<?= $class_info['color'] ?>-100 text-<?= $class_info['color'] ?>-700 px-3 py-1 rounded-full text-xs font-bold flex items-center gap-1">
                                    <i class="fas <?= $class_info['icon'] ?>"></i> <?= $class_info['name'] ?>
                                </span>
                            <?php endif; ?>
                            
                            <?php if ($verification_status === 'approved'): ?>
                            <span class="bg-green-100 text-green-700 px-3 py-1 rounded-full text-xs font-bold flex items-center gap-1" title="حساب موثق">
                                <i class="fas fa-check-circle"></i> موثق
                            </span>
                            <?php elseif ($verification_status === 'pending' && $is_own_profile): ?>
                            <span class="bg-yellow-100 text-yellow-700 px-3 py-1 rounded-full text-xs font-bold flex items-center gap-1">
                                <i class="fas fa-clock"></i> قيد التوثيق
                            </span>
                            <?php endif; ?>
                            
                            <?php if ($profile_user['is_featured']): ?>
                            <span class="bg-amber-500 text-white px-3 py-1 rounded-full text-xs font-bold flex items-center gap-1" title="حساب مميز">
                                <i class="fas fa-crown"></i> مميز
                            </span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="flex flex-wrap items-center gap-4 mt-3 text-sm text-gray-500">
                            <?php if ($profile_user['country_name']): ?>
                            <span class="flex items-center gap-1">
                                <i class="fas fa-map-marker-alt text-red-400"></i> 
                                <?= clean($profile_user['city_name'] ?? '') ?>، <?= clean($profile_user['country_name']) ?>
                            </span>
                            <?php endif; ?>
                            <span class="flex items-center gap-1">
                                <i class="fas fa-calendar-alt text-gray-400"></i> 
                                انضم منذ <?= timeAgo($profile_user['created_at']) ?>
                            </span>
                            <?php if ($profile_user['rating'] > 0): ?>
                            <span class="flex items-center gap-1">
                                <i class="fas fa-star text-amber-400"></i> 
                                <?= $profile_user['rating'] ?> (<?= $profile_user['reviews_count'] ?> تقييم)
                            </span>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($profile_user['bio']): ?>
                        <p class="text-gray-600 mt-3 max-w-2xl leading-relaxed"><?= nl2br(clean($profile_user['bio'])) ?></p>
                        <?php endif; ?>
                    </div>
                    
                    <!-- أزرار التواصل -->
                    <?php if (!$is_own_profile && isset($_SESSION['user_id'])): ?>
                    <div class="flex gap-3 mt-4 md:mt-0 flex-shrink-0">
                        <?php if ($profile_user['whatsapp']): ?>
                        <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $profile_user['whatsapp']) ?>" target="_blank"
                           class="bg-emerald-500 hover:bg-emerald-600 text-white px-5 py-2.5 rounded-xl transition-all font-medium flex items-center gap-2">
                            <i class="fab fa-whatsapp text-lg"></i> واتساب
                        </a>
                        <?php endif; ?>
                        
                        <?php if ($profile_user['phone']): ?>
                        <a href="tel:<?= $profile_user['phone'] ?>"
                           class="bg-<?= $color ?>-600 hover:bg-<?= $color ?>-700 text-white px-5 py-2.5 rounded-xl transition-all font-medium flex items-center gap-2">
                            <i class="fas fa-phone"></i> اتصال
                        </a>
                        <?php endif; ?>
                        
                        <a href="<?= SITE_URL ?>/chat.php?contractor_id=<?= $view_user_id ?>&project_id=0" 
                           class="bg-blue-500 hover:bg-blue-600 text-white px-5 py-2.5 rounded-xl transition-all font-medium flex items-center gap-2">
                            <i class="fas fa-comments"></i> محادثة
                        </a>
                    </div>
                    <?php elseif (!$is_own_profile && !isset($_SESSION['user_id'])): ?>
                    <div class="mt-4 md:mt-0">
                        <a href="<?= SITE_URL ?>/login.php" class="bg-gray-500 hover:bg-gray-600 text-white px-5 py-2.5 rounded-xl transition-all font-medium flex items-center gap-2">
                            <i class="fas fa-sign-in-alt"></i> سجل دخول للتواصل
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- ========== باقي المحتوى ========== -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            
            <!-- Sidebar Info -->
            <div class="lg:col-span-1 space-y-6">
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
                    <h3 class="font-bold text-gray-800 mb-4 flex items-center gap-2">
                        <i class="fas fa-address-card text-slate-500"></i> معلومات التواصل
                    </h3>
                    <ul class="space-y-3">
                        <?php if ($profile_user['phone']): ?>
                        <li class="flex items-center gap-3 text-sm">
                            <div class="w-9 h-9 bg-blue-50 rounded-lg flex items-center justify-center flex-shrink-0">
                                <i class="fas fa-phone text-blue-500"></i>
                            </div>
                            <?php if (isset($_SESSION['user_id'])): ?>
                                <span class="text-gray-600" dir="ltr"><?= clean($profile_user['phone']) ?></span>
                            <?php else: ?>
                                <span class="text-gray-400">(سجل دخول للمشاهدة)</span>
                            <?php endif; ?>
                        </li>
                        <?php endif; ?>
                        
                        <?php if ($profile_user['whatsapp']): ?>
                        <li class="flex items-center gap-3 text-sm">
                            <div class="w-9 h-9 bg-emerald-50 rounded-lg flex items-center justify-center flex-shrink-0">
                                <i class="fab fa-whatsapp text-emerald-500"></i>
                            </div>
                            <?php if (isset($_SESSION['user_id'])): ?>
                                <span class="text-gray-600" dir="ltr"><?= clean($profile_user['whatsapp']) ?></span>
                            <?php else: ?>
                                <span class="text-gray-400">(سجل دخول للمشاهدة)</span>
                            <?php endif; ?>
                        </li>
                        <?php endif; ?>
                        
                        <li class="flex items-center gap-3 text-sm">
                            <div class="w-9 h-9 bg-red-50 rounded-lg flex items-center justify-center flex-shrink-0">
                                <i class="fas fa-envelope text-red-400"></i>
                            </div>
                            <?php if (isset($_SESSION['user_id'])): ?>
                                <span class="text-gray-600" dir="ltr"><?= clean($profile_user['email']) ?></span>
                            <?php else: ?>
                                <span class="text-gray-400">(سجل دخول للمشاهدة)</span>
                            <?php endif; ?>
                        </li>
                        
                        <?php if ($profile_user['country_name']): ?>
                        <li class="flex items-center gap-3 text-sm">
                            <div class="w-9 h-9 bg-amber-50 rounded-lg flex items-center justify-center flex-shrink-0">
                                <i class="fas fa-map-marker-alt text-amber-500"></i>
                            </div>
                            <span class="text-gray-600"><?= clean($profile_user['city_name'] ?? '') ?>، <?= clean($profile_user['country_name']) ?></span>
                        </li>
                        <?php endif; ?>
                    </ul>
                </div>
                
                <?php if ($user_type === 'contractor'): ?>
                <div class="bg-white rounded-2xl border-t-4 border-accent-500 shadow-sm p-6">
                    <h3 class="font-bold text-gray-800 mb-4 flex items-center gap-2">
                        <i class="fas fa-tools text-accent-500"></i> التخصصات
                    </h3>
                    <div class="flex flex-wrap gap-2">
                        <?php if (empty($contractor_specs)): ?>
                        <p class="text-gray-400 text-sm">لم يحدد تخصصات بعد</p>
                        <?php else: ?>
                            <?php foreach ($contractor_specs as $spec): ?>
                            <span class="bg-accent-50 text-accent-700 border border-accent-200 px-3 py-1.5 rounded-lg text-xs font-medium">
                                <?= clean($spec) ?>
                            </span>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
                    <h3 class="font-bold text-gray-800 mb-4 flex items-center gap-2">
                        <i class="fas fa-file-alt text-primary-500"></i> المستندات والأوراق
                    </h3>
                    
                    <?php if ($verification_status === 'approved'): ?>
                    <div class="bg-green-50 border border-green-200 text-green-700 px-3 py-2 rounded-lg mb-3 text-sm">
                        <i class="fas fa-check-circle"></i> حساب موثق
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($is_own_profile || (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin')): ?>
                        <?php if (!empty($documents)): ?>
                        <div class="space-y-2 mb-4">
                            <?php foreach ($documents as $doc): ?>
                            <div class="flex items-center justify-between bg-gray-50 p-3 rounded-lg">
                                <div>
                                    <div class="font-medium text-gray-800 text-sm"><?= clean($doc['document_name']) ?></div>
                                    <div class="text-xs <?= $doc['status'] === 'pending' ? 'text-yellow-600' : ($doc['status'] === 'approved' ? 'text-green-600' : 'text-red-600') ?>">
                                        <?= $doc['status'] === 'pending' ? '⏳ قيد المراجعة' : ($doc['status'] === 'approved' ? '✅ معتمد' : '❌ مرفوض') ?>
                                    </div>
                                </div>
                                <a href="<?= SITE_URL ?>/<?= $doc['document_path'] ?>" target="_blank" class="text-blue-600 hover:text-blue-700 text-sm">
                                    <i class="fas fa-download"></i> تحميل
                                </a>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php elseif ($is_own_profile): ?>
                        <div class="bg-gray-50 rounded-lg p-4 text-center mb-4">
                            <i class="fas fa-upload text-gray-300 text-3xl mb-2"></i>
                            <p class="text-sm text-gray-500">قم برفع مستنداتك ليتم توثيق حسابك</p>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($is_own_profile && $verification_status !== 'approved'): ?>
                        <button onclick="openDocumentModal()" class="w-full bg-primary-600 hover:bg-primary-700 text-white py-2 rounded-lg text-sm font-medium">
                            <i class="fas fa-upload ml-1"></i> رفع مستند جديد
                        </button>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="text-center py-4 text-gray-400 text-sm">
                            <i class="fas fa-lock mb-2 block"></i>
                            المستندات مخفية للمستخدمين الآخرين
                        </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
                    <h3 class="font-bold text-gray-800 mb-4 flex items-center gap-2">
                        <i class="fas fa-star text-amber-400"></i> التقييم
                    </h3>
                    <div class="text-center py-4">
                        <div class="text-5xl font-black text-gray-800 mb-2"><?= $profile_user['rating'] ?: '-' ?></div>
                        <?php if ($profile_user['rating'] > 0): ?>
                        <div class="flex items-center justify-center gap-1 mb-2">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                            <i class="fas fa-star text-lg <?= $i <= round($profile_user['rating']) ? 'text-amber-400' : 'text-gray-200' ?>"></i>
                            <?php endfor; ?>
                        </div>
                        <p class="text-sm text-gray-400"><?= $profile_user['reviews_count'] ?> تقييم</p>
                        <?php else: ?>
                        <p class="text-sm text-gray-400 mt-2">لا توجد تقييمات بعد</p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- ===== رابط الإعدادات (بدلاً من قسم التنبيهات) ===== -->
                <?php if ($is_own_profile): ?>
                <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-4 hover:shadow-md transition-all">
                    <a href="<?= SITE_URL ?>/settings.php" class="flex items-center justify-between group">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-gray-100 rounded-xl flex items-center justify-center group-hover:bg-blue-50 transition-colors">
                                <i class="fas fa-cog text-gray-500 group-hover:text-blue-500 transition-colors text-lg"></i>
                            </div>
                            <div>
                                <h4 class="font-bold text-gray-800 text-sm">الإعدادات</h4>
                                <p class="text-xs text-gray-400">تحكم في الصوت والتنبيهات</p>
                            </div>
                        </div>
                        <i class="fas fa-chevron-left text-gray-300 group-hover:text-blue-500 transition-colors"></i>
                    </a>
                </div>
                <?php endif; ?>
                
            </div>
            
            <!-- Main Content -->
            <div class="lg:col-span-2 space-y-6">
                
                <?php if ($user_type === 'employer'): ?>
                <div class="bg-white rounded-2xl border-t-4 border-primary-500 shadow-sm overflow-hidden">
                    <div class="p-6 border-b border-gray-100 flex items-center justify-between">
                        <h3 class="font-bold text-gray-800 text-lg flex items-center gap-2">
                            <i class="fas fa-project-diagram text-primary-500"></i> المشاريع المطروحة
                        </h3>
                        <span class="bg-primary-100 text-primary-700 px-3 py-1 rounded-full text-xs font-bold"><?= count($projects) ?> مشروع</span>
                    </div>
                    
                    <?php if (empty($projects)): ?>
                    <div class="p-8 text-center">
                        <i class="fas fa-inbox text-4xl text-gray-200 mb-3"></i>
                        <p class="text-gray-400">لا توجد مشاريع بعد</p>
                    </div>
                    <?php else: ?>
                    <div class="divide-y divide-gray-50">
                        <?php foreach ($projects as $project): ?>
                        <a href="<?= SITE_URL ?>/project_detail.php?id=<?= $project['id'] ?>" class="block p-5 hover:bg-blue-50/30 transition-colors">
                            <div class="flex items-start justify-between">
                                <div class="flex-1">
                                    <h4 class="font-bold text-gray-800 mb-1"><?= clean($project['title']) ?></h4>
                                    <div class="flex flex-wrap items-center gap-3 text-xs text-gray-400">
                                        <span class="bg-primary-50 text-primary-600 px-2 py-0.5 rounded"><?= clean($project['category']) ?></span>
                                        <?php if ($project['city_name']): ?>
                                        <span><i class="fas fa-map-marker-alt ml-1"></i><?= clean($project['city_name']) ?></span>
                                        <?php endif; ?>
                                        <span><i class="fas fa-gavel ml-1"></i><?= $project['bids_count'] ?> عطاء</span>
                                    </div>
                                </div>
                                <span class="status-badge bg-<?= $project['status'] === 'open' ? 'green' : ($project['status'] === 'in_progress' ? 'yellow' : ($project['status'] === 'completed' ? 'blue' : 'red')) ?>-100 text-<?= $project['status'] === 'open' ? 'green' : ($project['status'] === 'in_progress' ? 'yellow' : ($project['status'] === 'completed' ? 'blue' : 'red')) ?>-700">
                                    <?php $sl = ['open'=>'مفتوح','in_progress'=>'قيد التنفيذ','completed'=>'مكتمل','cancelled'=>'ملغي']; echo $sl[$project['status']] ?? $project['status']; ?>
                                </span>
                            </div>
                        </a>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
                <?php if ($user_type === 'contractor'): ?>
                <div class="bg-white rounded-2xl border-t-4 border-accent-500 shadow-sm overflow-hidden">
                    <div class="p-6 border-b border-gray-100 flex items-center justify-between">
                        <h3 class="font-bold text-gray-800 text-lg flex items-center gap-2">
                            <i class="fas fa-gavel text-accent-500"></i> العطاءات المقدمة
                        </h3>
                        <span class="bg-accent-100 text-accent-700 px-3 py-1 rounded-full text-xs font-bold"><?= count($bids) ?> عطاء</span>
                    </div>
                    
                    <?php if (empty($bids)): ?>
                    <div class="p-8 text-center">
                        <i class="fas fa-gavel text-4xl text-gray-200 mb-3"></i>
                        <p class="text-gray-400">لم يقدم عطاءات بعد</p>
                    </div>
                    <?php else: ?>
                    <div class="divide-y divide-gray-50">
                        <?php foreach ($bids as $bid): ?>
                        <a href="<?= SITE_URL ?>/project_detail.php?id=<?= $bid['project_id'] ?>" class="block p-5 hover:bg-orange-50/30 transition-colors">
                            <div class="flex items-start justify-between">
                                <div class="flex-1">
                                    <h4 class="font-bold text-gray-800 mb-1"><?= clean($bid['project_title']) ?></h4>
                                    <p class="text-sm text-gray-500 line-clamp-1 mb-2"><?= clean($bid['proposal']) ?></p>
                                    <div class="flex items-center gap-3 text-xs text-gray-400">
                                        <span class="font-bold text-accent-600 text-sm"><?= number_format($bid['amount']) ?> ريال</span>
                                        <?php if ($bid['duration_days']): ?>
                                        <span><i class="fas fa-clock ml-1"></i><?= $bid['duration_days'] ?> يوم</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <span class="status-badge bg-<?= $bid['status'] === 'accepted' ? 'green' : ($bid['status'] === 'rejected' ? 'red' : 'yellow') ?>-100 text-<?= $bid['status'] === 'accepted' ? 'green' : ($bid['status'] === 'rejected' ? 'red' : 'yellow') ?>-700">
                                    <?php $bsl = ['pending'=>'معلّق','accepted'=>'مقبول','rejected'=>'مرفوض']; echo $bsl[$bid['status']] ?? $bid['status']; ?>
                                </span>
                            </div>
                        </a>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
                <?php if ($user_type === 'shop' && $shop_data): ?>
                <div class="bg-white rounded-2xl border-t-4 border-emerald-500 shadow-sm overflow-hidden">
                    <div class="p-6 border-b border-gray-100 flex items-center justify-between">
                        <h3 class="font-bold text-gray-800 text-lg flex items-center gap-2">
                            <i class="fas fa-boxes text-emerald-500"></i> المنتجات
                        </h3>
                        <div class="flex items-center gap-2">
                            <span class="bg-emerald-100 text-emerald-700 px-3 py-1 rounded-full text-xs font-bold"><?= count($products) ?> منتج</span>
                            <?php if ($is_own_profile): ?>
                            <a href="<?= SITE_URL ?>/add_product.php" class="bg-emerald-600 text-white px-3 py-1 rounded-lg text-xs font-medium hover:bg-emerald-700 transition-colors">
                                <i class="fas fa-plus ml-1"></i> إضافة
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <?php if (empty($products)): ?>
                    <div class="p-8 text-center">
                        <i class="fas fa-box-open text-4xl text-gray-200 mb-3"></i>
                        <p class="text-gray-400">لا توجد منتجات مضافة</p>
                        <?php if ($is_own_profile): ?>
                        <a href="<?= SITE_URL ?>/add_product.php" class="inline-block mt-3 text-emerald-600 font-medium text-sm hover:text-emerald-700">
                            أضف أول منتج الآن <i class="fas fa-arrow-left mr-1"></i>
                        </a>
                        <?php endif; ?>
                    </div>
                    <?php else: ?>
                    <div class="p-5 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        <?php foreach ($products as $product): ?>
                        <div class="border border-gray-100 rounded-xl overflow-hidden hover:shadow-md transition-all">
                            <div class="h-36 bg-gray-100 relative">
                                <?php if ($product['main_image']): ?>
                                <img src="<?= SITE_URL ?>/<?= $product['main_image'] ?>" alt="<?= clean($product['name']) ?>" class="w-full h-full object-cover">
                                <?php else: ?>
                                <div class="w-full h-full flex items-center justify-center">
                                    <i class="fas fa-box text-3xl text-gray-200"></i>
                                </div>
                                <?php endif; ?>
                                <?php if (!$product['is_available']): ?>
                                <div class="absolute top-2 right-2 bg-red-500 text-white text-xs px-2 py-0.5 rounded">غير متوفر</div>
                                <?php endif; ?>
                            </div>
                            <div class="p-3">
                                <h4 class="font-bold text-gray-800 text-sm mb-1 truncate"><?= clean($product['name']) ?></h4>
                                <div class="flex items-center justify-between">
                                    <span class="text-emerald-600 font-bold text-sm">
                                        <?= $product['price'] ? number_format($product['price']) : 'عند الاستفسار' ?>
                                    </span>
                                    <?php if ($product['unit']): ?>
                                    <span class="text-gray-400 text-xs">/ <?= clean($product['unit']) ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
                <!-- التقييمات -->
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
                    <div class="p-6 border-b border-gray-100 flex items-center justify-between">
                        <h3 class="font-bold text-gray-800 text-lg flex items-center gap-2">
                            <i class="fas fa-star text-amber-400"></i> التقييمات والتعليقات
                        </h3>
                        <span class="bg-amber-100 text-amber-700 px-3 py-1 rounded-full text-xs font-bold"><?= count($reviews) ?> تقييم</span>
                    </div>
                    
                    <?php if (empty($reviews)): ?>
                    <div class="p-8 text-center">
                        <i class="fas fa-star text-4xl text-gray-200 mb-3"></i>
                        <p class="text-gray-400">لا توجد تقييمات بعد</p>
                    </div>
                    <?php else: ?>
                    <div class="divide-y divide-gray-50">
                        <?php foreach ($reviews as $review): ?>
                        <div class="p-5 hover:bg-gray-50 transition-colors">
                            <div class="flex items-center justify-between mb-2">
                                <div class="flex items-center gap-2">
                                    <div class="w-8 h-8 bg-gray-100 rounded-full flex items-center justify-center">
                                        <i class="fas fa-user text-gray-400 text-xs"></i>
                                    </div>
                                    <span class="font-medium text-gray-700 text-sm"><?= clean($review['reviewer_name']) ?></span>
                                </div>
                                <div class="flex items-center gap-1">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="fas fa-star text-xs <?= $i <= $review['rating'] ? 'text-amber-400' : 'text-gray-200' ?>"></i>
                                    <?php endfor; ?>
                                </div>
                            </div>
                            <?php if ($review['comment']): ?>
                            <p class="text-gray-600 text-sm leading-relaxed mr-10"><?= nl2br(clean($review['comment'])) ?></p>
                            <?php endif; ?>
                            <div class="text-xs text-gray-300 mt-2 mr-10 flex items-center justify-between">
                                <span><?= timeAgo($review['created_at']) ?></span>
                                <?php if ($review['project_id']): ?>
                                <span class="text-primary-500 text-xs">مرتبط بمشروع</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!$is_own_profile && isset($_SESSION['user_id'])): ?>
                    <div class="p-4 border-t border-gray-100 bg-gray-50">
                        <a href="<?= SITE_URL ?>/add_review.php?id=<?= $view_user_id ?>" 
                           class="block w-full text-center bg-amber-500 hover:bg-amber-600 text-white font-medium py-2.5 rounded-xl transition-colors">
                            <i class="fas fa-star ml-1"></i> قيّم هذا المستخدم
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
                
            </div>
        </div>
    </div>
</div>

<!-- Modal لرفع المستندات -->
<?php if ($is_own_profile && $user_type === 'contractor' && $verification_status !== 'approved'): ?>
<div id="document-modal" class="fixed inset-0 z-50 hidden modal-backdrop flex items-center justify-center p-4" onclick="if(event.target===this) closeDocumentModal()">
    <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="font-bold text-lg text-gray-800">رفع مستند جديد</h3>
            <button onclick="closeDocumentModal()" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        
        <form method="POST" enctype="multipart/form-data" action="upload_document.php?redirect=profile.php?id=<?= $view_user_id ?>">
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">نوع المستند *</label>
                <select name="document_type" required class="w-full border border-gray-300 rounded-xl px-4 py-2">
                    <option value="">اختر النوع</option>
                    <option value="commercial_register">سجل تجاري</option>
                    <option value="tax_card">بطاقة ضريبية</option>
                    <option value="professional_license">رخصة مهنية</option>
                    <option value="bank_account">حساب بنكي</option>
                    <option value="previous_work">أعمال سابقة</option>
                    <option value="other">أخرى</option>
                </select>
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">اسم المستند *</label>
                <input type="text" name="document_name" required class="w-full border border-gray-300 rounded-xl px-4 py-2" placeholder="مثال: السجل التجاري - مؤسسة النور">
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">الملف * (PDF, JPG, PNG)</label>
                <input type="file" name="document_file" accept=".pdf,.jpg,.jpeg,.png" required class="w-full border border-gray-300 rounded-xl px-4 py-2">
            </div>
            <div class="flex gap-3">
                <button type="button" onclick="closeDocumentModal()" class="flex-1 bg-gray-200 text-gray-700 py-2 rounded-lg">إلغاء</button>
                <button type="submit" class="flex-1 bg-primary-600 text-white py-2 rounded-lg hover:bg-primary-700">رفع المستند</button>
            </div>
        </form>
    </div>
</div>

<script>
function openDocumentModal() {
    document.getElementById('document-modal').classList.remove('hidden');
}
function closeDocumentModal() {
    document.getElementById('document-modal').classList.add('hidden');
}
</script>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>