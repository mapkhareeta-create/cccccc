<?php
require_once 'config.php';

// التأكد من أن المستخدم مقاول
if (!isLoggedIn() || $_SESSION['user_type'] !== 'contractor') {
    redirect('login.php');
}

$bid_id = (int)($_GET['bid_id'] ?? 0);
$project_id = (int)($_GET['project_id'] ?? 0);

if (!$bid_id || !$project_id) {
    $_SESSION['error'] = 'بيانات غير صحيحة';
    redirect('my_bids.php');
}

// جلب بيانات المشروع وصاحب العمل
$stmt = $pdo->prepare("
    SELECT p.*, u.id as employer_id, u.name as employer_name, u.email as employer_email,
           b.id as bid_id, b.contractor_id
    FROM projects p
    JOIN bids b ON b.project_id = p.id
    JOIN users u ON p.employer_id = u.id
    WHERE p.id = ? AND b.id = ? AND b.contractor_id = ?
");
$stmt->execute([$project_id, $bid_id, $_SESSION['user_id']]);
$project = $stmt->fetch();

if (!$project) {
    $_SESSION['error'] = 'المشروع غير موجود أو ليس لك';
    redirect('my_bids.php');
}

if ($project['status'] !== 'completed') {
    $_SESSION['error'] = 'المشروع لم يكتمل بعد';
    redirect('my_bids.php');
}

// التحقق من وجود تقييم سابق (المقاول يقيم صاحب العمل)
$stmt = $pdo->prepare("
    SELECT * FROM reviews 
    WHERE project_id = ? AND reviewer_id = ? AND reviewed_id = ?
");
$stmt->execute([$project_id, $_SESSION['user_id'], $project['employer_id']]);
$existing_review = $stmt->fetch();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rating = (int)($_POST['rating'] ?? 0);
    $review_text = clean($_POST['review_text'] ?? '');
    
    if ($rating < 1 || $rating > 5) {
        $error = 'يرجى اختيار تقييم من 1 إلى 5 نجوم';
    } elseif (empty($review_text)) {
        $error = 'يرجى كتابة تعليقك على صاحب العمل';
    } else {
        try {
            $column_name = 'comment'; // تأكد من اسم العمود
            
            if ($existing_review) {
                $stmt = $pdo->prepare("
                    UPDATE reviews 
                    SET rating = ?, $column_name = ?, updated_at = NOW() 
                    WHERE id = ?
                ");
                $stmt->execute([$rating, $review_text, $existing_review['id']]);
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO reviews (project_id, reviewer_id, reviewed_id, rating, $column_name, created_at) 
                    VALUES (?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([$project_id, $_SESSION['user_id'], $project['employer_id'], $rating, $review_text]);
            }
            
            // تحديث متوسط تقييم صاحب العمل
            $stmt = $pdo->prepare("
                SELECT AVG(rating) as avg_rating, COUNT(*) as count 
                FROM reviews 
                WHERE reviewed_id = ?
            ");
            $stmt->execute([$project['employer_id']]);
            $rating_data = $stmt->fetch();
            
            $avg_rating = round($rating_data['avg_rating'], 1);
            $reviews_count = $rating_data['count'];
            
            $stmt = $pdo->prepare("
                UPDATE users 
                SET rating = ?, reviews_count = ? 
                WHERE id = ?
            ");
            $stmt->execute([$avg_rating, $reviews_count, $project['employer_id']]);
            
            // إشعار لصاحب العمل
            $notification_title = "⭐ تم تقييمك!";
            $notification_message = "المقاول " . $_SESSION['user_name'] . " قام بتقييمك بـ " . $rating . " نجوم على مشروع: " . $project['title'];
            $notification_link = SITE_URL . "/profile.php?id=" . $project['employer_id'];
            
            $stmt = $pdo->prepare("
                INSERT INTO notifications (user_id, title, message, link, type, created_at) 
                VALUES (?, ?, ?, ?, 'info', NOW())
            ");
            $stmt->execute([$project['employer_id'], $notification_title, $notification_message, $notification_link]);
            
            $success = '✅ تم إرسال تقييمك بنجاح! شكراً لتقييمك.';
            
        } catch (Exception $e) {
            $error = 'حدث خطأ: ' . $e->getMessage();
        }
    }
}

$page_title = 'تقييم صاحب العمل';
include 'includes/header.php';
?>

<style>
    .rate-container {
        max-width: 600px;
        margin: 0 auto;
        background: white;
        border-radius: 20px;
        padding: 40px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.05);
        border: 1px solid #e2e8f0;
    }
    
    .stars {
        display: flex;
        gap: 8px;
        justify-content: center;
        direction: ltr;
        font-size: 40px;
    }
    
    .stars .star {
        cursor: pointer;
        color: #e2e8f0;
        transition: all 0.2s;
    }
    
    .stars .star.active {
        color: #f59e0b;
    }
    
    .stars .star:hover {
        transform: scale(1.2);
    }
    
    .stars .star.hover {
        color: #fbbf24;
    }
    
    .rating-label {
        font-size: 18px;
        font-weight: 700;
        color: #0f172a;
        margin-top: 10px;
    }
    
    .rating-text {
        font-size: 14px;
        color: #64748b;
        margin-bottom: 20px;
    }
    
    .review-textarea {
        width: 100%;
        min-height: 120px;
        padding: 14px 16px;
        border: 2px solid #e2e8f0;
        border-radius: 12px;
        font-size: 14px;
        transition: all 0.3s;
        resize: vertical;
    }
    
    .review-textarea:focus {
        outline: none;
        border-color: #3b82f6;
        box-shadow: 0 0 0 4px rgba(59,130,246,0.1);
    }
    
    .btn-submit {
        background: linear-gradient(135deg, #8b5cf6, #7c3aed);
        color: white;
        border: none;
        padding: 14px 32px;
        border-radius: 12px;
        font-weight: 700;
        font-size: 16px;
        cursor: pointer;
        transition: all 0.3s;
        width: 100%;
    }
    
    .btn-submit:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(139,92,246,0.3);
    }
</style>

<div class="py-12">
    <div class="max-w-3xl mx-auto px-4">
        <div class="rate-container">
            
            <div class="text-center mb-8">
                <span class="text-5xl block mb-3">⭐</span>
                <h1 class="text-2xl font-black text-gray-800">تقييم صاحب العمل</h1>
                <p class="text-gray-500 text-sm">قيم تجربتك مع <strong><?= clean($project['employer_name']) ?></strong></p>
                <p class="text-gray-400 text-xs mt-1">مشروع: <?= clean($project['title']) ?></p>
            </div>
            
            <?php if ($error): ?>
            <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl mb-6 text-sm">
                <i class="fas fa-exclamation-circle ml-2"></i> <?= $error ?>
            </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
            <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-xl mb-6 text-sm">
                <i class="fas fa-check-circle ml-2"></i> <?= $success ?>
                <div class="mt-3">
                    <a href="<?= SITE_URL ?>/my_bids.php" class="bg-green-600 text-white px-6 py-2 rounded-lg text-sm hover:bg-green-700 transition-all inline-block">
                        <i class="fas fa-arrow-right ml-1"></i> العودة للعطاءات
                    </a>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if (!$success): ?>
            <form method="POST" action="">
                
                <div class="text-center mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-3">اختر عدد النجوم</label>
                    <div class="stars" id="starsContainer">
                        <span class="star" data-value="1">★</span>
                        <span class="star" data-value="2">★</span>
                        <span class="star" data-value="3">★</span>
                        <span class="star" data-value="4">★</span>
                        <span class="star" data-value="5">★</span>
                    </div>
                    <input type="hidden" name="rating" id="ratingInput" value="<?= $existing_review['rating'] ?? 0 ?>">
                    <div class="rating-label" id="ratingLabel">
                        <?php
                            $rating_texts = [
                                1 => '🌟 سيء جداً',
                                2 => '🌟 سيء',
                                3 => '🌟 جيد',
                                4 => '🌟 جيد جداً',
                                5 => '🌟 ممتاز'
                            ];
                            $current_rating = $existing_review['rating'] ?? 0;
                            echo $rating_texts[$current_rating] ?? 'اختر تقييمك';
                        ?>
                    </div>
                    <div class="rating-text">اختر عدد النجوم لتقييم أداء صاحب العمل</div>
                </div>
                
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">تعليقك على صاحب العمل</label>
                    <textarea name="review_text" class="review-textarea" placeholder="اكتب تجربتك مع صاحب العمل..."><?= clean($existing_review['review'] ?? '') ?></textarea>
                </div>
                
                <button type="submit" class="btn-submit">
                    <i class="fas fa-star ml-2"></i> إرسال التقييم
                </button>
                
                <div class="text-center mt-4">
                    <a href="<?= SITE_URL ?>/my_bids.php" class="text-sm text-gray-400 hover:text-gray-600">
                        <i class="fas fa-arrow-right ml-1"></i> تخطي والعودة للعطاءات
                    </a>
                </div>
            </form>
            <?php endif; ?>
            
        </div>
    </div>
</div>

<script>
// نفس سكريبت النجوم من rate_contractor.php
const stars = document.querySelectorAll('.star');
const ratingInput = document.getElementById('ratingInput');
const ratingLabel = document.getElementById('ratingLabel');

const ratingTexts = {
    1: '🌟 سيء جداً',
    2: '🌟 سيء',
    3: '🌟 جيد',
    4: '🌟 جيد جداً',
    5: '🌟 ممتاز'
};

stars.forEach(star => {
    star.addEventListener('click', function() {
        const value = parseInt(this.dataset.value);
        ratingInput.value = value;
        updateStars(value);
        ratingLabel.textContent = ratingTexts[value] || 'اختر تقييمك';
    });
    
    star.addEventListener('mouseenter', function() {
        const value = parseInt(this.dataset.value);
        stars.forEach(s => {
            if (parseInt(s.dataset.value) <= value) {
                s.classList.add('hover');
            } else {
                s.classList.remove('hover');
            }
        });
    });
    
    star.addEventListener('mouseleave', function() {
        stars.forEach(s => s.classList.remove('hover'));
        const currentValue = parseInt(ratingInput.value) || 0;
        updateStars(currentValue);
    });
});

function updateStars(value) {
    stars.forEach(star => {
        if (parseInt(star.dataset.value) <= value) {
            star.classList.add('active');
        } else {
            star.classList.remove('active');
        }
    });
}

<?php if ($existing_review): ?>
document.addEventListener('DOMContentLoaded', function() {
    const rating = <?= $existing_review['rating'] ?? 0 ?>;
    if (rating > 0) {
        updateStars(rating);
        ratingInput.value = rating;
        ratingLabel.textContent = ratingTexts[rating] || 'اختر تقييمك';
    }
});
<?php endif; ?>
</script>

<?php include 'includes/footer.php'; ?>