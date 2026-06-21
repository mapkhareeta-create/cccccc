<?php
require_once 'config.php';

// استدعاء مكتبة PHPMailer يدوياً (بدون Composer)
require_once __DIR__ . '/phpmailer/src/Exception.php';
require_once __DIR__ . '/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/phpmailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (isLoggedIn()) {
    redirect('');
}

 $error = '';
 $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = clean($_POST['email'] ?? '');
    
    if (empty($email)) {
        $error = 'يرجى إدخال البريد الإلكتروني';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'صيغة البريد الإلكتروني غير صحيحة';
    } else {
        // التحقق من وجود البريد في قاعدة البيانات
        $stmt = $pdo->prepare("SELECT id, name FROM users WHERE email = ? AND status = 'active'");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user) {
            // إنشاء توكن عشوائي آمن
            $token = bin2hex(random_bytes(32));
            // صلاحية التوكن ساعة واحدة فقط
            $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            // حفظ التوكن في قاعدة البيانات
            $stmt = $pdo->prepare("UPDATE users SET reset_token = ?, reset_expiry = ? WHERE id = ?");
            $stmt->execute([$token, $expiry, $user['id']]);
            
            // رابط إعادة التعيين
            $reset_link = SITE_URL . "/reset_password.php?token=" . $token;
            
            // إرسال الإيميل باستخدام PHPMailer
            $mail = new PHPMailer(true);
            
            try {
                // إعدادات السيرفر
                $mail->isSMTP();
                $mail->Host       = SMTP_HOST;
                $mail->SMTPAuth   = true;
                $mail->Username   = SMTP_USER;
                $mail->Password   = SMTP_PASS;
                $mail->SMTPSecure = SMTP_SECURE;
                $mail->Port       = SMTP_PORT;
                $mail->CharSet    = 'UTF-8';
                
                // المرسل والمستقبل
                $mail->setFrom(FROM_EMAIL, FROM_NAME);
                $mail->addAddress($email, $user['name']);
                
                // محتوى الإيميل
                $mail->isHTML(true);
                $mail->Subject = 'إعادة تعيين كلمة المرور - مزاد البناء';
                
                $mail->Body = '
                <div style="font-family: Cairo, Arial, sans-serif; direction: rtl; background: #f3f4f6; padding: 40px 0;">
                    <div style="max-width: 500px; margin: auto; background: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.05);">
                        <div style="background: linear-gradient(135deg, #1e3a8a, #2563eb); padding: 30px; text-align: center;">
                            <h2 style="color: #ffffff; margin: 0; font-size: 22px;">مزاد البناء</h2>
                        </div>
                        <div style="padding: 30px; text-align: right; color: #374151;">
                            <p style="font-size: 16px; margin-bottom: 15px;">مرحباً ' . htmlspecialchars($user['name']) . '،</p>
                            <p style="font-size: 14px; margin-bottom: 25px; color: #6b7280;">تلقينا طلباً لإعادة تعيين كلمة المرور الخاصة بحسابك. اضغط على الزر أدناه لاختيار كلمة مرور جديدة:</p>
                            <div style="text-align: center; margin: 30px 0;">
                                <a href="' . $reset_link . '" style="background: #2563eb; color: #ffffff; padding: 12px 30px; border-radius: 10px; text-decoration: none; font-weight: bold; display: inline-block;">إعادة تعيين كلمة المرور</a>
                            </div>
                            <p style="font-size: 12px; color: #9ca3af; margin-top: 25px;">إذا لم يكن الزر يعمل، انسخ الرابط التالي والصقه في المتصفح:<br>
                            <a href="' . $reset_link . '" style="color: #2563eb; word-break: break-all;">' . $reset_link . '</a></p>
                            <hr style="border: 0; border-top: 1px solid #e5e7eb; margin: 25px 0;">
                            <p style="font-size: 12px; color: #ef4444;">⚠️ هذا الرابط صالح لمدة ساعة واحدة فقط. إذا لم تطلب إعادة تعيين كلمة المرور، يرجى تجاهل هذه الرسالة.</p>
                        </div>
                    </div>
                </div>';
                
                $mail->send();
                $success = 'تم إرسال رابط إعادة التعيين إلى بريدك الإلكتروني! يرجى التحقق من صندوق الوارد (أو الجنك ميل).';
                
            } catch (Exception $e) {
                $error = "حدث خطأ أثناء إرسال الإيميل. حاول لاحقاً. ({$mail->ErrorInfo})";
            }
        } else {
            // لأسباب أمنية، لا نخبر المستخدم أن الإيميل غير موجود بالضبط
            $success = 'إذا كان هذا البريد مسجلاً لدينا، ستصل إليه رسالة إعادة التعيين.';
        }
    }
}

 $page_title = 'نسيت كلمة السر';
include 'includes/header.php';
?>

<div class="min-h-screen flex items-center justify-center py-12 px-4">
    <div class="max-w-md w-full">
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <!-- Header -->
            <div class="gradient-hero text-white p-8 text-center">
                <div class="w-16 h-16 bg-white/20 rounded-2xl flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-key text-3xl"></i>
                </div>
                <h1 class="text-2xl font-bold">نسيت كلمة السر؟</h1>
                <p class="text-blue-200 mt-1">لا تقلق، أدخل إيميلك وسنرسل لك رابط إعادة التعيين</p>
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
                
                <form method="POST">
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-1">البريد الإلكتروني</label>
                        <div class="relative">
                            <i class="fas fa-envelope absolute right-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                            <input type="email" name="email" required value="<?= clean($_POST['email'] ?? '') ?>"
                                class="w-full border border-gray-300 rounded-xl pr-10 pl-4 py-3 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-all" placeholder="أدخل البريد المسجل لدينا" dir="ltr">
                        </div>
                    </div>
                    
                    <button type="submit" class="w-full bg-primary-600 hover:bg-primary-700 text-white font-bold py-3 rounded-xl transition-all hover:shadow-lg">
                        <i class="fas fa-paper-plane ml-2"></i> إرسال رابط الاستعادة
                    </button>
                </form>
                
                <div class="text-center mt-6 text-sm text-gray-500">
                    تذكرت كلمة المرور؟ <a href="<?= SITE_URL ?>/login.php" class="text-primary-600 font-medium hover:text-primary-700">تسجيل الدخول</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>