<?php
require_once 'config.php';

if (isLoggedIn()) {
    redirect('');
}

 $token = clean($_GET['token'] ?? '');
 $error = '';
 $success = '';

// التحقق من صحة التوكن
if (empty($token)) {
    redirect('login.php');
}

 $stmt = $pdo->prepare("SELECT id, name FROM users WHERE reset_token = ? AND reset_expiry > NOW() AND status = 'active'");
 $stmt->execute([$token]);
 $user = $stmt->fetch();

if (!$user) {
    $error = 'رابط الاستعادة غير صالح أو منتهي الصلاحية. يرجى طلب رابط جديد.';
    $token = ''; // تعطيل الفورم
}

// تحديث كلمة المرور
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($token)) {
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($new_password) || empty($confirm_password)) {
        $error = 'يرجى ملء جميع الحقول';
    } elseif (strlen($new_password) < 6) {
        $error = 'كلمة المرور يجب أن تكون 6 أحرف على الأقل';
    } elseif ($new_password !== $confirm_password) {
        $error = 'كلمة المرور غير متطابقة';
    } else {
        $hashed = password_hash($new_password, PASSWORD_DEFAULT);
        
        // تحديث الباسورد وإلغاء التوكن
        $stmt = $pdo->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_expiry = NULL WHERE id = ?");
        if ($stmt->execute([$hashed, $user['id']])) {
            $success = 'تم تغيير كلمة المرور بنجاح! يمكنك تسجيل الدخول الآن.';
            // توجيه لصفحة الدخول بعد 3 ثواني
            echo "<script>setTimeout(() => window.location.href = '" . SITE_URL . "/login.php', 3000);</script>";
        } else {
            $error = 'حدث خطأ أثناء تحديث كلمة المرور';
        }
    }
}

 $page_title = 'تعيين كلمة سر جديدة';
include 'includes/header.php';
?>

<div class="min-h-screen flex items-center justify-center py-12 px-4">
    <div class="max-w-md w-full">
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="bg-green-600 text-white p-8 text-center">
                <div class="w-16 h-16 bg-white/20 rounded-2xl flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-lock-open text-3xl"></i>
                </div>
                <h1 class="text-2xl font-bold">تعيين كلمة سر جديدة</h1>
            </div>
            
            <div class="p-8">
                <?php if ($error): ?>
                <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl mb-6 flex items-center gap-2">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?= $error ?></span>
                </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-xl mb-6 flex items-center gap-2">
                    <i class="fas fa-check-circle"></i>
                    <span><?= $success ?></span>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($token) && !$success): ?>
                <form method="POST">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">كلمة المرور الجديدة</label>
                        <div class="relative">
                            <input type="password" name="new_password" id="new-password" required
                                class="w-full border border-gray-300 rounded-xl px-4 py-3 focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-all" placeholder="6 أحرف على الأقل" dir="ltr">
                            <button type="button" onclick="togglePassword('new-password')" class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-1">تأكيد كلمة المرور</label>
                        <div class="relative">
                            <input type="password" name="confirm_password" id="confirm-password" required
                                class="w-full border border-gray-300 rounded-xl px-4 py-3 focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-all" placeholder="أعد كتابة كلمة المرور" dir="ltr">
                            <button type="button" onclick="togglePassword('confirm-password')" class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    
                    <button type="submit" class="w-full bg-green-600 hover:bg-green-700 text-white font-bold py-3 rounded-xl transition-all hover:shadow-lg">
                        <i class="fas fa-save ml-2"></i> حفظ كلمة المرور الجديدة
                    </button>
                </form>
                <?php elseif (empty($token) && !$success): ?>
                <div class="text-center">
                    <a href="<?= SITE_URL ?>/forgot_password.php" class="text-primary-600 font-medium hover:text-primary-700">
                        <i class="fas fa-redo ml-1"></i> طلب رابط استعادة جديد
                    </a>
                </div>
                <?php endif; ?>
                
                <div class="text-center mt-6 text-sm text-gray-500">
                    <a href="<?= SITE_URL ?>/login.php" class="text-primary-600 font-medium hover:text-primary-700">
                        <i class="fas fa-arrow-right ml-1"></i> العودة لتسجيل الدخول
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>