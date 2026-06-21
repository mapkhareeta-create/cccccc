<?php
require_once 'config.php';

// ============================================
// 1. التحقق من تسجيل الدخول
// ============================================
if (!isLoggedIn()) {
    redirect('login.php');
}

if ($_SESSION['user_type'] !== 'employer') {
    $_SESSION['error'] = 'غير مسموح لك بهذه العملية';
    redirect('my_bids.php');
}

// ============================================
// 2. التحقق من وجود ID العطاء
// ============================================
$bid_id = (int)($_GET['id'] ?? 0);
if (!$bid_id) {
    $_SESSION['error'] = 'رقم العطاء غير صحيح';
    redirect('my_bids.php');
}

// ============================================
// 3. جلب بيانات العطاء
// ============================================
try {
    $stmt = $pdo->prepare("
        SELECT 
            b.*, 
            p.employer_id, 
            p.title as project_title,
            p.id as project_id,
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
} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    $_SESSION['error'] = 'خطأ في قاعدة البيانات';
    redirect('my_bids.php');
}

// ============================================
// 4. التأكد من أن العطاء يخص صاحب العمل
// ============================================
if (!$bid) {
    $_SESSION['error'] = 'العطاء غير موجود';
    redirect('my_bids.php');
}

if ($bid['employer_id'] != $_SESSION['user_id']) {
    $_SESSION['error'] = 'هذا العطاء ليس لمشروعك';
    redirect('my_bids.php');
}

// ============================================
// 5. التأكد من أن العطاء في حالة "في انتظار موافقة صاحب العمل" (pending_employer)
// ============================================
if ($bid['status'] !== 'pending_employer') {
    $_SESSION['error'] = 'هذا العطاء ليس في حالة انتظار موافقتك. الحالة الحالية: ' . $bid['status'];
    redirect('my_bids.php');
}

