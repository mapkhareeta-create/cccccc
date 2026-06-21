<?php
require_once 'config.php';

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
        
        setcookie('remember_token', $token, time() + (86400 * 30), "/", "", false, true);
        
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
                session_unset();
                
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_type'] = $user['user_type'];
                $_SESSION['user_email'] = $user['email'];
                
                if ($remember) {
                    $token = bin2hex(random_bytes(32));
                    $updateStmt = $pdo->prepare("UPDATE users SET remember_token = ? WHERE id = ?");
                    $updateStmt->execute([$token, $user['id']]);
                    setcookie('remember_token', $token, time() + (86400 * 30), "/", "", false, true);
                } else {
                    setcookie('remember_token', '', time() - 3600, "/");
                }
                
                try {
                    $updateStmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                    $updateStmt->execute([$user['id']]);
                } catch (Exception $e) {
                    error_log("Last login update failed: " . $e->getMessage());
                }
                
                session_write_close();
                
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

$page_title = 'تسجيل الدخول';
include 'includes/header.php';
?>

<div class="min-h-screen flex items-center justify-center bg-gradient-to-br from-blue-50 to-indigo-100 py-12 px-4">
    <div class="w-full max-w-md">
        <div class="bg-white rounded-2xl shadow-xl overflow-hidden">
            <div class="bg-gradient-to-r from-blue-600 to-indigo-600 p-6 text-white text-center">
                <div class="inline-flex items-center justify-center w-16 h-16 bg-white/20 rounded-full mb-3">
                    <i class="fas fa-sign-in-alt text-2xl"></i>
                </div>
                <h1 class="text-2xl font-bold">تسجيل الدخول</h1>
                <p class="text-blue-100 text-sm mt-1">مرحباً بعودتك!</p>
            </div>
            
            <div class="p-6">
                <?php if ($error): ?>
                <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl mb-6 flex items-center gap-2 text-sm">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="mb-5">
                        <label class="block text-sm font-medium text-gray-700 mb-2">البريد الإلكتروني</label>
                        <div class="relative">
                            <i class="fas fa-envelope absolute right-4 top-1/2 -translate-y-1/2 text-gray-400"></i>
                            <input type="email" name="email" required placeholder="example@email.com" dir="ltr" class="w-full border border-gray-300 rounded-xl px-4 py-3 pr-10 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>
                    </div>
                    
                    <div class="mb-5">
                        <label class="block text-sm font-medium text-gray-700 mb-2">كلمة المرور</label>
                        <div class="relative">
                            <i class="fas fa-lock absolute right-4 top-1/2 -translate-y-1/2 text-gray-400"></i>
                            <input type="password" name="password" id="password" required placeholder="••••••••" dir="ltr" class="w-full border border-gray-300 rounded-xl px-4 py-3 pr-10 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <button type="button" id="togglePasswordBtn" class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="flex items-center justify-between mb-6">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" name="remember" class="w-4 h-4 text-blue-600 rounded">
                            <span class="text-sm text-gray-600">تذكرني</span>
                        </label>
                        <a href="forgot_password.php" class="text-sm text-blue-600 hover:text-blue-700">
                            نسيت كلمة المرور؟
                        </a>
                    </div>
                    
                    <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 rounded-xl transition">
                        <i class="fas fa-sign-in-alt ml-2"></i> تسجيل الدخول
                    </button>
                </form>
                
                <div class="my-4 text-center">
                    <span class="text-gray-400">أو</span>
                </div>
                
                <p class="text-sm text-gray-600 text-center">
                    ليس لديك حساب؟ <a href="<?= SITE_URL ?>/register.php" class="text-blue-600 font-medium hover:text-blue-700">إنشاء حساب جديد</a>
                </p>
                
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