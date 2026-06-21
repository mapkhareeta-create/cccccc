<?php
    header('Content-Type: text/html; charset=utf-8');
// ============================================
// إعدادات قاعدة البيانات والنظام
// ============================================

// ============================================
// ✅ إعدادات الجلسة - مع التحقق من عدم وجود جلسة نشطة
// ============================================

// التحقق من حالة الجلسة قبل تغيير الإعدادات
if (session_status() === PHP_SESSION_NONE) {
    // فقط غير الإعدادات إذا لم تكن الجلسة قد بدأت
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_secure', 0); //设为1 إذا كان الموقع HTTPS
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.gc_maxlifetime', 86400); // 24 ساعة
    ini_set('session.cookie_lifetime', 86400); // 24 ساعة
    
    // بدء الجلسة
    session_start();
} else {
    // الجلسة بدأت بالفعل من مكان آخر
    // لا نحتاج لتغيير الإعدادات أو بدء الجلسة مرة أخرى
}

// ============================================
// إعدادات قاعدة البيانات (يجب تحديثها لمعلومات استضافة cheapname)
// ============================================
// ⚠️ قم بتعديل هذه القيم بحسب قاعدة البيانات التي ستنشئها على cheapname
define('DB_HOST', 'localhost');                  // غالباً localhost أو عنوان السيرفر
define('DB_NAME', '1270454_6794c85b3dbcd64d14b61ac7e54a60df');        // استبدل باسم قاعدة البيانات الجديدة
define('DB_USER', 'user-1524568');              // استبدل باسم المستخدم
define('DB_PASS', 'wVRovG3LVHCGzKrhiPPN');               // استبدل بكلمة المرور

// ============================================
// إعدادات الموقع (تم التحديث للرابط الجديد)
// ============================================
define('SITE_NAME', 'omran');
define('SITE_URL', 'https://www.omranhub.com');  // تم التحديث
define('UPLOAD_PATH', __DIR__ . '/assets/uploads/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB

// ============================================
// الاتصال بقاعدة البيانات
// ============================================
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
    $pdo->exec("SET NAMES utf8mb4");
    $pdo->exec("SET time_zone = '+03:00'"); // توقيت السعودية
} catch (PDOException $e) {
    die("خطأ في الاتصال بقاعدة البيانات: " . $e->getMessage());
}

// ============================================
// دوال مساعدة أساسية
// ============================================

/**
 * التحقق من تسجيل الدخول
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0;
}

/**
 * الحصول على نوع المستخدم الحالي
 */
function getUserType() {
    return $_SESSION['user_type'] ?? null;
}

/**
 * التحقق من أن المستخدم أدمن
 */
function isAdmin() {
    return isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin';
}

/**
 * التوجيه إلى صفحة أخرى
 */
function redirect($url) {
    // تنظيف الرابط
    $url = ltrim($url, '/');
    $full_url = SITE_URL . '/' . $url;
    
    // التأكد من عدم وجود أي إخراج قبل التوجيه
    if (headers_sent()) {
        echo "<script>window.location.href='$full_url';</script>";
        echo "<noscript><meta http-equiv='refresh' content='0;url=$full_url'></noscript>";
        exit();
    }
    
    header("Location: " . $full_url);
    exit();
}

/**
 * تنظيف النصوص من الـ HTML Special Characters
 */
function clean($data) {
    if (is_null($data)) return '';
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * رفع الصور العامة (للمشاريع والمنتجات)
 */
function uploadImage($file, $folder = 'projects') {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) return false;
    if ($file['size'] > MAX_FILE_SIZE) return false;
    
    $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($file['type'], $allowed)) return false;
    
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid() . '_' . time() . '.' . $ext;
    $uploadDir = UPLOAD_PATH . $folder . '/';
    
    if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0755, true);
    }
    if (!is_dir($uploadDir)) return false;
    
    if (move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
        return "assets/uploads/$folder/" . $filename;
    }
    return false;
}

/**
 * رفع صورة البروفايل (دالة خاصة)
 */
function uploadProfileImage($file, $user_id) {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['status' => 'error', 'message' => 'لم يتم اختيار ملف'];
    }
    
    if ($file['size'] > MAX_FILE_SIZE) {
        return ['status' => 'error', 'message' => 'حجم الصورة كبير جداً (الحد الأقصى 5MB)'];
    }
    
    $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($file['type'], $allowed)) {
        return ['status' => 'error', 'message' => 'نوع الملف غير مسموح (JPG, PNG, GIF, WEBP فقط)'];
    }
    
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'user_' . $user_id . '_' . time() . '.' . $ext;
    $uploadDir = UPLOAD_PATH . 'avatars/';
    
    if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0755, true);
    }
    
    if (!is_dir($uploadDir)) {
        return ['status' => 'error', 'message' => 'لا يمكن إنشاء مجلد الصور'];
    }
    
    if (move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
        // حذف الصورة القديمة إذا كانت موجودة وليست الافتراضية
        global $pdo;
        $stmt = $pdo->prepare("SELECT avatar FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $old = $stmt->fetch();
        if ($old && $old['avatar'] && $old['avatar'] !== 'default-avatar.png' && file_exists($old['avatar'])) {
            @unlink($old['avatar']);
        }
        
        return ['status' => 'success', 'path' => 'assets/uploads/avatars/' . $filename];
    }
    
    return ['status' => 'error', 'message' => 'حدث خطأ أثناء رفع الصورة'];
}

/**
 * جلب بيانات المستخدم (نسخة مبسطة ومضمونة)
 */
