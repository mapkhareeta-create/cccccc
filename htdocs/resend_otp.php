<?php
require_once 'config.php';

// ============================================
// دالة sendOTP - نفس الموجودة في register.php
// ============================================
function sendOTP($data) {
    $url = SITE_URL . '/send_otp.php';
    
    // استخدام cURL
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/x-www-form-urlencoded',
        'Accept: application/json'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    // تسجيل معلومات الطلب للتشخيص
    error_log("=== sendOTP Debug ===");
    error_log("URL: " . $url);
    error_log("HTTP Code: " . $httpCode);
    error_log("Response Length: " . strlen($response));
    error_log("cURL Error: " . $error);
    error_log("=====================");
    
    if ($error) {
        return [
            'status' => 'error', 
            'message' => 'خطأ في الاتصال: ' . $error
        ];
    }
    
    if ($httpCode != 200) {
        return [
            'status' => 'error', 
            'message' => 'فشل الاتصال بالخادم (HTTP ' . $httpCode . ')'
        ];
    }
    
    $response = trim($response);
    $result = json_decode($response, true);
    
    if (json_last_error() === JSON_ERROR_NONE) {
        return $result;
    }
    
    return [
        'status' => 'error', 
        'message' => 'استجابة غير صالحة من الخادم',
        'debug' => 'JSON Error: ' . json_last_error_msg(),
        'raw' => substr($response, 0, 200)
    ];
}

// ============================================
// التحقق من وجود بيانات التسجيل المؤقتة
// ============================================
if (!isset($_SESSION['temp_registration']) || !isset($_SESSION['temp_email'])) {
    $_SESSION['error'] = 'لا توجد بيانات تسجيل لإعادة إرسال الرمز';
    redirect('register.php');
}

$temp_data = $_SESSION['temp_registration'];
$email = $_SESSION['temp_email'];
$name = $_SESSION['temp_name'];

// ============================================
// إنشاء رمز OTP جديد
// ============================================
$otp_code = rand(100000, 999999);
$otp_expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));

// تحديث بيانات الجلسة
$_SESSION['temp_registration']['otp'] = $otp_code;
$_SESSION['temp_registration']['otp_expiry'] = $otp_expires;

// ============================================
// إرسال OTP جديد
// ============================================
$otpData = [
    'email' => $email,
    'name' => $name,
    'otp' => $otp_code
];

$otpResponse = sendOTP($otpData);

// ============================================
// معالجة الاستجابة
// ============================================
if (isset($otpResponse['status']) && $otpResponse['status'] === 'success') {
    $_SESSION['success'] = '✅ تم إعادة إرسال رمز التحقق إلى بريدك الإلكتروني';
} else {
    $error_msg = $otpResponse['message'] ?? 'حدث خطأ أثناء إعادة إرسال الرمز';
    if (isset($otpResponse['debug'])) {
        $error_msg .= ' (Debug: ' . $otpResponse['debug'] . ')';
    }
    $_SESSION['error'] = $error_msg;
}

// التوجيه إلى صفحة التحقق
redirect('verify_otp.php');
?>