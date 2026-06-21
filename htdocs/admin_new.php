<?php
require_once 'config.php';

// التأكد من أن المستخدم أدمن
if (!isLoggedIn() || getUserType() !== 'admin') {
    redirect('login.php');
}

$section = clean($_GET['section'] ?? 'dashboard');
$action = clean($_GET['action'] ?? '');
$id = (int)($_GET['id'] ?? 0);

// ============================================
// دوال إرسال البريد الإلكتروني (مستقلة)
// ============================================

// ========================================
// دالة إرسال بريد موافقة المشروع لصاحب العمل
// ========================================
function sendProjectApprovedEmail($to_email, $to_name, $project) {
    date_default_timezone_set('Asia/Riyadh');
    $arabic_months = ['Jan'=>'يناير','Feb'=>'فبراير','Mar'=>'مارس','Apr'=>'أبريل','May'=>'مايو','Jun'=>'يونيو','Jul'=>'يوليو','Aug'=>'أغسطس','Sep'=>'سبتمبر','Oct'=>'أكتوبر','Nov'=>'نوفمبر','Dec'=>'ديسمبر'];
    $current_date = date('d') . ' ' . $arabic_months[date('M')] . ' ' . date('Y');
    $current_time = date('h:i A');
    
    $subject = "✅ تمت الموافقة على مشروعك - " . $project['title'];
    $subject_encoded = "=?UTF-8?B?" . base64_encode($subject) . "?=";
    $from_name_encoded = "=?UTF-8?B?" . base64_encode(FROM_NAME) . "?=";
    
    $message = '
    <!DOCTYPE html>
    <html dir="rtl">
    <head><meta charset="UTF-8"><title>تمت الموافقة على مشروعك</title>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:"Segoe UI",Arial,sans-serif; background:#f0f4f8; padding:20px; direction:rtl; }
        .container { max-width:600px; margin:0 auto; background:#ffffff; border-radius:20px; overflow:hidden; box-shadow:0 10px 40px rgba(0,0,0,0.06); border:1px solid #e8edf4; }
        .header { background:linear-gradient(135deg,#1e3a8a,#2563eb); padding:35px 30px; text-align:center; position:relative; overflow:hidden; }
        .header .icon { font-size:52px; display:block; margin-bottom:8px; position:relative; z-index:1; }
        .header h1 { color:#ffffff; font-size:26px; font-weight:900; position:relative; z-index:1; text-shadow:0 2px 20px rgba(0,0,0,0.1); }
        .header .sub { color:rgba(255,255,255,0.8); font-size:14px; position:relative; z-index:1; }
        .body { padding:35px 30px; }
        .greeting { font-size:20px; font-weight:800; color:#0f172a; margin-bottom:6px; }
        .greeting span { color:#2563eb; }
        .text { color:#334155; font-size:15px; line-height:1.8; margin-bottom:16px; }
        .text strong { color:#0f172a; }
        .info-box { background:#f8fafc; border-radius:16px; padding:20px 24px; margin:20px 0; border:1px solid #f1f5f9; border-right:4px solid #2563eb; }
        .info-box .row { display:flex; justify-content:space-between; padding:6px 0; border-bottom:1px solid #f1f5f9; flex-wrap:wrap; }
        .info-box .row:last-child { border-bottom:none; }
        .info-box .label { color:#64748b; font-size:13px; font-weight:600; }
        .info-box .value { color:#0f172a; font-size:15px; font-weight:700; }
        .btn-group { display:flex; gap:12px; margin-top:20px; justify-content:center; flex-wrap:wrap; }
        .btn-primary { display:inline-flex; align-items:center; gap:8px; padding:12px 32px; background:linear-gradient(135deg,#2563eb,#1d4ed8); color:#ffffff; font-weight:700; font-size:15px; border-radius:12px; text-decoration:none; box-shadow:0 4px 20px rgba(37,99,235,0.3); transition:all 0.3s; }
        .btn-primary:hover { transform:translateY(-2px); box-shadow:0 8px 30px rgba(37,99,235,0.4); }
        .btn-secondary { display:inline-flex; align-items:center; gap:8px; padding:12px 32px; background:#f1f5f9; color:#1e293b; font-weight:700; font-size:15px; border-radius:12px; text-decoration:none; border:1px solid #e2e8f0; transition:all 0.3s; }
        .btn-secondary:hover { background:#e2e8f0; transform:translateY(-2px); }
        .footer { background:#f8fafc; padding:20px 30px; border-top:1px solid #f1f5f9; text-align:center; }
        .footer p { font-size:12px; color:#94a3b8; line-height:1.6; }
        .footer .highlight { color:#2563eb; font-weight:600; }
        .footer .time { font-size:11px; color:#cbd5e1; margin-top:4px; }
        @media (max-width:480px) { .body { padding:20px 16px; } .header { padding:25px 16px; } .header h1 { font-size:20px; } .btn-group { flex-direction:column; } .btn-primary,.btn-secondary { width:100%; justify-content:center; } }
    </style>
    </head>
    <body>
    <div class="container">
        <div class="header">
            <span class="icon">🏗️</span>
            <h1>تمت الموافقة على مشروعك!</h1>
            <div class="sub">تهانينا! مشروعك جاهز للانطلاق</div>
        </div>
        <div class="body">
            <div class="greeting">مرحباً <span>' . htmlspecialchars($to_name) . '</span>،</div>
            <p class="text">نود إعلامك بأنه تمت <strong>الموافقة الإدارية</strong> على مشروعك:</p>
            <div class="info-box">
                <div class="row"><span class="label">📋 المشروع</span><span class="value">' . htmlspecialchars($project['title']) . '</span></div>
                <div class="row"><span class="label">🏷️ التصنيف</span><span class="value">' . htmlspecialchars($project['category']) . '</span></div>
                <div class="row"><span class="label">📅 تاريخ الموافقة</span><span class="value">' . $current_date . '</span></div>
            </div>
            <p class="text">✨ <strong>ماذا الآن؟</strong><br>سيبدأ المقاولون في تقديم عطاءاتهم على مشروعك خلال 24 ساعة.</p>
            <div class="btn-group">
                <a href="' . SITE_URL . '/project_detail.php?id=' . $project['id'] . '" class="btn-primary">📋 عرض المشروع</a>
                <a href="' . SITE_URL . '" class="btn-secondary">🏠 الرئيسية</a>
            </div>
        </div>
        <div class="footer">
            <p>© ' . date('Y') . ' <span class="highlight">مزاد البناء</span> — جميع الحقوق محفوظة</p>
            <div class="time">تم الإرسال في: ' . $current_date . ' - ' . $current_time . '</div>
        </div>
    </div>
    </body>
    </html>
    ';
    
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = SMTP_HOST;
    $mail->SMTPAuth = true;
    $mail->Username = SMTP_USER;
    $mail->Password = SMTP_PASS;
    $mail->SMTPSecure = SMTP_SECURE;
    $mail->Port = SMTP_PORT;
    $mail->setFrom(FROM_EMAIL, $from_name_encoded);
    $mail->addAddress($to_email, $to_name);
    $mail->isHTML(true);
    $mail->CharSet = 'UTF-8';
    $mail->Encoding = 'base64';
    $mail->Subject = $subject_encoded;
    $mail->Body = $message;
    $mail->AltBody = strip_tags(str_replace(['<br>','</p>','<p>'], ["\n","\n",''], $message));
    $mail->set('Date', date('D, d M Y H:i:s O'));
    $mail->send();
}

// ========================================
// دالة إرسال تنبيه للمقاولين
// ========================================
function sendAlertEmail($to_email, $to_name, $project) {
    date_default_timezone_set('Asia/Riyadh');
    $arabic_months = ['Jan'=>'يناير','Feb'=>'فبراير','Mar'=>'مارس','Apr'=>'أبريل','May'=>'مايو','Jun'=>'يونيو','Jul'=>'يوليو','Aug'=>'أغسطس','Sep'=>'سبتمبر','Oct'=>'أكتوبر','Nov'=>'نوفمبر','Dec'=>'ديسمبر'];
    $current_date = date('d') . ' ' . $arabic_months[date('M')] . ' ' . date('Y');
    
    $subject = "📢 مشروع جديد في تخصصك - " . $project['title'];
    $subject_encoded = "=?UTF-8?B?" . base64_encode($subject) . "?=";
    $from_name_encoded = "=?UTF-8?B?" . base64_encode(FROM_NAME) . "?=";
    
    $message = '
    <!DOCTYPE html>
    <html dir="rtl">
    <head><meta charset="UTF-8"><title>مشروع جديد في تخصصك</title>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:"Segoe UI",Arial,sans-serif; background:#f0f4f8; padding:20px; direction:rtl; }
        .container { max-width:600px; margin:0 auto; background:#ffffff; border-radius:20px; overflow:hidden; box-shadow:0 10px 40px rgba(0,0,0,0.06); border:1px solid #e8edf4; }
        .header { background:linear-gradient(135deg,#b45309,#f59e0b); padding:35px 30px; text-align:center; position:relative; overflow:hidden; }
        .header .icon { font-size:52px; display:block; margin-bottom:8px; position:relative; z-index:1; }
        .header h1 { color:#ffffff; font-size:26px; font-weight:900; position:relative; z-index:1; text-shadow:0 2px 20px rgba(0,0,0,0.1); }
        .header .sub { color:rgba(255,255,255,0.85); font-size:14px; position:relative; z-index:1; }
        .body { padding:35px 30px; }
        .greeting { font-size:20px; font-weight:800; color:#0f172a; margin-bottom:6px; }
        .greeting span { color:#f59e0b; }
        .text { color:#334155; font-size:15px; line-height:1.8; margin-bottom:16px; }
        .text strong { color:#0f172a; }
        .info-box { background:#f8fafc; border-radius:16px; padding:20px 24px; margin:20px 0; border:1px solid #f1f5f9; border-right:4px solid #f59e0b; }
        .info-box .row { display:flex; justify-content:space-between; padding:6px 0; border-bottom:1px solid #f1f5f9; flex-wrap:wrap; }
        .info-box .row:last-child { border-bottom:none; }
        .info-box .label { color:#64748b; font-size:13px; font-weight:600; }
        .info-box .value { color:#0f172a; font-size:15px; font-weight:700; }
        .btn-group { display:flex; gap:12px; margin-top:20px; justify-content:center; flex-wrap:wrap; }
        .btn-primary { display:inline-flex; align-items:center; gap:8px; padding:12px 32px; background:linear-gradient(135deg,#f59e0b,#d97706); color:#ffffff; font-weight:700; font-size:15px; border-radius:12px; text-decoration:none; box-shadow:0 4px 20px rgba(245,158,11,0.3); transition:all 0.3s; }
        .btn-primary:hover { transform:translateY(-2px); box-shadow:0 8px 30px rgba(245,158,11,0.4); }
        .btn-secondary { display:inline-flex; align-items:center; gap:8px; padding:12px 32px; background:#f1f5f9; color:#1e293b; font-weight:700; font-size:15px; border-radius:12px; text-decoration:none; border:1px solid #e2e8f0; transition:all 0.3s; }
        .btn-secondary:hover { background:#e2e8f0; transform:translateY(-2px); }
        .footer { background:#f8fafc; padding:20px 30px; border-top:1px solid #f1f5f9; text-align:center; }
        .footer p { font-size:12px; color:#94a3b8; line-height:1.6; }
        .footer .highlight { color:#f59e0b; font-weight:600; }
        @media (max-width:480px) { .body { padding:20px 16px; } .header { padding:25px 16px; } .header h1 { font-size:20px; } .btn-group { flex-direction:column; } .btn-primary,.btn-secondary { width:100%; justify-content:center; } }
    </style>
    </head>
    <body>
    <div class="container">
        <div class="header">
            <span class="icon">🔔</span>
            <h1>مشروع جديد في تخصصك!</h1>
            <div class="sub">فرصة جديدة لا تفوتها</div>
        </div>
        <div class="body">
            <div class="greeting">مرحباً <span>' . htmlspecialchars($to_name) . '</span>،</div>
            <p class="text">تم طرح مشروع جديد في تخصصك على منصة <strong>مزاد البناء</strong>:</p>
            <div class="info-box">
                <div class="row"><span class="label">📋 المشروع</span><span class="value">' . htmlspecialchars($project['title']) . '</span></div>
                <div class="row"><span class="label">🏷️ التخصص</span><span class="value">' . htmlspecialchars($project['category']) . '</span></div>
                <div class="row"><span class="label">📅 تاريخ النشر</span><span class="value">' . $current_date . '</span></div>
            </div>
            <p class="text">💡 قم بتقديم عطائك الآن وكن جزءاً من هذا المشروع.</p>
            <div class="btn-group">
                <a href="' . SITE_URL . '/project_detail.php?id=' . $project['id'] . '" class="btn-primary">📋 عرض المشروع</a>
                <a href="' . SITE_URL . '/my_bids.php" class="btn-secondary">📊 عطاءاتي</a>
            </div>
        </div>
        <div class="footer">
            <p>© ' . date('Y') . ' <span class="highlight">مزاد البناء</span> — جميع الحقوق محفوظة</p>
            <p style="font-size:11px;color:#cbd5e1;">تم إرسال هذا التنبيه تلقائياً بناءً على تخصصك</p>
        </div>
    </div>
    </body>
    </html>
    ';
    
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = SMTP_HOST;
    $mail->SMTPAuth = true;
    $mail->Username = SMTP_USER;
    $mail->Password = SMTP_PASS;
    $mail->SMTPSecure = SMTP_SECURE;
    $mail->Port = SMTP_PORT;
    $mail->setFrom(FROM_EMAIL, $from_name_encoded);
    $mail->addAddress($to_email, $to_name);
    $mail->isHTML(true);
    $mail->CharSet = 'UTF-8';
    $mail->Encoding = 'base64';
    $mail->Subject = $subject_encoded;
    $mail->Body = $message;
    $mail->AltBody = strip_tags(str_replace(['<br>','</p>','<p>'], ["\n","\n",''], $message));
    $mail->set('Date', date('D, d M Y H:i:s O'));
    $mail->send();
}

// ========================================
// دالة إرسال بريد موافقة العطاء للمقاول
// ========================================
function sendBidApprovalEmail($to_email, $to_name, $bid_data) {
    date_default_timezone_set('Asia/Riyadh');
    $arabic_months = ['Jan'=>'يناير','Feb'=>'فبراير','Mar'=>'مارس','Apr'=>'أبريل','May'=>'مايو','Jun'=>'يونيو','Jul'=>'يوليو','Aug'=>'أغسطس','Sep'=>'سبتمبر','Oct'=>'أكتوبر','Nov'=>'نوفمبر','Dec'=>'ديسمبر'];
    $current_date = date('d') . ' ' . $arabic_months[date('M')] . ' ' . date('Y');
    
    $subject = "✅ تمت الموافقة الإدارية على عطائك - " . $bid_data['project_title'];
    $subject_encoded = "=?UTF-8?B?" . base64_encode($subject) . "?=";
    $from_name_encoded = "=?UTF-8?B?" . base64_encode(FROM_NAME) . "?=";
    
    $message = '
    <!DOCTYPE html>
    <html dir="rtl">
    <head><meta charset="UTF-8"><title>تمت الموافقة الإدارية على عطائك</title>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:"Segoe UI",Arial,sans-serif; background:#f0f4f8; padding:20px; direction:rtl; }
        .container { max-width:600px; margin:0 auto; background:#ffffff; border-radius:20px; overflow:hidden; box-shadow:0 10px 40px rgba(0,0,0,0.06); border:1px solid #e8edf4; }
        .header { background:linear-gradient(135deg,#065f46,#059669); padding:35px 30px; text-align:center; position:relative; overflow:hidden; }
        .header .icon { font-size:52px; display:block; margin-bottom:8px; position:relative; z-index:1; }
        .header h1 { color:#ffffff; font-size:26px; font-weight:900; position:relative; z-index:1; text-shadow:0 2px 20px rgba(0,0,0,0.1); }
        .header .sub { color:rgba(255,255,255,0.85); font-size:14px; position:relative; z-index:1; }
        .body { padding:35px 30px; }
        .greeting { font-size:20px; font-weight:800; color:#0f172a; margin-bottom:6px; }
        .greeting span { color:#059669; }
        .text { color:#334155; font-size:15px; line-height:1.8; margin-bottom:16px; }
        .text strong { color:#0f172a; }
        .info-box { background:#f8fafc; border-radius:16px; padding:20px 24px; margin:20px 0; border:1px solid #f1f5f9; border-right:4px solid #059669; }
        .info-box .row { display:flex; justify-content:space-between; padding:6px 0; border-bottom:1px solid #f1f5f9; flex-wrap:wrap; }
        .info-box .row:last-child { border-bottom:none; }
        .info-box .label { color:#64748b; font-size:13px; font-weight:600; }
        .info-box .value { color:#0f172a; font-size:15px; font-weight:700; }
        .info-box .value .amount { color:#059669; font-size:18px; }
        .note-box { background:#fffbeb; border:1px solid #fde68a; border-radius:12px; padding:14px 18px; margin:16px 0; font-size:13px; color:#92400e; }
        .btn-group { display:flex; gap:12px; margin-top:20px; justify-content:center; flex-wrap:wrap; }
        .btn-primary { display:inline-flex; align-items:center; gap:8px; padding:12px 32px; background:linear-gradient(135deg,#059669,#047857); color:#ffffff; font-weight:700; font-size:15px; border-radius:12px; text-decoration:none; box-shadow:0 4px 20px rgba(5,150,105,0.3); transition:all 0.3s; }
        .btn-primary:hover { transform:translateY(-2px); box-shadow:0 8px 30px rgba(5,150,105,0.4); }
        .btn-secondary { display:inline-flex; align-items:center; gap:8px; padding:12px 32px; background:#f1f5f9; color:#1e293b; font-weight:700; font-size:15px; border-radius:12px; text-decoration:none; border:1px solid #e2e8f0; transition:all 0.3s; }
        .btn-secondary:hover { background:#e2e8f0; transform:translateY(-2px); }
        .footer { background:#f8fafc; padding:20px 30px; border-top:1px solid #f1f5f9; text-align:center; }
        .footer p { font-size:12px; color:#94a3b8; line-height:1.6; }
        .footer .highlight { color:#059669; font-weight:600; }
        @media (max-width:480px) { .body { padding:20px 16px; } .header { padding:25px 16px; } .header h1 { font-size:20px; } .btn-group { flex-direction:column; } .btn-primary,.btn-secondary { width:100%; justify-content:center; } }
    </style>
    </head>
    <body>
    <div class="container">
        <div class="header">
            <span class="icon">🎉</span>
            <h1>تمت الموافقة الإدارية على عطائك!</h1>
            <div class="sub">خطوة أولى نحو تنفيذ المشروع</div>
        </div>
        <div class="body">
            <div class="greeting">مرحباً <span>' . htmlspecialchars($to_name) . '</span>،</div>
            <p class="text">نود إعلامك بأنه تمت <strong>الموافقة الإدارية المبدئية</strong> على عطائك.</p>
            <div class="info-box">
                <div class="row"><span class="label">📋 المشروع</span><span class="value">' . htmlspecialchars($bid_data['project_title']) . '</span></div>
                <div class="row"><span class="label">💰 قيمة العطاء</span><span class="value"><span class="amount">' . number_format($bid_data['amount']) . ' ر.س</span></span></div>
                <div class="row"><span class="label">⏱️ المدة</span><span class="value">' . ($bid_data['duration_days'] ?? '-') . ' يوم</span></div>
            </div>
            <div class="note-box">
                ⏳ الآن بانتظار <strong>موافقة صاحب العمل النهائية</strong> على العطاء.
            </div>
            <p class="text">سيتم إشعارك فور اتخاذ صاحب العمل قراره النهائي.</p>
            <div class="btn-group">
                <a href="' . SITE_URL . '/project_detail.php?id=' . $bid_data['project_id'] . '" class="btn-primary">📋 عرض المشروع</a>
                <a href="' . SITE_URL . '/my_bids.php" class="btn-secondary">📊 عطاءاتي</a>
            </div>
        </div>
        <div class="footer">
            <p>© ' . date('Y') . ' <span class="highlight">مزاد البناء</span> — جميع الحقوق محفوظة</p>
        </div>
    </div>
    </body>
    </html>
    ';
    
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = SMTP_HOST;
    $mail->SMTPAuth = true;
    $mail->Username = SMTP_USER;
    $mail->Password = SMTP_PASS;
    $mail->SMTPSecure = SMTP_SECURE;
    $mail->Port = SMTP_PORT;
    $mail->setFrom(FROM_EMAIL, $from_name_encoded);
    $mail->addAddress($to_email, $to_name);
    $mail->isHTML(true);
    $mail->CharSet = 'UTF-8';
    $mail->Encoding = 'base64';
    $mail->Subject = $subject_encoded;
    $mail->Body = $message;
    $mail->AltBody = strip_tags(str_replace(['<br>','</p>','<p>'], ["\n","\n",''], $message));
    $mail->set('Date', date('D, d M Y H:i:s O'));
    $mail->send();
}

// ========================================
// دالة إرسال بريد إشعار لصاحب العمل عن عطاء جديد
// ========================================
function sendBidToEmployerEmail($to_email, $to_name, $bid_data) {
    date_default_timezone_set('Asia/Riyadh');
    $arabic_months = ['Jan'=>'يناير','Feb'=>'فبراير','Mar'=>'مارس','Apr'=>'أبريل','May'=>'مايو','Jun'=>'يونيو','Jul'=>'يوليو','Aug'=>'أغسطس','Sep'=>'سبتمبر','Oct'=>'أكتوبر','Nov'=>'نوفمبر','Dec'=>'ديسمبر'];
    $current_date = date('d') . ' ' . $arabic_months[date('M')] . ' ' . date('Y');
    
    $subject = "📩 عطاء جديد على مشروعك - " . $bid_data['project_title'];
    $subject_encoded = "=?UTF-8?B?" . base64_encode($subject) . "?=";
    $from_name_encoded = "=?UTF-8?B?" . base64_encode(FROM_NAME) . "?=";
    
    $message = '
    <!DOCTYPE html>
    <html dir="rtl">
    <head><meta charset="UTF-8"><title>عطاء جديد على مشروعك</title>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:"Segoe UI",Arial,sans-serif; background:#f0f4f8; padding:20px; direction:rtl; }
        .container { max-width:600px; margin:0 auto; background:#ffffff; border-radius:20px; overflow:hidden; box-shadow:0 10px 40px rgba(0,0,0,0.06); border:1px solid #e8edf4; }
        .header { background:linear-gradient(135deg,#1e40af,#3b82f6); padding:35px 30px; text-align:center; position:relative; overflow:hidden; }
        .header .icon { font-size:52px; display:block; margin-bottom:8px; position:relative; z-index:1; }
        .header h1 { color:#ffffff; font-size:26px; font-weight:900; position:relative; z-index:1; text-shadow:0 2px 20px rgba(0,0,0,0.1); }
        .header .sub { color:rgba(255,255,255,0.85); font-size:14px; position:relative; z-index:1; }
        .body { padding:35px 30px; }
        .greeting { font-size:20px; font-weight:800; color:#0f172a; margin-bottom:6px; }
        .greeting span { color:#3b82f6; }
        .text { color:#334155; font-size:15px; line-height:1.8; margin-bottom:16px; }
        .text strong { color:#0f172a; }
        .info-box { background:#f8fafc; border-radius:16px; padding:20px 24px; margin:20px 0; border:1px solid #f1f5f9; border-right:4px solid #3b82f6; }
        .info-box .row { display:flex; justify-content:space-between; padding:6px 0; border-bottom:1px solid #f1f5f9; flex-wrap:wrap; }
        .info-box .row:last-child { border-bottom:none; }
        .info-box .label { color:#64748b; font-size:13px; font-weight:600; }
        .info-box .value { color:#0f172a; font-size:15px; font-weight:700; }
        .info-box .value .amount { color:#3b82f6; font-size:18px; }
        .note-box { background:#fef3c7; border:1px solid #fde68a; border-radius:12px; padding:14px 18px; margin:16px 0; font-size:13px; color:#92400e; }
        .btn-group { display:flex; gap:12px; margin-top:20px; justify-content:center; flex-wrap:wrap; }
        .btn-primary { display:inline-flex; align-items:center; gap:8px; padding:12px 32px; background:linear-gradient(135deg,#3b82f6,#1d4ed8); color:#ffffff; font-weight:700; font-size:15px; border-radius:12px; text-decoration:none; box-shadow:0 4px 20px rgba(59,130,246,0.3); transition:all 0.3s; }
        .btn-primary:hover { transform:translateY(-2px); box-shadow:0 8px 30px rgba(59,130,246,0.4); }
        .btn-secondary { display:inline-flex; align-items:center; gap:8px; padding:12px 32px; background:#f1f5f9; color:#1e293b; font-weight:700; font-size:15px; border-radius:12px; text-decoration:none; border:1px solid #e2e8f0; transition:all 0.3s; }
        .btn-secondary:hover { background:#e2e8f0; transform:translateY(-2px); }
        .footer { background:#f8fafc; padding:20px 30px; border-top:1px solid #f1f5f9; text-align:center; }
        .footer p { font-size:12px; color:#94a3b8; line-height:1.6; }
        .footer .highlight { color:#3b82f6; font-weight:600; }
        @media (max-width:480px) { .body { padding:20px 16px; } .header { padding:25px 16px; } .header h1 { font-size:20px; } .btn-group { flex-direction:column; } .btn-primary,.btn-secondary { width:100%; justify-content:center; } }
    </style>
    </head>
    <body>
    <div class="container">
        <div class="header">
            <span class="icon">📩</span>
            <h1>عطاء جديد على مشروعك!</h1>
            <div class="sub">تمت الموافقة الإدارية وبانتظار قرارك النهائي</div>
        </div>
        <div class="body">
            <div class="greeting">مرحباً <span>' . htmlspecialchars($to_name) . '</span>،</div>
            <p class="text">تم تقديم عطاء جديد من المقاول <strong>' . htmlspecialchars($bid_data['contractor_name']) . '</strong> على مشروعك:</p>
            <div class="info-box">
                <div class="row"><span class="label">📋 المشروع</span><span class="value">' . htmlspecialchars($bid_data['project_title']) . '</span></div>
                <div class="row"><span class="label">👤 المقاول</span><span class="value">' . htmlspecialchars($bid_data['contractor_name']) . '</span></div>
                <div class="row"><span class="label">💰 قيمة العطاء</span><span class="value"><span class="amount">' . number_format($bid_data['amount']) . ' ر.س</span></span></div>
                <div class="row"><span class="label">⏱️ المدة</span><span class="value">' . ($bid_data['duration_days'] ?? '-') . ' يوم</span></div>
            </div>
            <div class="note-box">
                ⚡ العطاء جاهز لمراجعتك! الموافقة الإدارية تمت بالفعل.
            </div>
            <p class="text">🔑 <strong>إجراء مطلوب:</strong> قم بمراجعة العطاء واتخاذ قرارك النهائي (قبول أو رفض).</p>
            <div class="btn-group">
                <a href="' . SITE_URL . '/project_detail.php?id=' . $bid_data['project_id'] . '" class="btn-primary">📋 مراجعة العطاء</a>
                <a href="' . SITE_URL . '/my_bids.php" class="btn-secondary">📊 عطاءاتي</a>
            </div>
        </div>
        <div class="footer">
            <p>© ' . date('Y') . ' <span class="highlight">مزاد البناء</span> — جميع الحقوق محفوظة</p>
        </div>
    </div>
    </body>
    </html>
    ';
    
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = SMTP_HOST;
    $mail->SMTPAuth = true;
    $mail->Username = SMTP_USER;
    $mail->Password = SMTP_PASS;
    $mail->SMTPSecure = SMTP_SECURE;
    $mail->Port = SMTP_PORT;
    $mail->setFrom(FROM_EMAIL, $from_name_encoded);
    $mail->addAddress($to_email, $to_name);
    $mail->isHTML(true);
    $mail->CharSet = 'UTF-8';
    $mail->Encoding = 'base64';
    $mail->Subject = $subject_encoded;
    $mail->Body = $message;
    $mail->AltBody = strip_tags(str_replace(['<br>','</p>','<p>'], ["\n","\n",''], $message));
    $mail->set('Date', date('D, d M Y H:i:s O'));
    $mail->send();
}

// ========================================
// دالة إرسال بريد رفض العطاء للمقاول
// ========================================
function sendBidRejectedEmail($to_email, $to_name, $bid_data) {
    date_default_timezone_set('Asia/Riyadh');
    $arabic_months = ['Jan'=>'يناير','Feb'=>'فبراير','Mar'=>'مارس','Apr'=>'أبريل','May'=>'مايو','Jun'=>'يونيو','Jul'=>'يوليو','Aug'=>'أغسطس','Sep'=>'سبتمبر','Oct'=>'أكتوبر','Nov'=>'نوفمبر','Dec'=>'ديسمبر'];
    $current_date = date('d') . ' ' . $arabic_months[date('M')] . ' ' . date('Y');
    
    $subject = "❌ تم رفض عطائك - " . $bid_data['project_title'];
    $subject_encoded = "=?UTF-8?B?" . base64_encode($subject) . "?=";
    $from_name_encoded = "=?UTF-8?B?" . base64_encode(FROM_NAME) . "?=";
    
    $message = '
    <!DOCTYPE html>
    <html dir="rtl">
    <head><meta charset="UTF-8"><title>تم رفض عطائك</title>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:"Segoe UI",Arial,sans-serif; background:#f0f4f8; padding:20px; direction:rtl; }
        .container { max-width:600px; margin:0 auto; background:#ffffff; border-radius:20px; overflow:hidden; box-shadow:0 10px 40px rgba(0,0,0,0.06); border:1px solid #e8edf4; }
        .header { background:linear-gradient(135deg,#991b1b,#dc2626); padding:35px 30px; text-align:center; position:relative; overflow:hidden; }
        .header .icon { font-size:52px; display:block; margin-bottom:8px; position:relative; z-index:1; }
        .header h1 { color:#ffffff; font-size:26px; font-weight:900; position:relative; z-index:1; text-shadow:0 2px 20px rgba(0,0,0,0.1); }
        .header .sub { color:rgba(255,255,255,0.85); font-size:14px; position:relative; z-index:1; }
        .body { padding:35px 30px; }
        .greeting { font-size:20px; font-weight:800; color:#0f172a; margin-bottom:6px; }
        .greeting span { color:#dc2626; }
        .text { color:#334155; font-size:15px; line-height:1.8; margin-bottom:16px; }
        .text strong { color:#0f172a; }
        .info-box { background:#f8fafc; border-radius:16px; padding:20px 24px; margin:20px 0; border:1px solid #f1f5f9; border-right:4px solid #dc2626; }
        .info-box .row { display:flex; justify-content:space-between; padding:6px 0; border-bottom:1px solid #f1f5f9; flex-wrap:wrap; }
        .info-box .row:last-child { border-bottom:none; }
        .info-box .label { color:#64748b; font-size:13px; font-weight:600; }
        .info-box .value { color:#0f172a; font-size:15px; font-weight:700; }
        .btn-group { display:flex; gap:12px; margin-top:20px; justify-content:center; flex-wrap:wrap; }
        .btn-primary { display:inline-flex; align-items:center; gap:8px; padding:12px 32px; background:linear-gradient(135deg,#3b82f6,#1d4ed8); color:#ffffff; font-weight:700; font-size:15px; border-radius:12px; text-decoration:none; box-shadow:0 4px 20px rgba(59,130,246,0.3); transition:all 0.3s; }
        .btn-primary:hover { transform:translateY(-2px); box-shadow:0 8px 30px rgba(59,130,246,0.4); }
        .btn-secondary { display:inline-flex; align-items:center; gap:8px; padding:12px 32px; background:#f1f5f9; color:#1e293b; font-weight:700; font-size:15px; border-radius:12px; text-decoration:none; border:1px solid #e2e8f0; transition:all 0.3s; }
        .btn-secondary:hover { background:#e2e8f0; transform:translateY(-2px); }
        .footer { background:#f8fafc; padding:20px 30px; border-top:1px solid #f1f5f9; text-align:center; }
        .footer p { font-size:12px; color:#94a3b8; line-height:1.6; }
        .footer .highlight { color:#dc2626; font-weight:600; }
        @media (max-width:480px) { .body { padding:20px 16px; } .header { padding:25px 16px; } .header h1 { font-size:20px; } .btn-group { flex-direction:column; } .btn-primary,.btn-secondary { width:100%; justify-content:center; } }
    </style>
    </head>
    <body>
    <div class="container">
        <div class="header">
            <span class="icon">❌</span>
            <h1>تم رفض عطائك</h1>
            <div class="sub">لا تقلق، هناك فرص أخرى في انتظارك</div>
        </div>
        <div class="body">
            <div class="greeting">مرحباً <span>' . htmlspecialchars($to_name) . '</span>،</div>
            <p class="text">نود إعلامك بأن عطائك على المشروع التالي <strong>لم يتم قبوله</strong>:</p>
            <div class="info-box">
                <div class="row"><span class="label">📋 المشروع</span><span class="value">' . htmlspecialchars($bid_data['project_title']) . '</span></div>
                <div class="row"><span class="label">💰 قيمة العطاء</span><span class="value">' . number_format($bid_data['amount']) . ' ر.س</span></div>
            </div>
            <p class="text">💪 <strong>لا تيأس!</strong> هناك العديد من المشاريع الأخرى المتاحة على المنصة.</p>
            <div class="btn-group">
                <a href="' . SITE_URL . '/projects_list.php" class="btn-primary">🔍 استكشاف مشاريع جديدة</a>
                <a href="' . SITE_URL . '/my_bids.php" class="btn-secondary">📊 عطاءاتي</a>
            </div>
        </div>
        <div class="footer">
            <p>© ' . date('Y') . ' <span class="highlight">مزاد البناء</span> — جميع الحقوق محفوظة</p>
        </div>
    </div>
    </body>
    </html>
    ';
    
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = SMTP_HOST;
    $mail->SMTPAuth = true;
    $mail->Username = SMTP_USER;
    $mail->Password = SMTP_PASS;
    $mail->SMTPSecure = SMTP_SECURE;
    $mail->Port = SMTP_PORT;
    $mail->setFrom(FROM_EMAIL, $from_name_encoded);
    $mail->addAddress($to_email, $to_name);
    $mail->isHTML(true);
    $mail->CharSet = 'UTF-8';
    $mail->Encoding = 'base64';
    $mail->Subject = $subject_encoded;
    $mail->Body = $message;
    $mail->AltBody = strip_tags(str_replace(['<br>','</p>','<p>'], ["\n","\n",''], $message));
    $mail->set('Date', date('D, d M Y H:i:s O'));
    $mail->send();
}

// ============================================
// معالجة الإجراءات
// ============================================
if ($action && $id) {
    switch ($action) {
        case 'approve_user':
            $pdo->prepare("UPDATE users SET status = 'active', is_verified = 1 WHERE id = ?")->execute([$id]);
            createNotification($id, 'تم تفعيل حسابك', 'تم تفعيل حسابك بنجاح في مزاد البناء', 'success');
            break;
        case 'suspend_user':
            $pdo->prepare("UPDATE users SET status = 'suspended' WHERE id = ?")->execute([$id]);
            createNotification($id, 'تم إيقاف حسابك', 'تم إيقاف حسابك مؤقتاً من قبل الإدارة', 'warning');
            break;
        case 'activate_user':
            $pdo->prepare("UPDATE users SET status = 'active' WHERE id = ?")->execute([$id]);
            createNotification($id, 'تم تنشيط حسابك', 'تم إعادة تنشيط حسابك بنجاح', 'success');
            break;
            
        case 'approve_project':
            $stmt_proj = $pdo->prepare("SELECT employer_id, title, description, category, country_id FROM projects WHERE id = ?");
            $stmt_proj->execute([$id]);
            $proj_data = $stmt_proj->fetch();
            
            if ($proj_data) {
                $pdo->prepare("UPDATE projects SET status = 'open' WHERE id = ?")->execute([$id]);
                
                $stmt = $pdo->prepare("SELECT id, name, email FROM users WHERE id = ?");
                $stmt->execute([$proj_data['employer_id']]);
                $employer = $stmt->fetch();
                
                createNotification(
                    $proj_data['employer_id'], 
                    '✅ تمت الموافقة على مشروعك!', 
                    'تمت الموافقة الإدارية على مشروعك: ' . $proj_data['title'] . '، وتم إضافته للعطاءات.', 
                    'success', 
                    SITE_URL . '/project_detail.php?id=' . $id
                );
                
                // إرسال بريد لصاحب العمل
                if ($employer) {
                    try {
                        sendProjectApprovedEmail($employer['email'], $employer['name'], $proj_data);
                    } catch (Exception $e) {
                        error_log("❌ Failed to send approval email: " . $e->getMessage());
                    }
                }
                
                // إرسال تنبيهات للمقاولين
                if ($proj_data['country_id'] > 0) {
                    try {
                        $stmt = $pdo->prepare("
                            SELECT u.id, u.name, u.email 
                            FROM users u
                            WHERE u.user_type = 'contractor' 
                            AND u.status = 'active'
                            AND u.country_id = ?
                        ");
                        $stmt->execute([$proj_data['country_id']]);
                        $contractors = $stmt->fetchAll();
                        
                        foreach ($contractors as $contractor) {
                            try {
                                sendAlertEmail($contractor['email'], $contractor['name'], $proj_data);
                            } catch (Exception $e) {
                                error_log("❌ Email error for {$contractor['email']}: " . $e->getMessage());
                            }
                        }
                    } catch (Exception $e) {
                        error_log("❌ Alert system error: " . $e->getMessage());
                    }
                }
            }
            break;
            
        case 'reject_project':
            $stmt_proj = $pdo->prepare("SELECT employer_id, title FROM projects WHERE id = ?");
            $stmt_proj->execute([$id]);
            $proj_data = $stmt_proj->fetch();
            if ($proj_data) {
                $pdo->prepare("UPDATE projects SET status = 'cancelled' WHERE id = ?")->execute([$id]);
                createNotification(
                    $proj_data['employer_id'], 
                    'تم رفض مشروعك', 
                    'تم رفض مشروعك: ' . $proj_data['title'] . ' من قبل الإدارة.', 
                    'error', 
                    SITE_URL . '/profile.php'
                );
            }
            break;
            
        case 'delete_project':
            $pdo->prepare("DELETE FROM projects WHERE id = ?")->execute([$id]);
            break;
        case 'delete_shop':
            $pdo->prepare("DELETE FROM shops WHERE id = ?")->execute([$id]);
            break;
        case 'delete_product':
            $pdo->prepare("DELETE FROM products WHERE id = ?")->execute([$id]);
            break;
            
        case 'admin_approve_bid':
            $stmt_bid = $pdo->prepare("
                SELECT b.contractor_id, b.project_id, b.amount, b.proposal, b.duration_days,
                       p.employer_id, p.title as project_title, 
                       u.name as contractor_name, u.email as contractor_email
                FROM bids b 
                JOIN projects p ON b.project_id = p.id 
                JOIN users u ON b.contractor_id = u.id
                WHERE b.id = ?
            ");
            $stmt_bid->execute([$id]);
            $bid_data = $stmt_bid->fetch();
            
            if ($bid_data) {
                $pdo->prepare("UPDATE bids SET admin_approved = 1 WHERE id = ?")->execute([$id]);
                
                createNotification(
                    $bid_data['contractor_id'], 
                    'تمت الموافقة الإدارية على عطائك', 
                    'تمت الموافقة المبدئية على عطائك في مشروع: ' . $bid_data['project_title'] . '، بانتظار موافقة صاحب العمل النهائية.', 
                    'info', 
                    SITE_URL . '/project_detail.php?id=' . $bid_data['project_id']
                );
                
                try {
                    sendBidApprovalEmail($bid_data['contractor_email'], $bid_data['contractor_name'], $bid_data);
                } catch (Exception $e) {
                    error_log("❌ Failed to send bid approval email: " . $e->getMessage());
                }
                
                createNotification(
                    $bid_data['employer_id'], 
                    '📩 عطاء جديد يحتاج موافقتك', 
                    'هناك عطاء جديد من المقاول ' . $bid_data['contractor_name'] . ' على مشروعك: ' . $bid_data['project_title'] . '، تمت الموافقة الإدارية عليه وبانتظار موافقتك النهائية.', 
                    'warning', 
                    SITE_URL . '/project_detail.php?id=' . $bid_data['project_id']
                );
                
                $stmt = $pdo->prepare("SELECT name, email FROM users WHERE id = ?");
                $stmt->execute([$bid_data['employer_id']]);
                $employer = $stmt->fetch();
                
                if ($employer) {
                    try {
                        sendBidToEmployerEmail($employer['email'], $employer['name'], $bid_data);
                    } catch (Exception $e) {
                        error_log("❌ Failed to send bid notification to employer: " . $e->getMessage());
                    }
                }
            }
            break;
            
        case 'reject_bid':
            $stmt_bid = $pdo->prepare("
                SELECT b.contractor_id, b.project_id, p.title as project_title,
                       u.name as contractor_name, u.email as contractor_email,
                       b.amount
                FROM bids b 
                JOIN projects p ON b.project_id = p.id 
                JOIN users u ON b.contractor_id = u.id
                WHERE b.id = ?
            ");
            $stmt_bid->execute([$id]);
            $bid_data = $stmt_bid->fetch();
            
            if ($bid_data) {
                $pdo->prepare("UPDATE bids SET status = 'rejected', admin_approved = 0 WHERE id = ?")->execute([$id]);
                
                createNotification(
                    $bid_data['contractor_id'], 
                    'تم رفض عطاءك', 
                    'تم رفض عطائك في مشروع: ' . $bid_data['project_title'], 
                    'error', 
                    SITE_URL . '/project_detail.php?id=' . $bid_data['project_id']
                );
                
                try {
                    sendBidRejectedEmail($bid_data['contractor_email'], $bid_data['contractor_name'], $bid_data);
                } catch (Exception $e) {
                    error_log("❌ Failed to send bid rejected email: " . $e->getMessage());
                }
            }
            break;
            
        case 'delete_bid':
            $pdo->prepare("DELETE FROM bids WHERE id = ?")->execute([$id]);
            break;
            
        case 'reset_bid':
            $pdo->prepare("UPDATE bids SET status = 'pending', admin_approved = 0 WHERE id = ?")->execute([$id]);
            break;
            
        // ============================================
        // إجراءات غرفة العمل والمشاريع
        // ============================================
        case 'delete_workspace':
            $pdo->prepare("DELETE FROM project_workspace WHERE id = ?")->execute([$id]);
            break;
            
        case 'close_workspace':
            $pdo->prepare("UPDATE project_workspace SET status = 'completed' WHERE id = ?")->execute([$id]);
            break;
            
        case 'delete_claim':
            $pdo->prepare("DELETE FROM workspace_claims WHERE id = ?")->execute([$id]);
            break;
            
        case 'delete_change_order':
            $pdo->prepare("DELETE FROM workspace_change_orders WHERE id = ?")->execute([$id]);
            break;
    }
    
    try {
        $table_check = $pdo->query("SHOW TABLES LIKE 'admin_logs'")->rowCount();
        if ($table_check > 0) {
            $pdo->prepare("INSERT INTO admin_logs (admin_id, action, details) VALUES (?, ?, ?)")
                ->execute([$_SESSION['user_id'], $action, "ID: $id"]);
        }
    } catch (Exception $e) {
        // تجاهل
    }
    
    redirect('admin.php?section=' . $section);
}

// ============================================
// إحصائيات
// ============================================
$stats = [
    'users' => $pdo->query("SELECT COUNT(*) FROM users WHERE user_type != 'admin'")->fetchColumn(),
    'employers' => $pdo->query("SELECT COUNT(*) FROM users WHERE user_type = 'employer'")->fetchColumn(),
    'contractors' => $pdo->query("SELECT COUNT(*) FROM users WHERE user_type = 'contractor'")->fetchColumn(),
    'shops' => $pdo->query("SELECT COUNT(*) FROM users WHERE user_type = 'shop'")->fetchColumn(),
    'projects' => $pdo->query("SELECT COUNT(*) FROM projects")->fetchColumn(),
    'open_projects' => $pdo->query("SELECT COUNT(*) FROM projects WHERE status = 'open'")->fetchColumn(),
    'pending_projects' => $pdo->query("SELECT COUNT(*) FROM projects WHERE status = 'pending'")->fetchColumn(),
    'bids' => $pdo->query("SELECT COUNT(*) FROM bids")->fetchColumn(),
    'products' => $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn(),
    'pending_users' => $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'pending'")->fetchColumn(),
    'pending_bids' => $pdo->query("SELECT COUNT(*) FROM bids WHERE status = 'pending'")->fetchColumn(),
    'admin_approved_bids' => $pdo->query("SELECT COUNT(*) FROM bids WHERE admin_approved = 1 AND status = 'pending'")->fetchColumn(),
    'workspaces' => $pdo->query("SELECT COUNT(*) FROM project_workspace")->fetchColumn(),
    'claims' => $pdo->query("SELECT COUNT(*) FROM workspace_claims")->fetchColumn(),
    'change_orders' => $pdo->query("SELECT COUNT(*) FROM workspace_change_orders")->fetchColumn(),
    'messages' => $pdo->query("SELECT COUNT(*) FROM workspace_messages")->fetchColumn(),
];

$page_title = 'لوحة التحكم - الأدمن';
include 'includes/header.php';
?>

<style>
    .admin-sidebar {
        background: #0f172a;
        color: #94a3b8;
        min-height: 100vh;
        width: 250px;
        flex-shrink: 0;
        position: sticky;
        top: 0;
    }
    
    .admin-sidebar .logo {
        padding: 20px;
        border-bottom: 1px solid #1e293b;
    }
    
    .admin-sidebar .logo span {
        color: white;
        font-weight: 700;
        font-size: 18px;
    }
    
    .admin-sidebar .nav-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px 20px;
        color: #94a3b8;
        text-decoration: none;
        transition: all 0.2s;
        border-radius: 8px;
        margin: 2px 8px;
    }
    
    .admin-sidebar .nav-item:hover {
        background: #1e293b;
        color: white;
    }
    
    .admin-sidebar .nav-item.active {
        background: #2563eb;
        color: white;
    }
    
    .admin-sidebar .nav-item .badge {
        background: #ef4444;
        color: white;
        font-size: 10px;
        padding: 2px 8px;
        border-radius: 20px;
        margin-right: auto;
    }
    
    .admin-sidebar .nav-item .badge.yellow {
        background: #f59e0b;
    }
    
    .admin-sidebar .nav-item .badge.green {
        background: #22c55e;
    }
    
    .admin-sidebar .nav-divider {
        border-top: 1px solid #1e293b;
        margin: 8px 16px;
    }
    
    .admin-content {
        flex: 1;
        padding: 24px;
        background: #f8fafc;
        min-height: 100vh;
    }
    
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 16px;
        margin-bottom: 24px;
    }
    
    .stat-card {
        background: white;
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.06);
        border: 1px solid #e2e8f0;
    }
    
    .stat-card .number {
        font-size: 28px;
        font-weight: 900;
        color: #0f172a;
    }
    
    .stat-card .label {
        font-size: 13px;
        color: #64748b;
        margin-top: 4px;
    }
    
    .stat-card .icon {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
    }
    
    .stat-card .icon.blue { background: #eff6ff; color: #3b82f6; }
    .stat-card .icon.green { background: #f0fdf4; color: #22c55e; }
    .stat-card .icon.amber { background: #fffbeb; color: #f59e0b; }
    .stat-card .icon.purple { background: #f5f3ff; color: #8b5cf6; }
    .stat-card .icon.red { background: #fef2f2; color: #ef4444; }
    .stat-card .icon.teal { background: #f0fdfa; color: #14b8a6; }
    
    .stat-card .icon i {
        font-size: 18px;
    }
    
    .table-container {
        background: white;
        border-radius: 12px;
        border: 1px solid #e2e8f0;
        overflow: hidden;
    }
    
    .table-container .table-header {
        padding: 16px 20px;
        border-bottom: 1px solid #e2e8f0;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
    }
    
    .table-container .table-header h3 {
        font-weight: 700;
        color: #0f172a;
        margin: 0;
    }
    
    .filter-buttons {
        display: flex;
        gap: 6px;
        flex-wrap: wrap;
    }
    
    .filter-buttons a {
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
        text-decoration: none;
        transition: all 0.2s;
    }
    
    .filter-buttons a:hover {
        transform: translateY(-1px);
    }
    
    .btn-xs {
        padding: 4px 10px;
        border-radius: 6px;
        font-size: 11px;
        font-weight: 600;
        text-decoration: none;
        transition: all 0.2s;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        border: none;
        cursor: pointer;
    }
    
    .btn-xs:hover {
        transform: translateY(-1px);
    }
    
    @media (max-width: 768px) {
        .admin-sidebar {
            display: none;
        }
        .admin-content {
            padding: 16px;
        }
        .stats-grid {
            grid-template-columns: 1fr 1fr;
        }
        .table-container .table-header {
            flex-direction: column;
            align-items: flex-start;
        }
    }
    
    @media (max-width: 480px) {
        .stats-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<!-- ============================================ -->
<!-- زر القائمة الجانبية (للجوال واللابتوب) -->
<!-- ============================================ -->
<button onclick="toggleAdminSidebar()" id="adminSidebarToggle" style="
    position: fixed;
    top: 80px;
    right: 16px;
    z-index: 999;
    background: white;
    color: #1e293b;
    padding: 12px 14px;
    border-radius: 50%;
    box-shadow: 0 4px 20px rgba(0,0,0,0.12);
    border: 1px solid #e2e8f0;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
">
    <i class="fas fa-bars" style="font-size: 18px;"></i>
</button>

<div class="flex min-h-screen">
    
    <!-- ============================================ -->
    <!-- Sidebar -->
    <!-- ============================================ -->
    <aside class="admin-sidebar" id="adminSidebar">
        <div class="logo">
            <span>🛡️ لوحة التحكم</span>
        </div>
        <nav style="padding: 12px 0;">
            <a href="admin.php?section=dashboard" class="nav-item <?= $section === 'dashboard' ? 'active' : '' ?>">
                <i class="fas fa-chart-pie" style="width:20px;"></i> الإحصائيات
            </a>
            <a href="admin.php?section=users" class="nav-item <?= $section === 'users' ? 'active' : '' ?>">
                <i class="fas fa-users" style="width:20px;"></i> المستخدمين
                <?php if ($stats['pending_users'] > 0): ?>
                <span class="badge yellow"><?= $stats['pending_users'] ?></span>
                <?php endif; ?>
            </a>
            <a href="admin.php?section=projects" class="nav-item <?= $section === 'projects' ? 'active' : '' ?>">
                <i class="fas fa-project-diagram" style="width:20px;"></i> المشاريع
                <?php if ($stats['pending_projects'] > 0): ?>
                <span class="badge yellow"><?= $stats['pending_projects'] ?></span>
                <?php endif; ?>
            </a>
            <a href="admin.php?section=bids_list" class="nav-item <?= $section === 'bids_list' ? 'active' : '' ?>">
                <i class="fas fa-gavel" style="width:20px;"></i> العطاءات
                <?php if ($stats['pending_bids'] > 0): ?>
                <span class="badge red"><?= $stats['pending_bids'] ?></span>
                <?php endif; ?>
            </a>
            <a href="admin.php?section=workspaces" class="nav-item <?= $section === 'workspaces' ? 'active' : '' ?>">
                <i class="fas fa-door-open" style="width:20px;"></i> غرف العمل
                <span class="badge green"><?= $stats['workspaces'] ?></span>
            </a>
            <a href="admin.php?section=shops_list" class="nav-item <?= $section === 'shops_list' ? 'active' : '' ?>">
                <i class="fas fa-store" style="width:20px;"></i> المحلات
            </a>
            <a href="admin.php?section=products" class="nav-item <?= $section === 'products' ? 'active' : '' ?>">
                <i class="fas fa-boxes" style="width:20px;"></i> المنتجات
            </a>
            <a href="admin.php?section=documents" class="nav-item <?= $section === 'documents' ? 'active' : '' ?>">
                <i class="fas fa-file-alt" style="width:20px;"></i> المستندات
            </a>
            <a href="admin.php?section=logs" class="nav-item <?= $section === 'logs' ? 'active' : '' ?>">
                <i class="fas fa-history" style="width:20px;"></i> سجل العمليات
            </a>
            <div class="nav-divider"></div>
            <a href="<?= SITE_URL ?>/" class="nav-item">
                <i class="fas fa-home" style="width:20px;"></i> الرئيسية
            </a>
            <a href="<?= SITE_URL ?>/logout.php" class="nav-item" style="color: #ef4444;">
                <i class="fas fa-sign-out-alt" style="width:20px;"></i> تسجيل خروج
            </a>
        </nav>
    </aside>
    
    <!-- ============================================ -->
    <!-- Main Content -->
    <!-- ============================================ -->
    <main class="admin-content" id="adminContent">
        
        <?php if ($section === 'dashboard'): ?>
        <!-- Dashboard -->
        <h2 class="text-2xl font-bold text-gray-800 mb-6">📊 لوحة التحكم</h2>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="number"><?= number_format($stats['users']) ?></div>
                        <div class="label">👥 إجمالي المستخدمين</div>
                    </div>
                    <div class="icon blue"><i class="fas fa-users"></i></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="number"><?= number_format($stats['projects']) ?></div>
                        <div class="label">🏗️ إجمالي المشاريع</div>
                    </div>
                    <div class="icon green"><i class="fas fa-project-diagram"></i></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="number"><?= number_format($stats['bids']) ?></div>
                        <div class="label">📋 إجمالي العطاءات</div>
                    </div>
                    <div class="icon amber"><i class="fas fa-gavel"></i></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="number"><?= number_format($stats['products']) ?></div>
                        <div class="label">📦 المنتجات</div>
                    </div>
                    <div class="icon purple"><i class="fas fa-boxes"></i></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="number"><?= number_format($stats['workspaces']) ?></div>
                        <div class="label">🚪 غرف العمل</div>
                    </div>
                    <div class="icon teal"><i class="fas fa-door-open"></i></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="number"><?= number_format($stats['pending_projects']) ?></div>
                        <div class="label">⏳ مشاريع معلقة</div>
                    </div>
                    <div class="icon red"><i class="fas fa-clock"></i></div>
                </div>
            </div>
        </div>
        
        <!-- تفاصيل المستخدمين -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
            <div class="bg-white rounded-xl p-5 border border-gray-100 shadow-sm flex items-center gap-4">
                <div class="w-14 h-14 bg-blue-100 rounded-xl flex items-center justify-center"><i class="fas fa-building text-blue-600 text-xl"></i></div>
                <div><div class="text-2xl font-bold text-gray-800"><?= number_format($stats['employers']) ?></div><div class="text-sm text-gray-500">أصحاب عمل</div></div>
            </div>
            <div class="bg-white rounded-xl p-5 border border-gray-100 shadow-sm flex items-center gap-4">
                <div class="w-14 h-14 bg-amber-100 rounded-xl flex items-center justify-center"><i class="fas fa-hard-hat text-amber-600 text-xl"></i></div>
                <div><div class="text-2xl font-bold text-gray-800"><?= number_format($stats['contractors']) ?></div><div class="text-sm text-gray-500">مقاولين</div></div>
            </div>
            <div class="bg-white rounded-xl p-5 border border-gray-100 shadow-sm flex items-center gap-4">
                <div class="w-14 h-14 bg-green-100 rounded-xl flex items-center justify-center"><i class="fas fa-store text-green-600 text-xl"></i></div>
                <div><div class="text-2xl font-bold text-gray-800"><?= number_format($stats['shops']) ?></div><div class="text-sm text-gray-500">محلات</div></div>
            </div>
        </div>
        
        <!-- Recent Users -->
        <?php
        $recent_users = $pdo->query("SELECT u.*, c.name_ar as country_name FROM users u LEFT JOIN countries c ON u.country_id = c.id WHERE u.user_type != 'admin' ORDER BY u.created_at DESC LIMIT 5")->fetchAll();
        ?>
        <div class="table-container">
            <div class="table-header"><h3>👤 أحدث المستخدمين</h3></div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr><th class="text-right px-5 py-3 font-medium text-gray-500">الاسم</th><th class="text-right px-5 py-3 font-medium text-gray-500">البريد</th><th class="text-right px-5 py-3 font-medium text-gray-500">النوع</th><th class="text-right px-5 py-3 font-medium text-gray-500">الدولة</th><th class="text-right px-5 py-3 font-medium text-gray-500">الحالة</th></tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <?php foreach ($recent_users as $user): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-5 py-3 font-medium"><?= clean($user['name']) ?></td>
                            <td class="px-5 py-3 text-gray-500 text-xs"><?= clean($user['email']) ?></td>
                            <td class="px-5 py-3"><?php $t=$user['user_type']; $type_labels=['employer'=>'صاحب عمل','contractor'=>'مقاول','shop'=>'محل']; $type_colors=['employer'=>'blue','contractor'=>'amber','shop'=>'green']; ?><span class="bg-<?= $type_colors[$t] ?>-100 text-<?= $type_colors[$t] ?>-700 px-2 py-1 rounded-lg text-xs"><?= $type_labels[$t] ?? $t ?></span></td>
                            <td class="px-5 py-3 text-gray-500"><?= clean($user['country_name'] ?? '-') ?></td>
                            <td class="px-5 py-3"><?php $s=$user['status']; $sl=['active'=>'مفعّل','suspended'=>'موقوف','pending'=>'معلّق']; $sc=['active'=>'green','suspended'=>'red','pending'=>'yellow']; ?><span class="bg-<?= $sc[$s] ?>-100 text-<?= $sc[$s] ?>-700 px-2 py-1 rounded-lg text-xs"><?= $sl[$s] ?? $s ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <?php elseif ($section === 'documents'): ?>
        <script>window.location.href = "<?= SITE_URL ?>/admin_documents.php";</script>
        <?php exit; ?>
        
        <?php elseif ($section === 'users'): ?>
        <h2 class="text-2xl font-bold text-gray-800 mb-6">👥 إدارة المستخدمين</h2>
        
        <?php
        $all_users = $pdo->query("SELECT u.*, c.name_ar as country_name, ci.name_ar as city_name FROM users u LEFT JOIN countries c ON u.country_id = c.id LEFT JOIN cities ci ON u.city_id = ci.id WHERE u.user_type != 'admin' ORDER BY u.created_at DESC")->fetchAll();
        ?>
        
        <div class="table-container">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr><th class="text-right px-5 py-3 font-medium text-gray-500">#</th><th class="text-right px-5 py-3 font-medium text-gray-500">الاسم</th><th class="text-right px-5 py-3 font-medium text-gray-500">البريد</th><th class="text-right px-5 py-3 font-medium text-gray-500">النوع</th><th class="text-right px-5 py-3 font-medium text-gray-500">الموقع</th><th class="text-right px-5 py-3 font-medium text-gray-500">الحالة</th><th class="text-right px-5 py-3 font-medium text-gray-500">إجراءات</th></tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <?php foreach ($all_users as $i => $user): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-5 py-3 text-gray-400"><?= $i+1 ?></td>
                            <td class="px-5 py-3 font-medium"><?= clean($user['name']) ?></td>
                            <td class="px-5 py-3 text-gray-500 text-xs"><?= clean($user['email']) ?></td>
                            <td class="px-5 py-3"><?php $t=$user['user_type']; $type_labels=['employer'=>'صاحب عمل','contractor'=>'مقاول','shop'=>'محل']; $type_colors=['employer'=>'blue','contractor'=>'amber','shop'=>'green']; ?><span class="bg-<?= $type_colors[$t] ?>-100 text-<?= $type_colors[$t] ?>-700 px-2 py-1 rounded-lg text-xs"><?= $type_labels[$t] ?? $t ?></span></td>
                            <td class="px-5 py-3 text-gray-500 text-xs"><?= clean($user['city_name']??'') ?>، <?= clean($user['country_name']??'-') ?></td>
                            <td class="px-5 py-3"><?php $s=$user['status']; $sl=['active'=>'مفعّل','suspended'=>'موقوف','pending'=>'معلّق']; $sc=['active'=>'green','suspended'=>'red','pending'=>'yellow']; ?><span class="bg-<?= $sc[$s] ?>-100 text-<?= $sc[$s] ?>-700 px-2 py-1 rounded-lg text-xs"><?= $sl[$s] ?? $s ?></span></td>
                            <td class="px-5 py-3">
                                <div class="flex gap-1 flex-wrap">
                                    <?php if($user['status']==='pending'): ?>
                                    <a href="admin.php?section=users&action=approve_user&id=<?= $user['id'] ?>" class="bg-green-500 text-white px-2 py-1 rounded text-xs hover:bg-green-600" onclick="return confirm('تفعيل هذا الحساب؟')"><i class="fas fa-check"></i> تفعيل</a>
                                    <?php endif; ?>
                                    <?php if($user['status']==='active'): ?>
                                    <a href="admin.php?section=users&action=suspend_user&id=<?= $user['id'] ?>" class="bg-red-500 text-white px-2 py-1 rounded text-xs hover:bg-red-600" onclick="return confirm('إيقاف هذا الحساب؟')"><i class="fas fa-ban"></i> إيقاف</a>
                                    <?php elseif($user['status']==='suspended'): ?>
                                    <a href="admin.php?section=users&action=activate_user&id=<?= $user['id'] ?>" class="bg-green-500 text-white px-2 py-1 rounded text-xs hover:bg-green-600" onclick="return confirm('تنشيط هذا الحساب؟')"><i class="fas fa-check"></i> تنشيط</a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <?php elseif ($section === 'projects'): ?>
        <h2 class="text-2xl font-bold text-gray-800 mb-6">🏗️ إدارة المشاريع</h2>
        
        <?php
        $all_projects = $pdo->query("SELECT p.*, u.name as employer_name, (SELECT COUNT(*) FROM bids WHERE project_id = p.id) as bids_count FROM projects p JOIN users u ON p.employer_id = u.id ORDER BY CASE WHEN p.status = 'pending' THEN 0 ELSE 1 END, p.created_at DESC")->fetchAll();
        ?>
        
        <div class="table-container">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr><th class="text-right px-5 py-3 font-medium text-gray-500">#</th><th class="text-right px-5 py-3 font-medium text-gray-500">العنوان</th><th class="text-right px-5 py-3 font-medium text-gray-500">صاحب العمل</th><th class="text-right px-5 py-3 font-medium text-gray-500">العطاءات</th><th class="text-right px-5 py-3 font-medium text-gray-500">الحالة</th><th class="text-right px-5 py-3 font-medium text-gray-500">إجراءات</th></tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <?php foreach ($all_projects as $i => $proj): ?>
                        <tr class="hover:bg-gray-50 <?= $proj['status'] === 'pending' ? 'bg-yellow-50' : '' ?>">
                            <td class="px-5 py-3 text-gray-400"><?= $i+1 ?></td>
                            <td class="px-5 py-3 font-medium max-w-xs truncate"><?= clean($proj['title']) ?></td>
                            <td class="px-5 py-3 text-gray-500"><?= clean($proj['employer_name']) ?></td>
                            <td class="px-5 py-3 text-center"><?= $proj['bids_count'] ?></td>
                            <td class="px-5 py-3"><?php $sl=['pending'=>'معلّق','open'=>'مفتوح','in_progress'=>'قيد التنفيذ','completed'=>'مكتمل','cancelled'=>'ملغي']; $sc=['pending'=>'yellow','open'=>'green','in_progress'=>'amber','completed'=>'blue','cancelled'=>'red']; $s=$proj['status']; ?><span class="bg-<?= $sc[$s] ?>-100 text-<?= $sc[$s] ?>-700 px-2 py-1 rounded-lg text-xs"><?= $sl[$s] ?? $s ?></span></td>
                            <td class="px-5 py-3">
                                <div class="flex gap-1 flex-wrap">
                                    <?php if($proj['status']==='pending'): ?>
                                    <a href="admin.php?section=projects&action=approve_project&id=<?= $proj['id'] ?>" class="bg-green-500 text-white px-2 py-1 rounded text-xs hover:bg-green-600" onclick="return confirm('الموافقة على هذا المشروع؟ سيتم إرسال تنبيهات للمقاولين.')"><i class="fas fa-check"></i> موافقة</a>
                                    <a href="admin.php?section=projects&action=reject_project&id=<?= $proj['id'] ?>" class="bg-red-500 text-white px-2 py-1 rounded text-xs hover:bg-red-600" onclick="return confirm('رفض هذا المشروع؟')"><i class="fas fa-times"></i> رفض</a>
                                    <?php endif; ?>
                                    <a href="<?= SITE_URL ?>/project_detail.php?id=<?= $proj['id'] ?>" class="bg-blue-100 text-blue-700 px-2 py-1 rounded text-xs hover:bg-blue-200"><i class="fas fa-eye"></i></a>
                                    <a href="admin.php?section=projects&action=delete_project&id=<?= $proj['id'] ?>" class="bg-red-100 text-red-700 px-2 py-1 rounded text-xs hover:bg-red-200" onclick="return confirm('حذف هذا المشروع نهائياً؟')"><i class="fas fa-trash"></i></a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <?php elseif ($section === 'shops_list'): ?>
        <h2 class="text-2xl font-bold text-gray-800 mb-6">🏪 إدارة المحلات</h2>
        
        <?php
        $all_shops = $pdo->query("SELECT s.*, u.name as owner_name, (SELECT COUNT(*) FROM products p WHERE p.shop_id = s.id) as products_count FROM shops s JOIN users u ON s.user_id = u.id ORDER BY s.created_at DESC")->fetchAll();
        ?>
        
        <div class="table-container">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr><th class="text-right px-5 py-3 font-medium text-gray-500">#</th><th class="text-right px-5 py-3 font-medium text-gray-500">اسم المحل</th><th class="text-right px-5 py-3 font-medium text-gray-500">المالك</th><th class="text-right px-5 py-3 font-medium text-gray-500">المنتجات</th><th class="text-right px-5 py-3 font-medium text-gray-500">الحالة</th><th class="text-right px-5 py-3 font-medium text-gray-500">إجراءات</th></tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <?php foreach ($all_shops as $i => $shop): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-5 py-3 text-gray-400"><?= $i+1 ?></td>
                            <td class="px-5 py-3 font-medium"><?= clean($shop['shop_name']) ?></td>
                            <td class="px-5 py-3 text-gray-500"><?= clean($shop['owner_name']) ?></td>
                            <td class="px-5 py-3 text-center"><?= $shop['products_count'] ?></td>
                            <td class="px-5 py-3"><span class="bg-<?= $shop['status'] === 'active' ? 'green' : 'red' ?>-100 text-<?= $shop['status'] === 'active' ? 'green' : 'red' ?>-700 px-2 py-1 rounded-lg text-xs"><?= $shop['status'] === 'active' ? 'نشط' : 'موقوف' ?></span></td>
                            <td class="px-5 py-3"><a href="<?= SITE_URL ?>/shop_detail.php?id=<?= $shop['id'] ?>" class="bg-blue-100 text-blue-700 px-2 py-1 rounded text-xs hover:bg-blue-200"><i class="fas fa-eye"></i></a></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <?php elseif ($section === 'products'): ?>
        <h2 class="text-2xl font-bold text-gray-800 mb-6">📦 إدارة المنتجات</h2>
        
        <?php
        $all_products = $pdo->query("SELECT p.*, s.shop_name FROM products p JOIN shops s ON p.shop_id = s.id ORDER BY p.created_at DESC")->fetchAll();
        ?>
        
        <div class="table-container">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr><th class="text-right px-5 py-3 font-medium text-gray-500">#</th><th class="text-right px-5 py-3 font-medium text-gray-500">المنتج</th><th class="text-right px-5 py-3 font-medium text-gray-500">المحل</th><th class="text-right px-5 py-3 font-medium text-gray-500">السعر</th><th class="text-right px-5 py-3 font-medium text-gray-500">متوفر</th><th class="text-right px-5 py-3 font-medium text-gray-500">إجراءات</th></tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <?php foreach ($all_products as $i => $prod): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-5 py-3 text-gray-400"><?= $i+1 ?></td>
                            <td class="px-5 py-3 font-medium"><?= clean($prod['name']) ?></td>
                            <td class="px-5 py-3 text-gray-500"><?= clean($prod['shop_name']) ?></td>
                            <td class="px-5 py-3 text-gray-700"><?= $prod['price'] ? number_format($prod['price']) : '-' ?></td>
                            <td class="px-5 py-3"><?= $prod['is_available'] ? '<span class="text-green-600"><i class="fas fa-check-circle"></i></span>' : '<span class="text-red-500"><i class="fas fa-times-circle"></i></span>' ?></td>
                            <td class="px-5 py-3"><a href="admin.php?section=products&action=delete_product&id=<?= $prod['id'] ?>" class="bg-red-100 text-red-700 px-2 py-1 rounded text-xs hover:bg-red-200" onclick="return confirm('حذف هذا المنتج نهائياً؟')"><i class="fas fa-trash"></i> حذف</a></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <?php elseif ($section === 'bids_list'): ?>
        <!-- إدارة العطاءات -->
        <div class="flex items-center justify-between mb-6 flex-wrap gap-2">
            <h2 class="text-2xl font-bold text-gray-800">📋 إدارة العطاءات</h2>
            <div class="filter-buttons">
                <a href="admin.php?section=bids_list&filter=pending" class="bg-yellow-100 text-yellow-700 hover:bg-yellow-200">المعلقة</a>
                <a href="admin.php?section=bids_list&filter=admin_approved" class="bg-blue-100 text-blue-700 hover:bg-blue-200">موافقة إدارية</a>
                <a href="admin.php?section=bids_list&filter=accepted" class="bg-green-100 text-green-700 hover:bg-green-200">المقبولة</a>
                <a href="admin.php?section=bids_list&filter=rejected" class="bg-red-100 text-red-700 hover:bg-red-200">المرفوضة</a>
                <a href="admin.php?section=bids_list" class="bg-gray-100 text-gray-700 hover:bg-gray-200">الكل</a>
            </div>
        </div>
        
        <?php
        $filter = clean($_GET['filter'] ?? '');
        $query = "
            SELECT b.*, p.title as project_title, p.id as project_id, u.name as contractor_name,
                   b.admin_approved
            FROM bids b
            JOIN projects p ON b.project_id = p.id
            JOIN users u ON b.contractor_id = u.id
        ";
        
        if ($filter === 'admin_approved') {
            $query .= " WHERE b.admin_approved = 1 AND b.status = 'pending'";
            $stmt = $pdo->query($query . " ORDER BY b.created_at DESC LIMIT 100");
        } elseif ($filter && in_array($filter, ['pending', 'accepted', 'rejected'])) {
            $query .= " WHERE b.status = ?";
            $stmt = $pdo->prepare($query . " ORDER BY b.created_at DESC LIMIT 100");
            $stmt->execute([$filter]);
        } else {
            $stmt = $pdo->query($query . " ORDER BY b.created_at DESC LIMIT 100");
        }
        $all_bids = $stmt->fetchAll();
        ?>
        
        <div class="table-container">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr><th class="text-right px-5 py-3 font-medium text-gray-500">#</th><th class="text-right px-5 py-3 font-medium text-gray-500">المشروع</th><th class="text-right px-5 py-3 font-medium text-gray-500">المقاول</th><th class="text-right px-5 py-3 font-medium text-gray-500">المبلغ</th><th class="text-right px-5 py-3 font-medium text-gray-500">المدة</th><th class="text-right px-5 py-3 font-medium text-gray-500">الحالة</th><th class="text-right px-5 py-3 font-medium text-gray-500">التاريخ</th><th class="text-right px-5 py-3 font-medium text-gray-500">إجراءات</th></tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <?php if (empty($all_bids)): ?>
                        <tr><td colspan="8" class="px-5 py-8 text-center text-gray-400">لا توجد عطاءات</td></tr>
                        <?php else: ?>
                            <?php foreach ($all_bids as $i => $bid): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-5 py-3 text-gray-400"><?= $i + 1 ?></td>
                                <td class="px-5 py-3 font-medium max-w-xs truncate"><a href="<?= SITE_URL ?>/project_detail.php?id=<?= $bid['project_id'] ?>" class="text-primary-600 hover:text-primary-700 hover:underline"><?= clean($bid['project_title']) ?></a></td>
                                <td class="px-5 py-3 text-gray-500"><?= clean($bid['contractor_name']) ?></td>
                                <td class="px-5 py-3 text-gray-700 font-medium"><?= number_format($bid['amount']) ?></td>
                                <td class="px-5 py-3 text-gray-500"><?= $bid['duration_days'] ? $bid['duration_days'] . ' يوم' : '-' ?></td>
                                <td class="px-5 py-3">
                                    <?php
                                    $bs = $bid['status'];
                                    $bsl = ['pending' => 'معلّق', 'accepted' => 'مقبول', 'rejected' => 'مرفوض'];
                                    $bsc = ['pending' => 'yellow', 'accepted' => 'green', 'rejected' => 'red'];
                                    $status_text = $bsl[$bs] ?? $bs;
                                    $status_color = $bsc[$bs];
                                    if ($bs === 'pending' && $bid['admin_approved'] == 1) {
                                        $status_text = '✅ موافقة إدارية';
                                        $status_color = 'blue';
                                    }
                                    ?>
                                    <span class="bg-<?= $status_color ?>-100 text-<?= $status_color ?>-700 px-2 py-1 rounded-lg text-xs"><?= $status_text ?></span>
                                </td>
                                <td class="px-5 py-3 text-gray-400 text-xs" dir="ltr"><?= date('Y/m/d H:i', strtotime($bid['created_at'])) ?></td>
                                <td class="px-5 py-3">
                                    <div class="flex gap-1 flex-wrap">
                                        <?php if ($bid['status'] === 'pending' && $bid['admin_approved'] == 0): ?>
                                            <a href="admin.php?section=bids_list&action=admin_approve_bid&id=<?= $bid['id'] ?>" 
                                               class="bg-blue-500 text-white px-2 py-1 rounded text-xs hover:bg-blue-600"
                                               onclick="return confirm('الموافقة الإدارية على هذا العطاء مبدئياً؟ سيتم إشعار صاحب العمل لاتخاذ القرار النهائي.')">
                                                <i class="fas fa-check-circle"></i> موافقة إدارية
                                            </a>
                                        <?php elseif ($bid['status'] === 'pending' && $bid['admin_approved'] == 1): ?>
                                            <span class="text-blue-600 text-xs">✓ بانتظار موافقة صاحب العمل</span>
                                        <?php endif; ?>
                                        
                                        <?php if ($bid['status'] === 'pending'): ?>
                                            <a href="admin.php?section=bids_list&action=reject_bid&id=<?= $bid['id'] ?>" 
                                               class="bg-red-500 text-white px-2 py-1 rounded text-xs hover:bg-red-600"
                                               onclick="return confirm('رفض هذا العطاء نهائياً؟')">
                                                <i class="fas fa-times"></i> رفض
                                            </a>
                                        <?php elseif ($bid['status'] === 'accepted' || $bid['status'] === 'rejected'): ?>
                                            <a href="admin.php?section=bids_list&action=reset_bid&id=<?= $bid['id'] ?>" 
                                               class="bg-yellow-100 text-yellow-700 px-2 py-1 rounded text-xs hover:bg-yellow-200"
                                               onclick="return confirm('إعادة العطاء إلى معلق؟')">
                                                <i class="fas fa-undo"></i> إعادة
                                            </a>
                                        <?php endif; ?>
                                        
                                        <a href="admin.php?section=bids_list&action=delete_bid&id=<?= $bid['id'] ?>" 
                                           class="bg-gray-200 text-gray-700 px-2 py-1 rounded text-xs hover:bg-red-100 hover:text-red-700"
                                           onclick="return confirm('حذف هذا العطاء نهائياً؟')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <?php elseif ($section === 'workspaces'): ?>
        <h2 class="text-2xl font-bold text-gray-800 mb-6">🚪 غرف العمل</h2>
        
        <?php
        $workspaces = $pdo->query("
            SELECT w.*, p.title as project_title, 
                   e.name as employer_name, c.name as contractor_name
            FROM project_workspace w
            JOIN projects p ON w.project_id = p.id
            JOIN users e ON w.employer_id = e.id
            JOIN users c ON w.contractor_id = c.id
            ORDER BY w.created_at DESC
        ")->fetchAll();
        ?>
        
        <div class="table-container">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr><th class="text-right px-5 py-3 font-medium text-gray-500">#</th><th class="text-right px-5 py-3 font-medium text-gray-500">المشروع</th><th class="text-right px-5 py-3 font-medium text-gray-500">صاحب العمل</th><th class="text-right px-5 py-3 font-medium text-gray-500">المقاول</th><th class="text-right px-5 py-3 font-medium text-gray-500">الحالة</th><th class="text-right px-5 py-3 font-medium text-gray-500">تاريخ الإنشاء</th><th class="text-right px-5 py-3 font-medium text-gray-500">إجراءات</th></tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <?php if (empty($workspaces)): ?>
                        <tr><td colspan="7" class="px-5 py-8 text-center text-gray-400">لا توجد غرف عمل</td></tr>
                        <?php else: ?>
                            <?php foreach ($workspaces as $i => $w): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-5 py-3 text-gray-400"><?= $i+1 ?></td>
                                <td class="px-5 py-3 font-medium"><?= clean($w['project_title']) ?></td>
                                <td class="px-5 py-3 text-gray-500"><?= clean($w['employer_name']) ?></td>
                                <td class="px-5 py-3 text-gray-500"><?= clean($w['contractor_name']) ?></td>
                                <td class="px-5 py-3"><?php $ws=$w['status']; $wsl=['active'=>'🟢 نشط','completed'=>'🔵 مكتمل','on_hold'=>'🟡 معلق']; ?><span class="text-xs"><?= $wsl[$ws] ?? $ws ?></span></td>
                                <td class="px-5 py-3 text-gray-400 text-xs"><?= date('Y/m/d', strtotime($w['created_at'])) ?></td>
                                <td class="px-5 py-3">
                                    <div class="flex gap-1 flex-wrap">
                                        <a href="<?= SITE_URL ?>/project_workspace.php?id=<?= $w['id'] ?>" class="bg-blue-100 text-blue-700 px-2 py-1 rounded text-xs hover:bg-blue-200"><i class="fas fa-eye"></i></a>
                                        <?php if ($w['status'] !== 'completed'): ?>
                                        <a href="admin.php?section=workspaces&action=close_workspace&id=<?= $w['id'] ?>" class="bg-green-100 text-green-700 px-2 py-1 rounded text-xs hover:bg-green-200" onclick="return confirm('إغلاق هذه الغرفة؟')"><i class="fas fa-check"></i> إغلاق</a>
                                        <?php endif; ?>
                                        <a href="admin.php?section=workspaces&action=delete_workspace&id=<?= $w['id'] ?>" class="bg-red-100 text-red-700 px-2 py-1 rounded text-xs hover:bg-red-200" onclick="return confirm('حذف هذه الغرفة نهائياً؟')"><i class="fas fa-trash"></i></a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <?php elseif ($section === 'logs'): ?>
        <h2 class="text-2xl font-bold text-gray-800 mb-6">📜 سجل العمليات</h2>
        
        <?php
        $logs = $pdo->query("SELECT al.*, u.name as admin_name FROM admin_logs al JOIN users u ON al.admin_id = u.id ORDER BY al.created_at DESC LIMIT 100")->fetchAll();
        ?>
        
        <div class="table-container">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr><th class="text-right px-5 py-3 font-medium text-gray-500">#</th><th class="text-right px-5 py-3 font-medium text-gray-500">المشرف</th><th class="text-right px-5 py-3 font-medium text-gray-500">الإجراء</th><th class="text-right px-5 py-3 font-medium text-gray-500">التفاصيل</th><th class="text-right px-5 py-3 font-medium text-gray-500">التاريخ</th></tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <?php if(empty($logs)): ?><tr><td colspan="5" class="px-5 py-8 text-center text-gray-400">لا توجد سجلات بعد</td></tr><?php else: foreach($logs as $i=>$log): ?><tr class="hover:bg-gray-50"><td class="px-5 py-3 text-gray-400"><?= $i+1 ?></td><td class="px-5 py-3 font-medium"><?= clean($log['admin_name']) ?></td><td class="px-5 py-3"><?= clean($log['action']) ?></td><td class="px-5 py-3 text-gray-500 text-xs"><?= clean($log['details']??'-') ?></td><td class="px-5 py-3 text-gray-400 text-xs"><?= date('Y/m/d H:i', strtotime($log['created_at'])) ?></td></tr><?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <?php endif; ?>
        
    </main>
</div>

<script>
function toggleAdminSidebar() {
    const sidebar = document.getElementById('adminSidebar');
    const content = document.getElementById('adminContent');
    
    if (sidebar.style.display === 'none' || sidebar.style.display === '') {
        sidebar.style.display = 'block';
        sidebar.style.position = 'fixed';
        sidebar.style.top = '0';
        sidebar.style.left = '0';
        sidebar.style.bottom = '0';
        sidebar.style.zIndex = '1000';
        sidebar.style.width = '280px';
        content.style.marginLeft = '280px';
    } else {
        sidebar.style.display = 'none';
        content.style.marginLeft = '0';
    }
}

// إغلاق القائمة الجانبية عند النقر خارجها (للجوال)
document.addEventListener('click', function(event) {
    const sidebar = document.getElementById('adminSidebar');
    const toggle = document.getElementById('adminSidebarToggle');
    
    if (window.innerWidth <= 768) {
        if (sidebar && sidebar.style.display !== 'none') {
            if (!sidebar.contains(event.target) && !toggle.contains(event.target)) {
                sidebar.style.display = 'none';
                document.getElementById('adminContent').style.marginLeft = '0';
            }
        }
    }
});

// عند تغيير حجم النافذة، إعادة ضبط القائمة
window.addEventListener('resize', function() {
    const sidebar = document.getElementById('adminSidebar');
    if (window.innerWidth > 768) {
        sidebar.style.display = 'block';
        sidebar.style.position = 'sticky';
        sidebar.style.width = '250px';
        document.getElementById('adminContent').style.marginLeft = '0';
    } else {
        sidebar.style.display = 'none';
        sidebar.style.position = 'fixed';
        sidebar.style.width = '280px';
    }
});

// تهيئة القائمة حسب حجم الشاشة
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('adminSidebar');
    if (window.innerWidth > 768) {
        sidebar.style.display = 'block';
        sidebar.style.position = 'sticky';
        sidebar.style.width = '250px';
    } else {
        sidebar.style.display = 'none';
        sidebar.style.position = 'fixed';
        sidebar.style.width = '280px';
    }
});
</script>

<?php include 'includes/footer.php'; ?>