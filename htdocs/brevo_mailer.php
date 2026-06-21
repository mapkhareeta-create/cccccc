<?php
// ============================================
// تحميل الإعدادات
// ============================================
require_once __DIR__ . '/config.php';

// ============================================
// التحقق من وجود المفتاح
// ============================================
if (!defined('BREVO_API_KEY') || empty(BREVO_API_KEY)) {
    die("❌ BREVO_API_KEY غير موجود في config.php");
}

echo "<h1>🧪 اختبار Brevo API</h1>";
echo "<hr>";

echo "<h3>📋 الإعدادات:</h3>";
echo "<ul>";
echo "<li><strong>API Key:</strong> " . BREVO_API_KEY . "</li>";
echo "<li><strong>From:</strong> info@omranhub.com</li>";
echo "<li><strong>To:</strong> albdour.abed@gmail.com</li>";
echo "</ul>";
echo "<hr>";

echo "<h3>📧 جاري الإرسال...</h3>";

// ============================================
// التحقق من وجود cURL
// ============================================
if (!function_exists('curl_init')) {
    die("❌ cURL غير مثبت على السيرفر!");
}

// ============================================
// إرسال البريد
// ============================================

$to_email = 'albdour.abed@gmail.com';
$to_name = 'Abed';

$data = [
    'sender' => [
        'name' => 'مزاد البناء', 
        'email' => 'info@omranhub.com'
    ],
    'to' => [
        ['email' => $to_email, 'name' => $to_name]
    ],
    'subject' => '🧪 اختبار Brevo API - مزاد البناء',
    'htmlContent' => '
        <h2>✅ تم إعداد Brevo API بنجاح!</h2>
        <p>هذه رسالة اختبار من موقع <strong>مزاد البناء</strong></p>
        <p>تم الإرسال في: ' . date('Y-m-d H:i:s') . '</p>
        <hr>
        <p style="color: #64748b; font-size: 14px;">API Key: ' . BREVO_API_KEY . '</p>
    ',
];

echo "<pre>";
echo "📤 جاري إرسال الطلب إلى Brevo...\n";

$ch = curl_init('https://api.brevo.com/v3/smtp/email');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'api-key: ' . BREVO_API_KEY
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if (curl_error($ch)) {
    echo "❌ خطأ في cURL: " . curl_error($ch) . "\n";
}

curl_close($ch);

echo "📥 استجابة السيرفر: HTTP $http_code\n";
echo "📄 الرد: " . $response . "\n";
echo "</pre>";

// ============================================
// عرض النتيجة
// ============================================

if ($http_code == 201) {
    echo '<div style="background: #d1fae5; border: 2px solid #10b981; padding: 20px; border-radius: 12px; margin: 20px 0;">';
    echo '✅ <strong style="color: #065f46; font-size: 18px;">تم إرسال البريد بنجاح!</strong>';
    echo '<p style="color: #065f46; margin-top: 10px;">تم إرسال رسالة اختبار إلى: <strong>albdour.abed@gmail.com</strong></p>';
    echo '<p style="color: #065f46; font-size: 14px;">⏱️ الوقت: ' . date('Y-m-d H:i:s') . '</p>';
    echo '</div>';
} else {
    echo '<div style="background: #fee2e2; border: 2px solid #ef4444; padding: 20px; border-radius: 12px; margin: 20px 0;">';
    echo '❌ <strong style="color: #991b1b; font-size: 18px;">فشل إرسال البريد!</strong>';
    echo '<p style="color: #991b1b; margin-top: 10px;"><strong>رمز الخطأ:</strong> HTTP ' . $http_code . '</p>';
    echo '<p style="color: #991b1b;"><strong>الرد:</strong> ' . htmlspecialchars($response) . '</p>';
    
    if ($http_code == 401) {
        echo '<p style="color: #991b1b;">🔑 <strong>المفتاح غير صالح!</strong> تأكد من نسخه بشكل صحيح.</p>';
    } elseif ($http_code == 403) {
        echo '<p style="color: #991b1b;">🚫 <strong>ليس لديك صلاحية!</strong> تأكد من أن المفتاح مفعل.</p>';
    }
    echo '</div>';
}

echo "<hr>";
echo '<p><a href="' . SITE_URL . '" style="color: #2563eb; text-decoration: none;">🏠 العودة إلى الرئيسية</a></p>';
?>