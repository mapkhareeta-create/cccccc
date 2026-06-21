<?php
require_once 'config.php';

if (!isLoggedIn() || $_SESSION['user_type'] !== 'employer') {
    redirect('login.php');
}

$bid_id = (int)($_GET['id'] ?? 0);
if (!$bid_id) {
    redirect('my_bids.php');
}

// التحقق من أن العطاء يخص صاحب العمل
$stmt = $pdo->prepare("
    SELECT 
        b.*, 
        p.employer_id, 
        p.title as project_title,
        u.name as contractor_name, 
        u.id as contractor_id,
        u.email as contractor_email
    FROM bids b 
    JOIN projects p ON b.project_id = p.id 
    JOIN users u ON b.contractor_id = u.id
    WHERE b.id = ?
");
$stmt->execute([$bid_id]);
$bid = $stmt->fetch();

if (!$bid || $bid['employer_id'] != $_SESSION['user_id']) {
    redirect('my_bids.php');
}

// ✅ التأكد من أن العطاء في حالة "في انتظار موافقة صاحب العمل"
if ($bid['status'] !== 'pending_employer') {
    $_SESSION['error'] = 'هذا العطاء ليس في حالة انتظار موافقتك';
    redirect('my_bids.php');
}

// رفض العطاء
$stmt = $pdo->prepare("UPDATE bids SET status = 'rejected' WHERE id = ?");
$stmt->execute([$bid_id]);

// ============================================
// إرسال إشعار داخل النظام للمقاول
// ============================================
$notification_title = "❌ تم رفض عطائك";
$notification_message = "صاحب العمل " . $_SESSION['user_name'] . " رفض عطائك على مشروع: " . $bid['project_title'];
$notification_link = SITE_URL . "/my_bids.php";

$stmt = $pdo->prepare("
    INSERT INTO notifications (user_id, title, message, link, type, created_at) 
    VALUES (?, ?, ?, ?, 'error', NOW())
");
$stmt->execute([$bid['contractor_id'], $notification_title, $notification_message, $notification_link]);

// ============================================
// إرسال بريد إلكتروني للمقاول (اختياري)
// ============================================
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/phpmailer/src/SMTP.php';
require_once __DIR__ . '/phpmailer/src/Exception.php';

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
    $mail->addAddress($bid['contractor_email'], $bid['contractor_name']);
    
    $mail->isHTML(true);
    $mail->CharSet = 'UTF-8';
    $mail->Subject = "❌ تم رفض عطائك - " . $bid['project_title'];
    
    $mail->Body = "
    <html dir='rtl'>
    <head>
        <style>
            body { font-family: 'Segoe UI', Arial, sans-serif; direction: rtl; padding: 20px; }
            .container { max-width: 550px; margin: 0 auto; background: #ffffff; border-radius: 16px; padding: 30px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); border: 1px solid #e8edf4; }
            .header { text-align: center; padding-bottom: 20px; border-bottom: 2px solid #f0f4fa; }
            .header h1 { color: #dc2626; font-size: 24px; margin: 0; }
            .icon-big { font-size: 48px; display: block; margin-bottom: 10px; }
            .content { padding: 20px 0; }
            .content .message { font-size: 16px; color: #2c3e50; line-height: 1.8; }
            .content .highlight { background: #fef2f2; padding: 15px; border-radius: 10px; margin: 15px 0; border-right: 4px solid #dc2626; }
            .content .highlight .label { font-weight: 600; color: #1a3a5c; }
            .btn { display: inline-block; padding: 12px 30px; background: #2563eb; color: white; text-decoration: none; border-radius: 8px; font-weight: 600; }
            .footer { margin-top: 20px; padding-top: 20px; border-top: 2px solid #f0f4fa; text-align: center; color: #9aafc4; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <span class='icon-big'>❌</span>
                <h1>تم رفض عطائك</h1>
            </div>
            <div class='content'>
                <p class='message'>مرحباً <strong>" . clean($bid['contractor_name']) . "</strong>،</p>
                <p class='message'>نود إعلامك بأن صاحب العمل <strong>" . clean($_SESSION['user_name']) . "</strong> قد <strong>رفض</strong> عطائك على المشروع التالي:</p>
                
                <div class='highlight'>
                    <p><span class='label'>📋 المشروع:</span> " . clean($bid['project_title']) . "</p>
                    <p><span class='label'>💰 قيمة العطاء:</span> " . number_format($bid['amount']) . " ر.س</p>
                    <p><span class='label'>📅 تاريخ الرفض:</span> " . date('Y-m-d H:i') . "</p>
                </div>
                
                <p class='message'>لا تقلق! هناك العديد من المشاريع الأخرى المتاحة. يمكنك البحث عن مشاريع جديدة وتقديم عطاءات أخرى.</p>
                
                <div style='text-align: center; margin: 25px 0;'>
                    <a href='" . SITE_URL . "/projects_list.php' class='btn'>🔍 استكشاف مشاريع جديدة</a>
                </div>
                
                <p style='font-size: 14px; color: #64748b;'>تم إرسال هذا البريد تلقائياً من منصة مزاد البناء. يرجى عدم الرد عليه.</p>
            </div>
            <div class='footer'>
                &copy; " . date('Y') . " مزاد البناء - جميع الحقوق محفوظة
            </div>
        </div>
    </body>
    </html>
    ";
    
    $mail->AltBody = strip_tags(str_replace(['<br>', '</p>', '<p>'], ["\n", "\n", ''], $mail->Body));
    $mail->send();
    
} catch (Exception $e) {
    error_log("Mail Error: " . $mail->ErrorInfo);
}

$_SESSION['success'] = '❌ تم رفض العطاء. تم إرسال إشعار للمقاول.';
redirect('my_bids.php');
?>