<?php
require_once 'config.php';

// التحقق من كوكي "تذكرني"
if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_token'])) {
    $token = $_COOKIE['remember_token'];
    $stmt = $pdo->prepare("SELECT * FROM users WHERE remember_token = ?");
    $stmt->execute([$token]);
    $user = $stmt->fetch();
    
    if ($user) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_type'] = $user['user_type'];
        $_SESSION['user_email'] = $user['email'];
        
        // تجديد الكوكي لمدة أطول
        setcookie('remember_token', $token, time() + (86400 * 30), "/", "", false, true);
        
        // التوجيه حسب نوع المستخدم
        if ($user['user_type'] === 'admin') {
            redirect('admin.php');
        } elseif ($user['user_type'] === 'employer') {
            redirect('employer_dashboard.php');
        } else {
            redirect('index.php');
        }
        exit();
    }
}

// إذا كان المستخدم مسجل دخول بالفعل
if (isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0) {
    if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin') {
        redirect('admin.php');
    } elseif (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'employer') {
        redirect('employer_dashboard.php');
    } else {
        redirect('index.php');
    }
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = clean($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);
    
    if (empty($email) || empty($password)) {
        $error = 'يرجى إدخال البريد الإلكتروني وكلمة المرور';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password'])) {
                // تنظيف الجلسة القديمة
                session_unset();
                
                // تخزين بيانات المستخدم
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_type'] = $user['user_type'];
                $_SESSION['user_email'] = $user['email'];
                
                // معالجة خيار "تذكرني"
                if ($remember) {
                    // إنشاء رمز عشوائي
                    $token = bin2hex(random_bytes(32));
                    
                    // حفظ الرمز في قاعدة البيانات
                    $updateStmt = $pdo->prepare("UPDATE users SET remember_token = ? WHERE id = ?");
                    $updateStmt->execute([$token, $user['id']]);
                    
                    // إنشاء كوكي لمدة 30 يوم
                    setcookie('remember_token', $token, time() + (86400 * 30), "/", "", false, true);
                } else {
                    // حذف أي كوكي سابق
                    setcookie('remember_token', '', time() - 3600, "/");
                }
                
                // تحديث آخر تسجيل دخول
                try {
                    $updateStmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                    $updateStmt->execute([$user['id']]);
                } catch (Exception $e) {
                    error_log("Last login update failed: " . $e->getMessage());
                }
                
                // حفظ الجلسة
                session_write_close();
                
                // التوجيه حسب نوع المستخدم
                if ($user['user_type'] === 'admin') {
                    redirect('admin.php');
                } elseif ($user['user_type'] === 'employer') {
                    redirect('employer_dashboard.php');
                } else {
                    redirect('index.php');
                }
                exit();
            } else {
                $error = 'البريد الإلكتروني أو كلمة المرور غير صحيحة';
            }
        } catch (Exception $e) {
            $error = 'حدث خطأ في النظام، يرجى المحاولة لاحقاً';
            error_log("Login error: " . $e->getMessage());
        }
    }
}

$page_title = 'تسجيل الدخول - ' . SITE_NAME;
include 'includes/header.php';
?>