// ============================================
// 6. تنفيذ قبول العطاء (القبول النهائي من صاحب العمل)
// ============================================
try {
    $pdo->beginTransaction();
    
    // تحديث حالة العطاء إلى "مقبول" (accepted)
    $stmt = $pdo->prepare("UPDATE bids SET status = 'accepted' WHERE id = ?");
    $stmt->execute([$bid_id]);
    
    // تحديث حالة المشروع إلى "قيد التنفيذ"
    $stmt = $pdo->prepare("UPDATE projects SET status = 'in_progress' WHERE id = ?");
    $stmt->execute([$bid['project_id']]);
    
    // ============================================
    // 7. إنشاء غرفة عمل للمشروع (باستخدام استعلام آمن)
    // ============================================
    $workspace_id = null;
    try {
        // التحقق من وجود الجدول
        $stmt = $pdo->query("SHOW TABLES LIKE 'project_workspace'");
        if ($stmt->rowCount() > 0) {
            // التحقق من عدم وجود غرفة مسبقاً
            $stmt = $pdo->prepare("SELECT id FROM project_workspace WHERE project_id = ? AND bid_id = ?");
            $stmt->execute([$bid['project_id'], $bid_id]);
            $existing = $stmt->fetch();
            
            if (!$existing) {
                // ============================================
                // ** التعديل الجوهري: استخدام INSERT ... SELECT لجلب المعرفين الصحيحين من الجداول الأصلية
                // ============================================
                $stmt = $pdo->prepare("
                    INSERT INTO project_workspace (project_id, bid_id, employer_id, contractor_id, status, created_at)
                    SELECT ?, ?, p.employer_id, b.contractor_id, 'active', NOW()
                    FROM projects p
                    JOIN bids b ON b.id = ?
                    WHERE p.id = ? AND b.id = ?
                ");
                $stmt->execute([
                    $bid['project_id'],
                    $bid_id,
                    $bid_id,           // for JOIN bids
                    $bid['project_id'], // for WHERE p.id
                    $bid_id            // for WHERE b.id
                ]);
                $workspace_id = $pdo->lastInsertId();
                
                // إشعار للمقاول بإنشاء الغرفة
                $stmt = $pdo->prepare("
                    INSERT INTO notifications (user_id, title, message, link, type, created_at) 
                    VALUES (?, ?, ?, ?, 'success', NOW())
                ");
                $stmt->execute([
                    $bid['contractor_id'],
                    '🏗️ تم فتح غرفة عمل للمشروع',
                    'صاحب العمل ' . $_SESSION['user_name'] . ' فتح غرفة عمل لمشروع: ' . $bid['project_title'],
                    SITE_URL . '/project_workspace.php?id=' . $workspace_id
                ]);
            } else {
                $workspace_id = $existing['id'];
            }
        }
    } catch (Exception $e) {
        // تجاهل الأخطاء إذا كان الجدول غير موجود
        error_log("Workspace creation error: " . $e->getMessage());
    }
    
    // ============================================
    // 8. إرسال إشعار داخل النظام للمقاول بالقبول النهائي
    // ============================================
    $notification_title = "🎉 تم قبول عطائك نهائياً!";
    $notification_message = "صاحب العمل " . $_SESSION['user_name'] . " قبل عطائك نهائياً على مشروع: " . $bid['project_title'] . " - تم نقل المشروع إلى قسم المشاريع المحالة قيد التنفيذ";
    $notification_link = SITE_URL . "/my_bids.php";
    
    $stmt = $pdo->prepare("
        INSERT INTO notifications (user_id, title, message, link, type, created_at) 
        VALUES (?, ?, ?, ?, 'success', NOW())
    ");
    $stmt->execute([$bid['contractor_id'], $notification_title, $notification_message, $notification_link]);
    
    $pdo->commit();
    
    // ============================================
    // 9. إرسال البريد الإلكتروني للمقاول (اختياري)
    // ============================================
    $email_sent = false;
    
    try {
        // التحقق من وجود ملفات PHPMailer
        $phpmailer_path = __DIR__ . '/phpmailer/src/PHPMailer.php';
        $smtp_path = __DIR__ . '/phpmailer/src/SMTP.php';
        $exception_path = __DIR__ . '/phpmailer/src/Exception.php';
        
        if (file_exists($phpmailer_path) && file_exists($smtp_path) && file_exists($exception_path)) {
            
            require_once $phpmailer_path;
            require_once $smtp_path;
            require_once $exception_path;
            
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            
            // إعدادات SMTP
            $mail->isSMTP();
            $mail->Host       = SMTP_HOST;
            $mail->SMTPAuth   = true;
            $mail->Username   = SMTP_USER;
            $mail->Password   = SMTP_PASS;
            $mail->SMTPSecure = SMTP_SECURE;
            $mail->Port       = SMTP_PORT;
            
            // إعدادات البريد
            $mail->setFrom(FROM_EMAIL, FROM_NAME);
            $mail->addAddress($bid['contractor_email'], $bid['contractor_name']);
            
            $mail->isHTML(true);
            $mail->CharSet = 'UTF-8';
            $mail->Subject = "🎉 تم قبول عطائك نهائياً - " . $bid['project_title'];
            
            // ============================================
            // محتوى البريد الإلكتروني (نفسه تماماً)
            // ============================================
            $mail->Body = "
            <html dir='rtl'>
            <head>
                <meta charset='UTF-8'>
                <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                <style>
                    body { font-family: 'Segoe UI', Arial, sans-serif; direction: rtl; padding: 20px; background: #f4f7fb; }
                    .container { max-width: 550px; margin: 0 auto; background: #ffffff; border-radius: 16px; padding: 30px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); border: 1px solid #e8edf4; }
                    .header { text-align: center; padding-bottom: 20px; border-bottom: 2px solid #f0f4fa; }
                    .header h1 { color: #1a3a5c; font-size: 24px; margin: 0; }
                    .icon-big { font-size: 48px; display: block; margin-bottom: 10px; }
                    .content { padding: 20px 0; }
                    .content .message { font-size: 16px; color: #2c3e50; line-height: 1.8; }
                    .content .highlight { background: #f0f7ff; padding: 15px; border-radius: 10px; margin: 15px 0; border-right: 4px solid #2563eb; }
                    .content .highlight .label { font-weight: 600; color: #1a3a5c; }
                    .content .highlight .value { color: #2563eb; font-weight: 700; }
                    .btn { display: inline-block; padding: 12px 30px; background: #2563eb; color: white; text-decoration: none; border-radius: 8px; font-weight: 600; }
                    .btn:hover { background: #1d4ed8; }
                    .btn-green { background: #22c55e; }
                    .btn-green:hover { background: #16a34a; }
                    .footer { margin-top: 20px; padding-top: 20px; border-top: 2px solid #f0f4fa; text-align: center; color: #9aafc4; font-size: 12px; }
                    .footer a { color: #2563eb; text-decoration: none; }
                    .info-box { background: #fef3c7; padding: 12px; border-radius: 10px; border-right: 4px solid #f59e0b; margin: 15px 0; }
                    .info-box .info-text { color: #92400e; font-size: 14px; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <span class='icon-big'>🎉</span>
                        <h1>تم قبول عطائك نهائياً!</h1>
                    </div>
                    <div class='content'>
                        <p class='message'>مرحباً <strong>" . clean($bid['contractor_name']) . "</strong>،</p>
                        <p class='message'>نود إعلامك بأن صاحب العمل <strong>" . clean($_SESSION['user_name']) . "</strong> قد <strong style='color:#22c55e;'>قبل نهائياً</strong> عطائك على المشروع التالي:</p>
                        
                        <div class='highlight'>
                            <p><span class='label'>📋 المشروع:</span> <span class='value'>" . clean($bid['project_title']) . "</span></p>
                            <p><span class='label'>💰 قيمة العطاء:</span> <span class='value'>" . number_format($bid['amount']) . " ر.س</span></p>
                            <p><span class='label'>📅 تاريخ القبول النهائي:</span> <span class='value'>" . date('Y-m-d H:i') . "</span></p>
                        </div>
                        
                        <div class='info-box'>
                            <p class='info-text'>✅ <strong>تم نقل المشروع إلى قسم المشاريع المحالة قيد التنفيذ</strong> في لوحة تحكم صاحب العمل</p>
                        </div>
                        
                        <p class='message'>🔔 تم فتح غرفة عمل خاصة للمشروع يمكنك من خلالها التواصل مع صاحب العمل ومشاركة الملفات.</p>
                        
                        <div style='text-align: center; margin: 25px 0;'>
                            <a href='" . SITE_URL . "/my_bids.php' class='btn'>📋 عرض عطاءاتي</a>
                            <a href='" . SITE_URL . "/project_workspace.php?id=" . ($workspace_id ?? 0) . "' class='btn btn-green' style='margin-right:10px;'>🏗️ الذهاب لغرفة العمل</a>
                        </div>
                        
                        <p style='font-size: 14px; color: #64748b; border-top: 1px solid #e8edf4; padding-top: 15px;'>
                            <strong>📌 ملاحظة:</strong> تم إرسال هذا البريد تلقائياً من منصة مزاد البناء. يرجى عدم الرد عليه.
                        </p>
                    </div>
                    <div class='footer'>
                        <p>© " . date('Y') . " <a href='" . SITE_URL . "'>مزاد البناء</a> - جميع الحقوق محفوظة</p>
                        <p style='font-size: 11px; color: #b0c4d8;'>هذا بريد آلي، يرجى عدم الرد عليه</p>
                    </div>
                </div>
            </body>
            </html>
            ";
            
            $mail->AltBody = strip_tags(str_replace(['<br>', '</p>', '<p>'], ["\n", "\n", ''], $mail->Body));
            
            $mail->send();
            $email_sent = true;
            
        } else {
            error_log("PHPMailer files not found at: " . __DIR__ . '/phpmailer/src/');
        }
        
    } catch (Exception $e) {
        $email_sent = false;
        error_log("Mail Error: " . $e->getMessage());
    } catch (Error $e) {
        $email_sent = false;
        error_log("Mail Error (PHP Error): " . $e->getMessage());
    }
    
    // ============================================
    // 10. رسالة النجاح
    // ============================================
    $success_message = '✅ تم قبول العطاء نهائياً بنجاح! تم نقل المشروع "' . $bid['project_title'] . '" إلى قسم <strong>المشاريع المحالة قيد التنفيذ</strong>.';
    
    if ($email_sent) {
        $success_message .= ' تم إرسال إشعار للمقاول عبر البريد الإلكتروني.';
    } else {
        $success_message .= ' تم إرسال إشعار للمقاول داخل النظام.';
    }
    
    $_SESSION['success'] = $success_message;
    
    // ============================================
    // 11. إعادة التوجيه إلى صفحة العطاءات مع فلتر pending_employer
    // ============================================
    redirect('my_bids.php?filter=pending_employer');
    
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Accept Bid Error: " . $e->getMessage());
    $_SESSION['error'] = 'حدث خطأ أثناء قبول العطاء: ' . $e->getMessage();
    redirect('my_bids.php');
}
?>