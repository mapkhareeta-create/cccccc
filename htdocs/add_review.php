<?php
require_once 'config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$reviewed_id = (int)($_GET['id'] ?? 0);
$project_id = (int)($_GET['project_id'] ?? 0);

if (!$reviewed_id) {
    redirect('profile.php');
}

$reviewed_user = getUser($reviewed_id);
if (!$reviewed_user) {
    redirect('profile.php');
}

// منع تقييم النفس
if ($_SESSION['user_id'] == $reviewed_id) {
    $_SESSION['error'] = 'لا يمكنك تقييم نفسك';
    redirect('profile.php?id=' . $reviewed_id);
}

// التحقق من وجود مشروع حقيقي إذا تم إرسال project_id
$project_exists = false;
if ($project_id > 0) {
    $stmt = $pdo->prepare("SELECT id FROM projects WHERE id = ?");
    $stmt->execute([$project_id]);
    $project_exists = (bool)$stmt->fetch();
    if (!$project_exists) {
        $project_id = 0;
    }
}

// التحقق من وجود تقييم سابق
$stmt = $pdo->prepare("SELECT id FROM reviews WHERE reviewer_id = ? AND reviewed_id = ? AND (project_id = ? OR (project_id IS NULL AND ? = 0))");
$stmt->execute([$_SESSION['user_id'], $reviewed_id, $project_id > 0 ? $project_id : 0, $project_id]);
$existing_review = $stmt->fetch();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rating = (int)($_POST['rating'] ?? 0);
    $comment = clean($_POST['comment'] ?? '');
    $project_id_from_post = (int)($_POST['project_id'] ?? 0);
    
    // التحقق من صحة project_id من POST
    $valid_project_id = null;
    if ($project_id_from_post > 0) {
        $stmt = $pdo->prepare("SELECT id FROM projects WHERE id = ?");
        $stmt->execute([$project_id_from_post]);
        if ($stmt->fetch()) {
            $valid_project_id = $project_id_from_post;
        }
    } elseif ($project_id > 0) {
        $valid_project_id = $project_id;
    }
    
    if ($rating < 1 || $rating > 5) {
        $error = 'يرجى اختيار تقييم بين 1 و 5 نجوم';
    } elseif (empty($comment)) {
        $error = 'يرجى كتابة تعليق';
    } else {
        try {
            if ($existing_review) {
                // تحديث التقييم الموجود
                $stmt = $pdo->prepare("
                    UPDATE reviews 
                    SET rating = ?, comment = ?, created_at = NOW() 
                    WHERE id = ?
                ");
                $stmt->execute([$rating, $comment, $existing_review['id']]);
                $success = 'تم تحديث تقييمك بنجاح';
            } else {
                // إضافة تقييم جديد
                if ($valid_project_id) {
                    $stmt = $pdo->prepare("
                        INSERT INTO reviews (reviewer_id, reviewed_id, project_id, rating, comment) 
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$_SESSION['user_id'], $reviewed_id, $valid_project_id, $rating, $comment]);
                } else {
                    $stmt = $pdo->prepare("
                        INSERT INTO reviews (reviewer_id, reviewed_id, rating, comment) 
                        VALUES (?, ?, ?, ?)
                    ");
                    $stmt->execute([$_SESSION['user_id'], $reviewed_id, $rating, $comment]);
                }
                $success = 'تم إضافة تقييمك بنجاح';
            }
            
            // تحديث متوسط التقييم وعدد التقييمات للمستخدم (بدون شرط status)
            $stmt = $pdo->prepare("
                SELECT AVG(rating) as avg_rating, COUNT(*) as total 
                FROM reviews 
                WHERE reviewed_id = ?
            ");
            $stmt->execute([$reviewed_id]);
            $stats = $stmt->fetch();
            
            $stmt = $pdo->prepare("
                UPDATE users 
                SET rating = ?, reviews_count = ? 
                WHERE id = ?
            ");
            $stmt->execute([round($stats['avg_rating'] ?? 0, 1), $stats['total'] ?? 0, $reviewed_id]);
            
            // إرسال إشعار للمستخدم الذي تم تقييمه
            createNotification(
                $reviewed_id,
                'تقييم جديد على ملفك الشخصي',
                'قام ' . $_SESSION['user_name'] . ' بتقييمك بـ ' . $rating . ' نجوم',
                'review',
                SITE_URL . '/profile.php?id=' . $reviewed_id
            );
            
        } catch (Exception $e) {
            $error = 'حدث خطأ: ' . $e->getMessage();
        }
    }
}

$page_title = 'تقييم ' . clean($reviewed_user['name']);
include 'includes/header.php';
?>

<div class="py-8">
    <div class="max-w-2xl mx-auto px-4">
        
        <div class="bg-white rounded-2xl shadow-md border border-gray-100 overflow-hidden">
            <!-- Header -->
            <div class="bg-gradient-to-r from-amber-500 to-orange-500 p-6 text-white text-center">
                <div class="inline-flex items-center justify-center w-16 h-16 bg-white/20 rounded-full mb-3">
                    <i class="fas fa-star text-3xl"></i>
                </div>
                <h1 class="text-2xl font-bold">تقييم <?= clean($reviewed_user['name']) ?></h1>
                <p class="text-amber-100 mt-1">شاركنا رأيك في تجربتك مع هذا المستخدم</p>
            </div>
            
            <div class="p-6">
                <?php if ($error): ?>
                <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl mb-6">
                    <i class="fas fa-exclamation-circle ml-2"></i> <?= $error ?>
                </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-xl mb-6">
                    <i class="fas fa-check-circle ml-2"></i> <?= $success ?>
                    <div class="mt-3">
                        <a href="<?= SITE_URL ?>/profile.php?id=<?= $reviewed_id ?>" class="text-green-700 font-medium underline">
                            العودة للملف الشخصي
                        </a>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if (!$success): ?>
                <form method="POST">
                    <!-- تقييم بالنجوم -->
                    <div class="mb-8 text-center">
                        <label class="block text-sm font-medium text-gray-700 mb-3">تقييمك للمستخدم</label>
                        <div class="flex justify-center gap-2 rating-stars">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                            <button type="button" data-rating="<?= $i ?>" class="star-btn text-4xl text-gray-300 hover:text-amber-400 transition-colors focus:outline-none">
                                ☆
                            </button>
                            <?php endfor; ?>
                        </div>
                        <input type="hidden" name="rating" id="rating-value" required>
                        <p class="text-xs text-gray-400 mt-2" id="rating-text">اضغط على النجمة لتحديد التقييم</p>
                    </div>
                    
                    <!-- التعليق -->
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-2">تعليقك <span class="text-red-500">*</span></label>
                        <textarea name="comment" rows="5" required
                            class="w-full border border-gray-300 rounded-xl px-4 py-3 focus:ring-2 focus:ring-amber-500 focus:border-amber-500 transition-all resize-none"
                            placeholder="شاركنا تجربتك مع هذا المستخدم... ماذا أعجبك؟ ماذا يمكن تحسينه؟"></textarea>
                    </div>
                    
                    <!-- معلومات المشروع (إذا كان هناك مشروع صالح) -->
                    <?php if ($project_exists && $project_id > 0): ?>
                    <div class="mb-6 bg-gray-50 rounded-xl p-4">
                        <p class="text-sm text-gray-600">
                            <i class="fas fa-project-diagram ml-2 text-amber-500"></i>
                            هذا التقييم مرتبط بالمشروع
                        </p>
                    </div>
                    <input type="hidden" name="project_id" value="<?= $project_id ?>">
                    <?php endif; ?>
                    
                    <div class="flex gap-3">
                        <button type="submit" class="flex-1 bg-amber-500 hover:bg-amber-600 text-white font-bold py-3 rounded-xl transition-colors">
                            <i class="fas fa-paper-plane ml-2"></i> إرسال التقييم
                        </button>
                        <a href="<?= SITE_URL ?>/profile.php?id=<?= $reviewed_id ?>" class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-bold py-3 px-6 rounded-xl transition-colors">
                            إلغاء
                        </a>
                    </div>
                </form>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- نصائح للتقييم -->
        <div class="bg-amber-50 rounded-xl p-4 mt-6 text-center">
            <p class="text-sm text-amber-800">
                <i class="fas fa-info-circle ml-2"></i>
                تقييماتك تساعد الآخرين في اختيار المقاولين وأصحاب العمل المناسبين.
                كن موضوعياً وعادلاً في تقييمك.
            </p>
        </div>
    </div>
</div>

<script>
// نظام اختيار النجوم
const stars = document.querySelectorAll('.star-btn');
const ratingInput = document.getElementById('rating-value');
const ratingText = document.getElementById('rating-text');

const ratingMessages = {
    1: 'ضعيف جداً - يحتاج تحسين كبير',
    2: 'ضعيف - يمكن تحسينه',
    3: 'جيد - مقبول',
    4: 'جيد جداً - ممتاز',
    5: 'ممتاز - أوصي به بشدة'
};

stars.forEach(star => {
    star.addEventListener('click', function() {
        const rating = parseInt(this.dataset.rating);
        ratingInput.value = rating;
        
        stars.forEach((s, index) => {
            if (index < rating) {
                s.innerHTML = '★';
                s.classList.add('text-amber-400');
                s.classList.remove('text-gray-300');
            } else {
                s.innerHTML = '☆';
                s.classList.remove('text-amber-400');
                s.classList.add('text-gray-300');
            }
        });
        
        ratingText.textContent = ratingMessages[rating];
        ratingText.classList.add('text-amber-600', 'font-medium');
    });
});

// hover effect
stars.forEach(star => {
    star.addEventListener('mouseenter', function() {
        const rating = parseInt(this.dataset.rating);
        stars.forEach((s, index) => {
            if (index < rating) {
                s.innerHTML = '★';
                s.classList.add('text-amber-300');
            } else {
                s.innerHTML = '☆';
            }
        });
    });
    
    star.addEventListener('mouseleave', function() {
        const currentRating = parseInt(ratingInput.value) || 0;
        stars.forEach((s, index) => {
            if (index < currentRating) {
                s.innerHTML = '★';
                s.classList.add('text-amber-400');
                s.classList.remove('text-amber-300');
            } else {
                s.innerHTML = '☆';
                s.classList.remove('text-amber-400', 'text-amber-300');
                s.classList.add('text-gray-300');
            }
        });
    });
});
</script>

<?php include 'includes/footer.php'; ?>