function getUser($id) {
    global $pdo;
    
    if (!$id || $id <= 0) {
        return false;
    }
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $user = $stmt->fetch();
    
    if (!$user) {
        return false;
    }
    
    // جلب اسم الدولة والمدينة
    if ($user['country_id']) {
        $stmt = $pdo->prepare("SELECT name_ar, flag FROM countries WHERE id = ?");
        $stmt->execute([$user['country_id']]);
        $country = $stmt->fetch();
        $user['country_name'] = $country['name_ar'] ?? null;
        $user['country_flag'] = $country['flag'] ?? null;
    }
    
    if ($user['city_id']) {
        $stmt = $pdo->prepare("SELECT name_ar FROM cities WHERE id = ?");
        $stmt->execute([$user['city_id']]);
        $city = $stmt->fetch();
        $user['city_name'] = $city['name_ar'] ?? null;
    }
    
    return $user;
}

/**
 * جلب بيانات العملة حسب الدولة
 */
function getCurrency($country_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT currency_ar, currency_code FROM countries WHERE id = ?");
    $stmt->execute([$country_id]);
    return $stmt->fetch();
}

/**
 * جلب قائمة الدول
 */
function getCountries() {
    global $pdo;
    $stmt = $pdo->query("SELECT * FROM countries ORDER BY name_ar");
    return $stmt->fetchAll();
}

/**
 * جلب قائمة المدن حسب الدولة
 */
function getCities($country_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM cities WHERE country_id = ? ORDER BY name_ar");
    $stmt->execute([$country_id]);
    return $stmt->fetchAll();
}

/**
 * إنشاء إشعار جديد
 */
function createNotification($user_id, $title, $message, $type = 'info', $link = '') {
    global $pdo;
    $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type, link, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
    $stmt->execute([$user_id, $title, $message, $type, $link]);
}

/**
 * جلب تصنيفات المشاريع
 */
function getProjectCategories() {
    return [
        'بناء' => '🏗️', 'تشطيبات' => '🎨', 'كهرباء' => '⚡', 'سباكة' => '🔧',
        'تكييف' => '❄️', 'دهان' => '🖌️', 'أرضيات' => '🧱', 'نجارة' => '🪵',
        'جبس بورد' => '🏠', 'زجاج' => '🪟', 'عزل' => '🛡️', 'تنسيق حدائق' => '🌿',
        'نظافة' => '🧹', 'أبواب ونوافذ' => '🚪', 'مقاول عام' => '👷'
    ];
}

/**
 * جلب تصنيفات المنتجات
 */
function getProductCategories() {
    return [
        'أسمنت' => '🏗️', 'حديد' => '🔩', 'طوب' => '🧱', 'رمل وحصى' => '⛰️',
        'دهانات' => '🎨', 'مواد سباكة' => '🔧', 'مواد كهربائية' => '⚡',
        'أخشاب' => '🪵', 'بلاط وسيراميك' => '🏠', 'عزل' => '🛡️',
        'أدوات بناء' => '🔨', 'أبواب ونوافذ' => '🚪', 'أنابيب' => '🔩'
    ];
}

/**
 * تحويل الوقت إلى صيغة "منذ"
 */
function timeAgo($datetime) {
    if (empty($datetime)) return 'الآن';
    
    $now = new DateTime();
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);
    
    if ($diff->y > 0) return $diff->y . ' سنة';
    if ($diff->m > 0) return $diff->m . ' شهر';
    if ($diff->d > 0) return $diff->d . ' يوم';
    if ($diff->h > 0) return $diff->h . ' ساعة';
    if ($diff->i > 0) return $diff->i . ' دقيقة';
    return 'الآن';
}

/**
 * تنسيق الأرقام (K, M)
 */
function formatNumber($num) {
    if ($num >= 1000000) return number_format($num / 1000000, 1) . 'M';
    if ($num >= 1000) return number_format($num / 1000, 1) . 'K';
    return number_format($num);
}

// ============================================
// ✅ إعدادات البريد الإلكتروني (SMTP) - تم التحديث للبريد الجديد
// ============================================
define('SMTP_HOST', 'smtp.gmail.com');          // إذا كان البريد على Gmail، وإلا غيّر إلى SMTP الخاص بالاستضافة
define('SMTP_USER', 'info@omranhub.com');        // البريد الجديد
define('SMTP_PASS', 'P74u-nCcD-Xxxp-3rrC-nRvU-ZamJ'); // كلمة المرور الجديدة
define('SMTP_PORT', 587);
define('SMTP_SECURE', 'tls');
define('FROM_EMAIL', 'info@omranhub.com');
define('FROM_NAME', 'omran-hub');

// ============================================
// دوال إضافية لصفحة المقاولين
// ============================================

/**
 * جلب تخصصات المقاول
 */
