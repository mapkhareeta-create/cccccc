<?php
// ========================================
// بدء التخزين المؤقت للمخرجات
// ========================================
ob_start();

require_once 'config.php';

// ========================================
// منع الوصول المباشر (GET)
// ========================================
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    sendJsonResponse([
        'status' => 'error', 
        'message' => 'طريقة طلب غير صحيحة. يرجى استخدام POST'
    ]);
    exit;
}

// ========================================
// تعيين نوع المحتوى إلى JSON
// ========================================
header('Content-Type: application/json; charset=utf-8');

// ========================================
// استقبال البيانات
// ========================================
$email = clean($_POST['email'] ?? '');
$name = clean($_POST['name'] ?? '');
$otp = clean($_POST['otp'] ?? '');

// ========================================
// التحقق من البيانات
// ========================================
if (empty($email)) {
    sendJsonResponse([
        'status' => 'error', 
        'message' => 'البريد الإلكتروني مطلوب'
    ]);
    exit;
}

if (empty($otp)) {
    sendJsonResponse([
        'status' => 'error', 
        'message' => 'رمز التحقق مطلوب'
    ]);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    sendJsonResponse([
        'status' => 'error', 
        'message' => 'البريد الإلكتروني غير صالح'
    ]);
    exit;
}

// ========================================
// استخدام PHPMailer
// ========================================
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/phpmailer/src/SMTP.php';
require_once __DIR__ . '/phpmailer/src/Exception.php';

// ========================================
// تحضير محتوى البريد
// ========================================
$subject = "رمز التحقق - مزاد البناء";
$message = "
<html>
<head><meta charset='UTF-8'></head>
<body style='font-family: Arial, sans-serif; direction: rtl;'>
    <div style='max-width: 500px; margin: auto; padding: 20px; border: 1px solid #ddd; border-radius: 10px;'>
        <h2 style='color: #2563eb; text-align: center;'>مرحباً $name،</h2>
        <p style='font-size: 16px;'>شكراً لتسجيلك في منصة <strong>مزاد البناء</strong>. يرجى استخدام رمز التحقق التالي لإكمال عملية التسجيل:</p>
        <div style='text-align: center; margin: 30px 0;'>
            <span style='display: inline-block; font-size: 32px; font-weight: bold; background: #f3f4f6; padding: 15px 25px; letter-spacing: 5px; border-radius: 10px; direction: ltr;'>$otp</span>
        </div>
        <p style='font-size: 14px; color: #666;'>هذا الرمز صالح لمدة 10 دقائق فقط.</p>
        <hr>
        <p style='font-size: 12px; color: #999; text-align: center;'>إذا لم تقم بإنشاء هذا الحساب، يرجى تجاهل هذه الرسالة.</p>
    </div>
</body>
</html>
";

$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->Host       = SMTP_HOST;
    $mail->SMTPAuth   = true;
    $mail->Username   = SMTP_USER;
    $mail->Password   = SMTP_PASS;
    $mail->SMTPSecure = SMTP_SECURE;
    $mail->Port       = SMTP_PORT;

    $mail->setFrom(FROM_EMAIL, FROM_NAME);
    $mail->addAddress($email, $name);
    
    $mail->isHTML(true);
    $mail->Subject = $subject;
    $mail->Body    = $message;
    $mail->CharSet = 'UTF-8';

    $mail->send();
    
    // تنظيف أي مخرجات قبل إرسال JSON
    ob_clean();
    
    sendJsonResponse([
        'status' => 'success',
        'message' => 'تم إرسال رمز التحقق إلى بريدك الإلكتروني',
        'email' => $email
    ]);
    
} catch (Exception $e) {
    error_log("Mail Error: " . $mail->ErrorInfo);
    
    ob_clean();
    sendJsonResponse([
        'status' => 'error',
        'message' => 'فشل إرسال البريد الإلكتروني: ' . $mail->ErrorInfo
    ]);
}

// ========================================
// دالة مساعدة لإرجاع JSON
// ========================================
function sendJsonResponse($data) {
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// ========================================
// ❌ لا تضع ?> هنا ❌
// ========================================