<style>
    .login-page {
        min-height: calc(100vh - 200px);
        display: flex;
        align-items: center;
        justify-content: center;
        background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
        padding: 20px;
    }
    
    .login-card {
        width: 100%;
        max-width: 450px;
        margin: 0 auto;
        animation: fadeInUp 0.5s ease-out;
    }
    
    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(30px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    .input-group {
        position: relative;
    }
    
    .input-group .input-icon {
        position: absolute;
        right: 15px;
        top: 50%;
        transform: translateY(-50%);
        color: #9ca3af;
    }
    
    .input-group input {
        width: 100%;
        padding: 12px 45px 12px 15px;
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        font-size: 14px;
        transition: all 0.3s ease;
    }
    
    .input-group input:focus {
        outline: none;
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }
    
    .toggle-password {
        position: absolute;
        left: 15px;
        top: 50%;
        transform: translateY(-50%);
        background: none;
        border: none;
        color: #9ca3af;
        cursor: pointer;
    }
    
    .btn-login {
        width: 100%;
        background: linear-gradient(135deg, #3b82f6, #1d4ed8);
        color: white;
        padding: 12px;
        border: none;
        border-radius: 12px;
        font-weight: 600;
        font-size: 16px;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    
    .btn-login:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 20px rgba(59, 130, 246, 0.3);
    }
    
    .remember-checkbox {
        display: flex;
        align-items: center;
        gap: 8px;
        cursor: pointer;
    }
    
    .remember-checkbox input {
        width: 18px;
        height: 18px;
        cursor: pointer;
        accent-color: #3b82f6;
    }
    
    .remember-checkbox span {
        font-size: 13px;
        color: #4b5563;
    }
    
    .login-divider {
        display: flex;
        align-items: center;
        gap: 16px;
        margin: 20px 0;
    }
    
    .login-divider::before,
    .login-divider::after {
        content: '';
        flex: 1;
        height: 1px;
        background: #e5e7eb;
    }
    
    .login-divider span {
        font-size: 13px;
        color: #9ca3af;
        white-space: nowrap;
    }
</style>

<div class="login-page">
    <div class="login-card">
        <div class="bg-white rounded-2xl shadow-xl overflow-hidden">
            <!-- Header -->
            <div class="bg-gradient-to-r from-blue-600 to-indigo-600 p-6 text-white text-center">
                <div class="inline-flex items-center justify-center w-16 h-16 bg-white/20 rounded-full mb-3">
                    <i class="fas fa-sign-in-alt text-2xl"></i>
                </div>
                <h1 class="text-2xl font-bold">تسجيل الدخول</h1>
                <p class="text-blue-100 text-sm mt-1">مرحباً بعودتك! سجل الدخول إلى حسابك</p>
            </div>
            
            <div class="p-6">
                <?php if ($error): ?>
                <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl mb-6 flex items-center gap-2 text-sm">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?= $error ?></span>
                </div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="mb-5">
                        <label class="block text-sm font-medium text-gray-700 mb-2">البريد الإلكتروني</label>
                        <div class="input-group">
                            <span class="input-icon"><i class="fas fa-envelope"></i></span>
                            <input type="email" name="email" required placeholder="example@email.com" dir="ltr">
                        </div>
                    </div>
                    
                    <div class="mb-5">
                        <label class="block text-sm font-medium text-gray-700 mb-2">كلمة المرور</label>
                        <div class="input-group">
                            <span class="input-icon"><i class="fas fa-lock"></i></span>
                            <input type="password" name="password" id="password" required placeholder="••••••••" dir="ltr">
                            <button type="button" class="toggle-password" id="togglePasswordBtn">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="flex items-center justify-between mb-6">
                        <label class="remember-checkbox">
                            <input type="checkbox" name="remember" id="remember">
                            <span>
                                <i class="fas fa-check-circle text-green-500 text-xs"></i>
                                تذكرني
                            </span>
                        </label>
                        <a href="forgot_password.php" class="text-sm text-blue-600 hover:text-blue-700">
                            <i class="fas fa-question-circle"></i> نسيت كلمة المرور؟
                        </a>
                    </div>
                    
                    <button type="submit" class="btn-login">
                        <i class="fas fa-sign-in-alt ml-2"></i> تسجيل الدخول
                    </button>
                </form>
                
                <div class="login-divider">
                    <span>أو</span>
                </div>
                
                <div class="text-center">
                    <p class="text-sm text-gray-500">
                        ليس لديك حساب؟ 
                        <a href="<?= SITE_URL ?>/register.php" class="text-blue-600 font-medium hover:text-blue-700">
                            <i class="fas fa-user-plus"></i> إنشاء حساب جديد
                        </a>
                    </p>
                </div>
                
                <div class="mt-4 text-center">
                    <p class="text-xs text-gray-400">
                        <i class="fas fa-info-circle"></i> عند تفعيل "تذكرني"، ستبقى مسجل الدخول لمدة 30 يوم
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('togglePasswordBtn').addEventListener('click', function() {
    const passwordInput = document.getElementById('password');
    const icon = this.querySelector('i');
    
    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        passwordInput.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
});
</script>

<?php include 'includes/footer.php'; ?>