function getContractorSpecializations($user_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT spec FROM contractor_specs WHERE user_id = ?");
    $stmt->execute([$user_id]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

/**
 * جلب جميع التخصصات
 */
function getAllSpecializations() {
    global $pdo;
    $stmt = $pdo->query("SELECT DISTINCT spec FROM contractor_specs ORDER BY spec");
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

/**
 * جلب المقاولين المميزين
 */
function getFeaturedContractors($limit = 6) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT u.*, c.name_ar as country_name, ci.name_ar as city_name
        FROM users u
        LEFT JOIN countries c ON u.country_id = c.id
        LEFT JOIN cities ci ON u.city_id = ci.id
        WHERE u.user_type = 'contractor' AND u.is_featured = 1
        ORDER BY u.rating DESC LIMIT ?
    ");
    $stmt->execute([$limit]);
    return $stmt->fetchAll();
}

/**
 * البحث عن المقاولين
 */
function searchContractors($filters = []) {
    global $pdo;
    $sql = "SELECT u.*, c.name_ar as country_name, ci.name_ar as city_name 
            FROM users u 
            LEFT JOIN countries c ON u.country_id = c.id 
            LEFT JOIN cities ci ON u.city_id = ci.id 
            WHERE u.user_type = 'contractor' AND (u.status = 'active' OR u.status IS NULL)";
    $params = [];
    
    if (!empty($filters['search'])) { 
        $sql .= " AND u.name LIKE ?"; 
        $params[] = "%{$filters['search']}%"; 
    }
    if (!empty($filters['specialization'])) { 
        $sql .= " AND EXISTS (SELECT 1 FROM contractor_specs WHERE user_id = u.id AND spec = ?)"; 
        $params[] = $filters['specialization']; 
    }
    if (!empty($filters['class']) && $filters['class'] != 'all') { 
        $sql .= " AND u.contractor_class = ?"; 
        $params[] = $filters['class']; 
    }
    if (!empty($filters['country_id']) && $filters['country_id'] > 0) { 
        $sql .= " AND u.country_id = ?"; 
        $params[] = $filters['country_id']; 
    }
    if (!empty($filters['city_id']) && $filters['city_id'] > 0) { 
        $sql .= " AND u.city_id = ?"; 
        $params[] = $filters['city_id']; 
    }
    if (!empty($filters['verified']) && $filters['verified'] == 1) { 
        $sql .= " AND u.verification_status = 'approved'"; 
    }
    if (!empty($filters['min_rating']) && $filters['min_rating'] > 0) { 
        $sql .= " AND u.rating >= ?"; 
        $params[] = $filters['min_rating']; 
    }
    
    $sql .= " ORDER BY u.is_featured DESC, u.rating DESC, u.created_at DESC LIMIT 30";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * جلب تصنيفات المقاولين
 */
function getContractorClasses() {
    return [
        'all' => ['name' => 'جميع التصنيفات', 'icon' => 'fa-chart-line', 'color' => 'gray'],
        'A' => ['name' => 'تصنيف أ (متقدم)', 'icon' => 'fa-crown', 'color' => 'green'],
        'B' => ['name' => 'تصنيف ب (جيد جداً)', 'icon' => 'fa-medal', 'color' => 'blue'],
        'C' => ['name' => 'تصنيف ج (متوسط)', 'icon' => 'fa-chart-line', 'color' => 'amber'],
        'D' => ['name' => 'تصنيف د (مبتدئ)', 'icon' => 'fa-seedling', 'color' => 'orange']
    ];
}

// ============================================
// ✅ دوال نظام تنبيهات المشاريع الجديدة
// ============================================

/**
 * جلب إعدادات التنبيهات للمقاول
 */
function getContractorAlertSettings($contractor_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM contractor_alerts WHERE contractor_id = ?");
    $stmt->execute([$contractor_id]);
    $settings = $stmt->fetch();
    
    if (!$settings) {
        // إنشاء إعدادات افتراضية
        $stmt = $pdo->prepare("INSERT INTO contractor_alerts (contractor_id, is_active, alert_sound, alert_email) VALUES (?, 1, 1, 1)");
        $stmt->execute([$contractor_id]);
        
        $stmt = $pdo->prepare("SELECT * FROM contractor_alerts WHERE contractor_id = ?");
        $stmt->execute([$contractor_id]);
        $settings = $stmt->fetch();
    }
    
    return $settings;
}

/**
 * تحديث إعدادات التنبيهات للمقاول
 */
function updateContractorAlertSettings($contractor_id, $data) {
    global $pdo;
    
    $is_active = isset($data['is_active']) ? (int)$data['is_active'] : 1;
    $alert_sound = isset($data['alert_sound']) ? (int)$data['alert_sound'] : 1;
    $alert_email = isset($data['alert_email']) ? (int)$data['alert_email'] : 1;
    
    $stmt = $pdo->prepare("
        UPDATE contractor_alerts 
        SET is_active = ?, alert_sound = ?, alert_email = ?, updated_at = NOW() 
        WHERE contractor_id = ?
    ");
    return $stmt->execute([$is_active, $alert_sound, $alert_email, $contractor_id]);
}

/**
 * جلب عدد التنبيهات غير المقروءة للمقاول
 */
function getUnreadAlertsCount($contractor_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM contractor_alerts_log WHERE contractor_id = ? AND is_read = 0");
    $stmt->execute([$contractor_id]);
    return (int)$stmt->fetchColumn();
}

/**
 * جلب تنبيهات المشاريع للمقاول
 */
function getContractorAlerts($contractor_id, $limit = 50) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT al.*, p.title, p.category, p.created_at as project_created_at,
               c.name_ar as country_name, c.flag,
               u.name as employer_name
        FROM contractor_alerts_log al
        JOIN projects p ON al.project_id = p.id
        LEFT JOIN countries c ON p.country_id = c.id
        LEFT JOIN users u ON p.employer_id = u.id
        WHERE al.contractor_id = ?
        ORDER BY al.sent_at DESC
        LIMIT ?
    ");
    $stmt->execute([$contractor_id, $limit]);
    return $stmt->fetchAll();
}

/**
 * تحديد تنبيه كمقروء
 */
function markAlertAsRead($alert_id, $contractor_id) {
    global $pdo;
    $stmt = $pdo->prepare("UPDATE contractor_alerts_log SET is_read = 1, read_at = NOW() WHERE id = ? AND contractor_id = ?");
    return $stmt->execute([$alert_id, $contractor_id]);
}

/**
 * تحديد جميع التنبيهات كمقروءة
 */
function markAllAlertsAsRead($contractor_id) {
    global $pdo;
    $stmt = $pdo->prepare("UPDATE contractor_alerts_log SET is_read = 1, read_at = NOW() WHERE contractor_id = ? AND is_read = 0");
    return $stmt->execute([$contractor_id]);
}

/**
 * إرسال تنبيهات المشروع الجديد للمقاولين في نفس الدولة
 */
function sendProjectAlerts($project_id, $country_id, $project_data) {
    global $pdo;
    
    if ($country_id <= 0) return false;
    
    try {
        // جلب المقاولين النشطين في نفس الدولة مع تفعيل التنبيهات
        $stmt = $pdo->prepare("
            SELECT u.id, u.name, u.phone, u.email, u.device_token, 
                   ca.alert_sound, ca.alert_email
            FROM users u
            JOIN contractor_alerts ca ON u.id = ca.contractor_id
            WHERE u.user_type = 'contractor' 
            AND u.status = 'active'
            AND u.country_id = ?
            AND ca.is_active = 1
        ");
        $stmt->execute([$country_id]);
        $contractors = $stmt->fetchAll();
        
        if (empty($contractors)) {
            error_log("⚠️ No contractors found for alerts in country: " . $country_id);
            return false;
        }
        
        error_log("✅ Found " . count($contractors) . " contractors for alerts in country: " . $country_id);
        
        // إرسال التنبيهات لكل مقاول
        foreach ($contractors as $contractor) {
            // تسجيل التنبيه في السجل
            $stmt = $pdo->prepare("INSERT INTO contractor_alerts_log (contractor_id, project_id) VALUES (?, ?)");
            $result = $stmt->execute([$contractor['id'], $project_id]);
            
            if ($result) {
                error_log("✅ Alert logged for contractor: " . $contractor['id'] . " - " . $contractor['name']);
            } else {
                error_log("❌ Failed to log alert for contractor: " . $contractor['id']);
            }
            
            // إرسال إشعار بريد إلكتروني
            if ($contractor['alert_email']) {
                try {
                    sendAlertEmail($contractor['email'], $contractor['name'], $project_data);
                    error_log("✅ Email sent to: " . $contractor['email']);
                } catch (Exception $e) {
                    error_log("❌ Email error for {$contractor['email']}: " . $e->getMessage());
                }
            }
        }
        
        return true;
    } catch (Exception $e) {
        error_log("❌ Alert system error: " . $e->getMessage());
        return false;
    }
}

/**
 * إرسال بريد إلكتروني للتنبيه للمقاولين (مشروع جديد)
 */
function sendAlertEmail($email, $contractor_name, $project) {
    $subject = "🔔 مشروع جديد في دولتك - Omran-Hub";
    $message = "
    <!DOCTYPE html>
    <html dir='rtl' lang='ar'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>تنبيه مشروع جديد</title>
        <style>
            body { font-family: Arial, sans-serif; background-color: #f4f6f9; margin: 0; padding: 20px; }
            .container { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
            .header { background: linear-gradient(135deg, #1e40af, #2563eb); padding: 30px 20px; text-align: center; }
            .header h1 { color: #ffffff; margin: 0; font-size: 24px; font-weight: 700; }
            .header .icon { font-size: 48px; display: block; margin-bottom: 10px; }
            .body { padding: 30px 25px; }
            .body .greeting { color: #1e293b; font-size: 16px; margin-bottom: 10px; }
            .body .greeting strong { color: #1e40af; }
            .body .info { color: #475569; font-size: 15px; margin-bottom: 20px; }
            .project-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 18px 20px; margin-bottom: 20px; }
            .project-box .title { color: #1e293b; font-size: 18px; font-weight: 700; margin: 0 0 8px 0; }
            .project-box .detail { color: #64748b; font-size: 14px; margin: 4px 0; }
            .project-box .detail strong { color: #334155; }
            .btn { display: inline-block; background: #2563eb; color: #ffffff; padding: 12px 32px; border-radius: 10px; text-decoration: none; font-weight: 700; font-size: 16px; margin: 10px 0; transition: background 0.3s ease; }
            .btn:hover { background: #1d4ed8; }
            .footer { padding: 20px 25px; border-top: 1px solid #e2e8f0; text-align: center; }
            .footer p { color: #94a3b8; font-size: 12px; margin: 5px 0; }
            .footer a { color: #2563eb; text-decoration: none; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <span class='icon'>🏗️</span>
                <h1>مشروع جديد في دولتك</h1>
            </div>
            <div class='body'>
                <p class='greeting'>مرحباً <strong>" . htmlspecialchars($contractor_name) . "</strong>،</p>
                <p class='info'>تم طرح مشروع جديد في دولتك، إليك التفاصيل:</p>
                
                <div class='project-box'>
                    <h3 class='title'>📋 " . htmlspecialchars($project['title']) . "</h3>
                    <p class='detail'><strong>التخصص:</strong> " . htmlspecialchars($project['category']) . "</p>
                    <p class='detail'><strong>الوصف:</strong> " . htmlspecialchars(substr($project['description'], 0, 150)) . "...</p>
                </div>
                
                <div style='text-align: center;'>
                    <a href='" . SITE_URL . "/project_detail.php?id=" . $project['id'] . "' class='btn'>📌 عرض المشروع</a>
                </div>
                
                <p style='color: #94a3b8; font-size: 13px; margin-top: 15px; text-align: center;'>
                    تم إرسال هذا الإشعار لأنك فعّلت خاصية تنبيهات المشاريع الجديدة.
                </p>
            </div>
            <div class='footer'>
                <p>© " . date('Y') . " Omran-Hub - جميع الحقوق محفوظة</p>
                <p><a href='" . SITE_URL . "/profile.php'>إدارة التنبيهات</a></p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    try {
        require_once __DIR__ . '/phpmailer/src/PHPMailer.php';
        require_once __DIR__ . '/phpmailer/src/SMTP.php';
        require_once __DIR__ . '/phpmailer/src/Exception.php';
        
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USER;
        $mail->Password = SMTP_PASS;
        $mail->SMTPSecure = SMTP_SECURE;
        $mail->Port = SMTP_PORT;
        $mail->setFrom(FROM_EMAIL, 'Omran-Hub');
        $mail->addAddress($email, $contractor_name);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $message;
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Failed to send alert email to {$email}: " . $e->getMessage());
        return false;
    }
}

/**
 * إرسال بريد إلكتروني لصاحب العمل عند الموافقة على مشروعه
 */
function sendProjectApprovedEmail($email, $name, $project) {
    $subject = "✅ تم الموافقة على مشروعك - Omran-Hub";
    $message = "
    <!DOCTYPE html>
    <html dir='rtl' lang='ar'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>تم الموافقة على مشروعك</title>
        <style>
            body { font-family: Arial, sans-serif; background-color: #f4f6f9; margin: 0; padding: 20px; }
            .container { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
            .header { background: linear-gradient(135deg, #059669, #10b981); padding: 30px 20px; text-align: center; }
            .header h1 { color: #ffffff; margin: 0; font-size: 24px; font-weight: 700; }
            .header .icon { font-size: 48px; display: block; margin-bottom: 10px; }
            .body { padding: 30px 25px; }
            .body .greeting { color: #1e293b; font-size: 16px; margin-bottom: 10px; }
            .body .greeting strong { color: #059669; }
            .body .info { color: #475569; font-size: 15px; margin-bottom: 20px; }
            .project-box { background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 12px; padding: 18px 20px; margin-bottom: 20px; }
            .project-box .title { color: #1e293b; font-size: 18px; font-weight: 700; margin: 0 0 8px 0; }
            .project-box .detail { color: #64748b; font-size: 14px; margin: 4px 0; }
            .btn { display: inline-block; background: #059669; color: #ffffff; padding: 12px 32px; border-radius: 10px; text-decoration: none; font-weight: 700; font-size: 16px; margin: 10px 0; transition: background 0.3s ease; }
            .btn:hover { background: #047857; }
            .footer { padding: 20px 25px; border-top: 1px solid #e2e8f0; text-align: center; }
            .footer p { color: #94a3b8; font-size: 12px; margin: 5px 0; }
            .footer a { color: #059669; text-decoration: none; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <span class='icon'>✅</span>
                <h1>تم الموافقة على مشروعك</h1>
            </div>
            <div class='body'>
                <p class='greeting'>مرحباً <strong>" . htmlspecialchars($name) . "</strong>،</p>
                <p class='info'>تمت الموافقة الإدارية على مشروعك، وتم إضافته للعطاءات:</p>
                
                <div class='project-box'>
                    <h3 class='title'>📋 " . htmlspecialchars($project['title']) . "</h3>
                    <p class='detail'><strong>التخصص:</strong> " . htmlspecialchars($project['category']) . "</p>
                    <p class='detail'><strong>الحالة:</strong> مفتوح للعطاءات</p>
                </div>
                
                <div style='text-align: center;'>
                    <a href='" . SITE_URL . "/project_detail.php?id=" . $project['id'] . "' class='btn'>📌 عرض المشروع</a>
                </div>
                
                <p style='color: #94a3b8; font-size: 13px; margin-top: 15px; text-align: center;'>
                    الآن يمكن للمقاولين تقديم عطاءاتهم على مشروعك.
                </p>
            </div>
            <div class='footer'>
                <p>© " . date('Y') . " Omran-Hub - جميع الحقوق محفوظة</p>
                <p><a href='" . SITE_URL . "/profile.php'>لوحة التحكم</a></p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    try {
        require_once __DIR__ . '/phpmailer/src/PHPMailer.php';
        require_once __DIR__ . '/phpmailer/src/SMTP.php';
        require_once __DIR__ . '/phpmailer/src/Exception.php';
        
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USER;
        $mail->Password = SMTP_PASS;
        $mail->SMTPSecure = SMTP_SECURE;
        $mail->Port = SMTP_PORT;
        $mail->setFrom(FROM_EMAIL, 'Omran-Hub');
        $mail->addAddress($email, $name);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $message;
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Failed to send approval email to {$email}: " . $e->getMessage());
        return false;
    }
}

// ============================================
// ✅ دوال البريد الإلكتروني للعطاءات
// ============================================

/**
 * إرسال بريد إلكتروني للمقاول عند الموافقة الإدارية على عطائه
 */
function sendBidApprovalEmail($email, $contractor_name, $bid_data) {
    $subject = "✅ تمت الموافقة الإدارية على عطائك - Omran-Hub";
    $message = "
    <!DOCTYPE html>
    <html dir='rtl' lang='ar'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>تمت الموافقة على عطائك</title>
        <style>
            body { font-family: Arial, sans-serif; background-color: #f4f6f9; margin: 0; padding: 20px; }
            .container { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
            .header { background: linear-gradient(135deg, #2563eb, #1d4ed8); padding: 30px 20px; text-align: center; }
            .header h1 { color: #ffffff; margin: 0; font-size: 24px; font-weight: 700; }
            .header .icon { font-size: 48px; display: block; margin-bottom: 10px; }
            .body { padding: 30px 25px; }
            .body .greeting { color: #1e293b; font-size: 16px; margin-bottom: 10px; }
            .body .greeting strong { color: #2563eb; }
            .body .info { color: #475569; font-size: 15px; margin-bottom: 20px; }
            .bid-box { background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 12px; padding: 18px 20px; margin-bottom: 20px; }
            .bid-box .title { color: #1e293b; font-size: 18px; font-weight: 700; margin: 0 0 8px 0; }
            .bid-box .detail { color: #64748b; font-size: 14px; margin: 4px 0; }
            .bid-box .detail strong { color: #334155; }
            .bid-box .amount { color: #2563eb; font-size: 20px; font-weight: 700; }
            .btn { display: inline-block; background: #2563eb; color: #ffffff; padding: 12px 32px; border-radius: 10px; text-decoration: none; font-weight: 700; font-size: 16px; margin: 10px 0; transition: background 0.3s ease; }
            .btn:hover { background: #1d4ed8; }
            .footer { padding: 20px 25px; border-top: 1px solid #e2e8f0; text-align: center; }
            .footer p { color: #94a3b8; font-size: 12px; margin: 5px 0; }
            .footer a { color: #2563eb; text-decoration: none; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <span class='icon'>✅</span>
                <h1>تمت الموافقة على عطائك</h1>
            </div>
            <div class='body'>
                <p class='greeting'>مرحباً <strong>" . htmlspecialchars($contractor_name) . "</strong>،</p>
                <p class='info'>تمت الموافقة الإدارية المبدئية على عطائك في المشروع التالي:</p>
                
                <div class='bid-box'>
                    <h3 class='title'>📋 " . htmlspecialchars($bid_data['project_title']) . "</h3>
                    <p class='detail'><strong>المبلغ المقترح:</strong> <span class='amount'>" . number_format($bid_data['amount']) . " ريال</span></p>
                    " . (!empty($bid_data['duration_days']) ? "<p class='detail'><strong>المدة:</strong> " . $bid_data['duration_days'] . " يوم</p>" : "") . "
                    " . (!empty($bid_data['proposal']) ? "<p class='detail'><strong>العرض:</strong> " . htmlspecialchars($bid_data['proposal']) . "</p>" : "") . "
                </div>
                
                <div style='text-align: center;'>
                    <a href='" . SITE_URL . "/project_detail.php?id=" . $bid_data['project_id'] . "' class='btn'>📌 عرض المشروع</a>
                </div>
                
                <p style='color: #94a3b8; font-size: 13px; margin-top: 15px; text-align: center;'>
                    الآن بانتظار موافقة صاحب العمل النهائية على عطائك.
                </p>
            </div>
            <div class='footer'>
                <p>© " . date('Y') . " Omran-Hub - جميع الحقوق محفوظة</p>
                <p><a href='" . SITE_URL . "/profile.php'>لوحة التحكم</a></p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    try {
        require_once __DIR__ . '/phpmailer/src/PHPMailer.php';
        require_once __DIR__ . '/phpmailer/src/SMTP.php';
        require_once __DIR__ . '/phpmailer/src/Exception.php';
        
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USER;
        $mail->Password = SMTP_PASS;
        $mail->SMTPSecure = SMTP_SECURE;
        $mail->Port = SMTP_PORT;
        $mail->setFrom(FROM_EMAIL, 'Omran-Hub');
        $mail->addAddress($email, $contractor_name);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $message;
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Failed to send bid approval email to {$email}: " . $e->getMessage());
        return false;
    }
}

/**
 * إرسال بريد إلكتروني لصاحب العمل عند وجود عطاء جديد يحتاج موافقته
 */
function sendBidToEmployerEmail($email, $employer_name, $bid_data) {
    $subject = "📩 عطاء جديد يحتاج موافقتك - Omran-Hub";
    $message = "
    <!DOCTYPE html>
    <html dir='rtl' lang='ar'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>عطاء جديد يحتاج موافقتك</title>
        <style>
            body { font-family: Arial, sans-serif; background-color: #f4f6f9; margin: 0; padding: 20px; }
            .container { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
            .header { background: linear-gradient(135deg, #f59e0b, #d97706); padding: 30px 20px; text-align: center; }
            .header h1 { color: #ffffff; margin: 0; font-size: 24px; font-weight: 700; }
            .header .icon { font-size: 48px; display: block; margin-bottom: 10px; }
            .body { padding: 30px 25px; }
            .body .greeting { color: #1e293b; font-size: 16px; margin-bottom: 10px; }
            .body .greeting strong { color: #d97706; }
            .body .info { color: #475569; font-size: 15px; margin-bottom: 20px; }
            .bid-box { background: #fffbeb; border: 1px solid #fde68a; border-radius: 12px; padding: 18px 20px; margin-bottom: 20px; }
            .bid-box .title { color: #1e293b; font-size: 18px; font-weight: 700; margin: 0 0 8px 0; }
            .bid-box .detail { color: #64748b; font-size: 14px; margin: 4px 0; }
            .bid-box .detail strong { color: #334155; }
            .bid-box .amount { color: #d97706; font-size: 20px; font-weight: 700; }
            .bid-box .contractor { color: #2563eb; font-weight: 600; }
            .btn { display: inline-block; background: #d97706; color: #ffffff; padding: 12px 32px; border-radius: 10px; text-decoration: none; font-weight: 700; font-size: 16px; margin: 10px 0; transition: background 0.3s ease; }
            .btn:hover { background: #b45309; }
            .footer { padding: 20px 25px; border-top: 1px solid #e2e8f0; text-align: center; }
            .footer p { color: #94a3b8; font-size: 12px; margin: 5px 0; }
            .footer a { color: #d97706; text-decoration: none; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <span class='icon'>📩</span>
                <h1>عطاء جديد يحتاج موافقتك</h1>
            </div>
            <div class='body'>
                <p class='greeting'>مرحباً <strong>" . htmlspecialchars($employer_name) . "</strong>،</p>
                <p class='info'>قام المقاول <strong class='contractor'>" . htmlspecialchars($bid_data['contractor_name']) . "</strong> بتقديم عطاء على مشروعك:</p>
                
                <div class='bid-box'>
                    <h3 class='title'>📋 " . htmlspecialchars($bid_data['project_title']) . "</h3>
                    <p class='detail'><strong>المبلغ المقترح:</strong> <span class='amount'>" . number_format($bid_data['amount']) . " ريال</span></p>
                    " . (!empty($bid_data['duration_days']) ? "<p class='detail'><strong>المدة:</strong> " . $bid_data['duration_days'] . " يوم</p>" : "") . "
                    " . (!empty($bid_data['proposal']) ? "<p class='detail'><strong>العرض:</strong> " . htmlspecialchars($bid_data['proposal']) . "</p>" : "") . "
                    <p class='detail' style='margin-top:8px;'><strong>📌 حالة العطاء:</strong> <span style='color:#d97706;font-weight:600;'>بإنتظار موافقتك النهائية</span></p>
                </div>
                
                <div style='text-align: center;'>
                    <a href='" . SITE_URL . "/project_detail.php?id=" . $bid_data['project_id'] . "' class='btn'>📌 مراجعة العطاء</a>
                </div>
                
                <p style='color: #94a3b8; font-size: 13px; margin-top: 15px; text-align: center;'>
                    تمت الموافقة الإدارية المبدئية على هذا العطاء، الآن دورك للموافقة النهائية أو الرفض.
                </p>
            </div>
            <div class='footer'>
                <p>© " . date('Y') . " Omran-Hub - جميع الحقوق محفوظة</p>
                <p><a href='" . SITE_URL . "/profile.php'>لوحة التحكم</a></p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    try {
        require_once __DIR__ . '/phpmailer/src/PHPMailer.php';
        require_once __DIR__ . '/phpmailer/src/SMTP.php';
        require_once __DIR__ . '/phpmailer/src/Exception.php';
        
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USER;
        $mail->Password = SMTP_PASS;
        $mail->SMTPSecure = SMTP_SECURE;
        $mail->Port = SMTP_PORT;
        $mail->setFrom(FROM_EMAIL, 'Omran-Hub');
        $mail->addAddress($email, $employer_name);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $message;
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Failed to send bid notification to employer {$email}: " . $e->getMessage());
        return false;
    }
}

/**
 * إرسال بريد إلكتروني للمقاول عند رفض عطائه
 */
function sendBidRejectedEmail($email, $contractor_name, $bid_data) {
    $subject = "❌ تم رفض عطائك - Omran-Hub";
    $message = "
    <!DOCTYPE html>
    <html dir='rtl' lang='ar'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>تم رفض عطائك</title>
        <style>
            body { font-family: Arial, sans-serif; background-color: #f4f6f9; margin: 0; padding: 20px; }
            .container { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
            .header { background: linear-gradient(135deg, #dc2626, #b91c1c); padding: 30px 20px; text-align: center; }
            .header h1 { color: #ffffff; margin: 0; font-size: 24px; font-weight: 700; }
            .header .icon { font-size: 48px; display: block; margin-bottom: 10px; }
            .body { padding: 30px 25px; }
            .body .greeting { color: #1e293b; font-size: 16px; margin-bottom: 10px; }
            .body .greeting strong { color: #dc2626; }
            .body .info { color: #475569; font-size: 15px; margin-bottom: 20px; }
            .bid-box { background: #fef2f2; border: 1px solid #fca5a5; border-radius: 12px; padding: 18px 20px; margin-bottom: 20px; }
            .bid-box .title { color: #1e293b; font-size: 18px; font-weight: 700; margin: 0 0 8px 0; }
            .bid-box .detail { color: #64748b; font-size: 14px; margin: 4px 0; }
            .bid-box .detail strong { color: #334155; }
            .btn { display: inline-block; background: #dc2626; color: #ffffff; padding: 12px 32px; border-radius: 10px; text-decoration: none; font-weight: 700; font-size: 16px; margin: 10px 0; transition: background 0.3s ease; }
            .btn:hover { background: #b91c1c; }
            .footer { padding: 20px 25px; border-top: 1px solid #e2e8f0; text-align: center; }
            .footer p { color: #94a3b8; font-size: 12px; margin: 5px 0; }
            .footer a { color: #dc2626; text-decoration: none; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <span class='icon'>❌</span>
                <h1>تم رفض عطائك</h1>
            </div>
            <div class='body'>
                <p class='greeting'>مرحباً <strong>" . htmlspecialchars($contractor_name) . "</strong>،</p>
                <p class='info'>للأسف، تم رفض عطائك في المشروع التالي:</p>
                
                <div class='bid-box'>
                    <h3 class='title'>📋 " . htmlspecialchars($bid_data['project_title']) . "</h3>
                    <p class='detail'><strong>سبب الرفض:</strong> تم رفض العطاء من قبل الإدارة.</p>
                </div>
                
                <div style='text-align: center;'>
                    <a href='" . SITE_URL . "/project_detail.php?id=" . $bid_data['project_id'] . "' class='btn'>📌 عرض المشروع</a>
                </div>
                
                <p style='color: #94a3b8; font-size: 13px; margin-top: 15px; text-align: center;'>
                    يمكنك متابعة مشاريع أخرى وتقديم عطاءات جديدة.
                </p>
            </div>
            <div class='footer'>
                <p>© " . date('Y') . " Omran-Hub - جميع الحقوق محفوظة</p>
                <p><a href='" . SITE_URL . "/projects_list.php'>عرض المشاريع</a></p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    try {
        require_once __DIR__ . '/phpmailer/src/PHPMailer.php';
        require_once __DIR__ . '/phpmailer/src/SMTP.php';
        require_once __DIR__ . '/phpmailer/src/Exception.php';
        
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USER;
        $mail->Password = SMTP_PASS;
        $mail->SMTPSecure = SMTP_SECURE;
        $mail->Port = SMTP_PORT;
        $mail->setFrom(FROM_EMAIL, 'Omran-Hub');
        $mail->addAddress($email, $contractor_name);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $message;
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Failed to send bid rejection email to {$email}: " . $e->getMessage());
        return false;
    }
}

// ============================================
// ✅ دالة البريد الإلكتروني للموافقة النهائية من صاحب العمل
// ============================================

/**
 * إرسال بريد إلكتروني للمقاول عند الموافقة النهائية من صاحب العمل
 */
function sendBidFinalApprovalEmail($email, $contractor_name, $bid_data) {
    $subject = "🎉 تمت الموافقة النهائية على عطائك - Omran-Hub";
    $message = "
    <!DOCTYPE html>
    <html dir='rtl' lang='ar'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>تمت الموافقة النهائية على عطائك</title>
        <style>
            body { font-family: Arial, sans-serif; background-color: #f4f6f9; margin: 0; padding: 20px; }
            .container { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
            .header { background: linear-gradient(135deg, #059669, #10b981); padding: 30px 20px; text-align: center; }
            .header h1 { color: #ffffff; margin: 0; font-size: 24px; font-weight: 700; }
            .header .icon { font-size: 48px; display: block; margin-bottom: 10px; }
            .body { padding: 30px 25px; }
            .body .greeting { color: #1e293b; font-size: 16px; margin-bottom: 10px; }
            .body .greeting strong { color: #059669; }
            .body .info { color: #475569; font-size: 15px; margin-bottom: 20px; }
            .bid-box { background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 12px; padding: 18px 20px; margin-bottom: 20px; }
            .bid-box .title { color: #1e293b; font-size: 18px; font-weight: 700; margin: 0 0 8px 0; }
            .bid-box .detail { color: #64748b; font-size: 14px; margin: 4px 0; }
            .bid-box .detail strong { color: #334155; }
            .bid-box .amount { color: #059669; font-size: 20px; font-weight: 700; }
            .btn { display: inline-block; background: #059669; color: #ffffff; padding: 12px 32px; border-radius: 10px; text-decoration: none; font-weight: 700; font-size: 16px; margin: 10px 0; transition: background 0.3s ease; }
            .btn:hover { background: #047857; }
            .btn-chat { display: inline-block; background: #2563eb; color: #ffffff; padding: 12px 32px; border-radius: 10px; text-decoration: none; font-weight: 700; font-size: 16px; margin: 10px 5px; transition: background 0.3s ease; }
            .btn-chat:hover { background: #1d4ed8; }
            .footer { padding: 20px 25px; border-top: 1px solid #e2e8f0; text-align: center; }
            .footer p { color: #94a3b8; font-size: 12px; margin: 5px 0; }
            .footer a { color: #059669; text-decoration: none; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <span class='icon'>🎉</span>
                <h1>تهانينا! تمت الموافقة النهائية على عطائك</h1>
            </div>
            <div class='body'>
                <p class='greeting'>مرحباً <strong>" . htmlspecialchars($contractor_name) . "</strong>،</p>
                <p class='info'>نحن سعداء بإعلامك بأن صاحب العمل وافق نهائياً على عطائك في المشروع التالي:</p>
                
                <div class='bid-box'>
                    <h3 class='title'>📋 " . htmlspecialchars($bid_data['project_title']) . "</h3>
                    <p class='detail'><strong>المبلغ المتفق عليه:</strong> <span class='amount'>" . number_format($bid_data['amount']) . " ريال</span></p>
                    " . (!empty($bid_data['duration_days']) ? "<p class='detail'><strong>المدة المتفق عليها:</strong> " . $bid_data['duration_days'] . " يوم</p>" : "") . "
                    <p class='detail' style='margin-top:8px;'><strong>📌 حالة العطاء:</strong> <span style='color:#059669;font-weight:600;'>تمت الموافقة النهائية ✅</span></p>
                </div>
                
                <div style='text-align: center;'>
                    <a href='" . SITE_URL . "/project_detail.php?id=" . $bid_data['project_id'] . "' class='btn'>📌 عرض المشروع</a>
                    <a href='" . SITE_URL . "/chat.php?contractor_id=" . $bid_data['employer_id'] . "&project_id=" . $bid_data['project_id'] . "' class='btn-chat'>💬 تواصل مع صاحب العمل</a>
                </div>
                
                <div style='background: #f0fdf4; border-radius: 10px; padding: 15px; margin-top: 15px;'>
                    <p style='color: #065f46; font-size: 14px; margin: 0; text-align: center;'>
                        <strong>📌 الخطوة التالية:</strong> تواصل مع صاحب العمل عبر المحادثة لاتخاذ الخطوات التالية لتنفيذ المشروع.
                    </p>
                </div>
                
                <p style='color: #94a3b8; font-size: 13px; margin-top: 15px; text-align: center;'>
                    نتمنى لك التوفيق في مشروعك الجديد! 🚀
                </p>
            </div>
            <div class='footer'>
                <p>© " . date('Y') . " Omran-Hub - جميع الحقوق محفوظة</p>
                <p><a href='" . SITE_URL . "/profile.php'>لوحة التحكم</a></p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    try {
        require_once __DIR__ . '/phpmailer/src/PHPMailer.php';
        require_once __DIR__ . '/phpmailer/src/SMTP.php';
        require_once __DIR__ . '/phpmailer/src/Exception.php';
        
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USER;
        $mail->Password = SMTP_PASS;
        $mail->SMTPSecure = SMTP_SECURE;
        $mail->Port = SMTP_PORT;
        $mail->setFrom(FROM_EMAIL, 'Omran-Hub');
        $mail->addAddress($email, $contractor_name);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $message;
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Failed to send final approval email to {$email}: " . $e->getMessage());
        return false;
    }
}
?>