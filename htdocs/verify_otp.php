<?php
require_once 'config.php';

// التأكد من وجود بيانات التسجيل المؤقتة
if (!isset($_SESSION['temp_registration']) || !isset($_SESSION['temp_email'])) {
    redirect('register.php');
}

$temp_data = $_SESSION['temp_registration'];
$email = $_SESSION['temp_email'];
$name = $_SESSION['temp_name'];

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $otp = clean($_POST['otp'] ?? '');
    
    if (empty($otp)) {
        $error = 'يرجى إدخال رمز التحقق';
    } elseif ($otp != $temp_data['otp']) {
        $error = 'رمز التحقق غير صحيح';
    } elseif (strtotime($temp_data['otp_expiry']) < time()) {
        $error = 'انتهت صلاحية رمز التحقق، يرجى إعادة التسجيل';
    } else {
        try {
            $pdo->beginTransaction();
            
            // ============================================
            // 1. إنشاء المستخدم
            // ============================================
            $stmt = $pdo->prepare("
                INSERT INTO users (name, email, password, user_type, entity_type, phone, whatsapp, country_id, city_id, status, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW())
            ");
            $stmt->execute([
                $temp_data['name'],
                $temp_data['email'],
                $temp_data['password'],
                $temp_data['user_type'],
                $temp_data['entity_type'] ?? null,
                $temp_data['phone'] ?? '',
                $temp_data['whatsapp'] ?? '',
                $temp_data['country_id'] ?? 0,
                $temp_data['city_id'] ?? 0
            ]);
            $user_id = $pdo->lastInsertId();
            
            // ============================================
            // 2. إذا كان المستخدم مقاولاً، أضف التخصصات
            // ============================================
            if ($temp_data['user_type'] === 'contractor' && !empty($temp_data['specializations'])) {
                foreach ($temp_data['specializations'] as $spec) {
                    $stmt = $pdo->prepare("INSERT INTO contractor_specs (user_id, spec) VALUES (?, ?)");
                    $stmt->execute([$user_id, $spec]);
                }
            }
            
            // ============================================
            // 3. ✅ إذا كان المستخدم محلاً، أنشئ سجل في جدول shops
            // ============================================
            if ($temp_data['user_type'] === 'shop') {
                // التحقق من وجود اسم المحل
                $shop_name = $temp_data['shop_name'] ?? '';
                if (empty($shop_name)) {
                    throw new Exception('يرجى إدخال اسم المحل');
                }
                
                $stmt = $pdo->prepare("
                    INSERT INTO shops (
                        user_id, 
                        shop_name, 
                        description, 
                        address, 
                        country_id, 
                        city_id, 
                        phone, 
                        whatsapp, 
                        status, 
                        created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
                ");
                $stmt->execute([
                    $user_id,
                    $shop_name,
                    $temp_data['shop_description'] ?? '',
                    $temp_data['shop_address'] ?? '',
                    $temp_data['country_id'] ?? 0,
                    $temp_data['city_id'] ?? 0,
                    $temp_data['phone'] ?? '',
                    $temp_data['whatsapp'] ?? ''
                ]);
            }
            
            $pdo->commit();
            
            // تنظيف الجلسة
            unset($_SESSION['temp_registration']);
            unset($_SESSION['temp_email']);
            unset($_SESSION['temp_name']);
            
            // ============================================
            // 4. تسجيل الدخول تلقائياً
            // ============================================
            $_SESSION['user_id'] = $user_id;
            $_SESSION['user_name'] = $temp_data['name'];
            $_SESSION['user_type'] = $temp_data['user_type'];
            
            // ============================================
            // 5. التوجيه حسب نوع المستخدم
            // ============================================
            if ($temp_data['user_type'] === 'employer') {
                redirect('employer_dashboard.php');
            } elseif ($temp_data['user_type'] === 'shop') {
                redirect('edit_shop.php');
            } else {
                redirect('my_bids.php');
            }
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'حدث خطأ: ' . $e->getMessage();
        }
    }
}

$page_title = 'تأكيد الحساب';
include 'includes/header.php';
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تأكيد الحساب - مزاد البناء</title>
    <script src="https://cdn.tailwindcss.com/3.4.17"></script>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        * { font-family: 'DM Sans', sans-serif; }
        
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
        
        .form-card {
            background: #ffffff;
            border-radius: 16px;
            border: 1px solid #e8edf2;
            padding: 32px;
            max-width: 440px;
            margin: 0 auto;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
        }
        
        .form-card .icon {
            width: 64px;
            height: 64px;
            background: #2563eb;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
        }
        
        .form-card .icon i {
            color: white;
            font-size: 28px;
        }
        
        .form-card h1 {
            color: #0f172a;
            font-size: 22px;
            font-weight: 800;
            text-align: center;
        }
        
        .form-card p {
            color: #94a3b8;
            font-size: 14px;
            text-align: center;
            margin-top: 4px;
        }
        
        .form-group {
            margin-bottom: 16px;
        }
        
        .form-group label {
            display: block;
            font-weight: 600;
            font-size: 13px;
            color: #334155;
            margin-bottom: 4px;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px 14px;
            border: 2px solid #e8edf2;
            border-radius: 10px;
            font-size: 18px;
            text-align: center;
            letter-spacing: 8px;
            transition: all 0.25s ease;
            background: #f8fafc;
            color: #0f172a;
        }
        
        .form-group input:focus {
            border-color: #2563eb;
            outline: none;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
            background: #ffffff;
        }
        
        .form-group .help-text {
            font-size: 12px;
            color: #94a3b8;
            margin-top: 6px;
            text-align: center;
        }
        
        .form-group .help-text strong {
            color: #0f172a;
        }
        
        .btn-submit {
            background: #2563eb;
            color: #ffffff;
            border: none;
            padding: 12px 24px;
            border-radius: 10px;
            font-weight: 700;
            font-size: 15px;
            cursor: pointer;
            transition: all 0.25s ease;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .btn-submit:hover {
            background: #1d4ed8;
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(37, 99, 235, 0.25);
        }
        
        .btn-link {
            display: inline-block;
            color: #2563eb;
            font-weight: 600;
            font-size: 13px;
            text-decoration: none;
            transition: color 0.25s ease;
        }
        
        .btn-link:hover {
            color: #1d4ed8;
        }
        
        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 16px;
            border: 1px solid #fca5a5;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
        }
        
        .divider {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 16px 0;
        }
        
        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: #e8edf2;
        }
        
        .divider span {
            color: #94a3b8;
            font-size: 12px;
            font-weight: 500;
        }
        
        @media (max-width: 480px) {
            .form-card {
                padding: 20px;
            }
            .form-card h1 {
                font-size: 18px;
            }
            .form-group input {
                font-size: 16px;
                padding: 10px 12px;
                letter-spacing: 6px;
            }
        }
    </style>
</head>
<body>

<div class="main-content">
    <div class="max-w-7xl mx-auto px-3 md:px-6">
        
        <div class="form-card">
            <div class="icon">
                <i class="fas fa-envelope"></i>
            </div>
            
            <h1>✅ تأكيد الحساب</h1>
            <p>أدخل رمز التحقق المرسل إلى بريدك الإلكتروني</p>
            
            <?php if ($error): ?>
            <div class="alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?= $error ?>
            </div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label>🔑 رمز التحقق</label>
                    <input type="text" name="otp" required 
                           placeholder="000000" 
                           maxlength="6" 
                           dir="ltr"
                           autocomplete="off"
                           inputmode="numeric">
                    <div class="help-text">
                        تم إرسال الرمز إلى: <strong><?= htmlspecialchars($email) ?></strong>
                    </div>
                </div>
                
                <button type="submit" class="btn-submit">
                    <i class="fas fa-check"></i> تأكيد الحساب
                </button>
            </form>
            
            <div class="divider">
                <span>أو</span>
            </div>
            
            <div class="text-center space-y-2">
                <a href="<?= SITE_URL ?>/resend_otp.php" class="btn-link">
                    <i class="fas fa-redo"></i> إعادة إرسال الرمز
                </a>
                <br>
                <a href="<?= SITE_URL ?>/register.php" class="text-sm text-gray-400 hover:text-gray-600">
                    <i class="fas fa-arrow-right"></i> العودة للتسجيل
                </a>
            </div>
        </div>
        
    </div>
</div>

<script>
    // التركيز على حقل OTP عند تحميل الصفحة
    document.addEventListener('DOMContentLoaded', function() {
        const otpInput = document.querySelector('input[name="otp"]');
        if (otpInput) {
            otpInput.focus();
            
            // الانتقال تلقائياً عند إدخال 6 أرقام
            otpInput.addEventListener('input', function() {
                if (this.value.length === 6) {
                    this.form.submit();
                }
            });
        }
    });
</script>

<?php include 'includes/footer.php'; ?>
</body>
</html>