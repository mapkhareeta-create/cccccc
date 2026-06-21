<?php
require_once 'config.php';

if (isLoggedIn()) {
    redirect('');
}

$countries = getCountries();
$error = '';
$success = '';

// ========================================
// دالة sendOTP المحسنة - ترسل طلب POST وتتعامل مع الاستجابة
// ========================================
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
    
    // تنظيف الاستجابة من أي مسافات أو أحرف غير مرغوب فيها
    $response = trim($response);
    
    // محاولة فك JSON
    $result = json_decode($response, true);
    
    if (json_last_error() === JSON_ERROR_NONE) {
        return $result;
    }
    
    // إذا فشل فك JSON، عرض الخطأ
    return [
        'status' => 'error', 
        'message' => 'استجابة غير صالحة من الخادم',
        'debug' => 'JSON Error: ' . json_last_error_msg(),
        'raw' => substr($response, 0, 200)
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = clean($_POST['name'] ?? '');
    $email = clean($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $user_type = clean($_POST['user_type'] ?? '');
    $entity_type = clean($_POST['entity_type'] ?? '');
    $phone = clean($_POST['phone'] ?? '');
    $whatsapp = clean($_POST['whatsapp'] ?? '');
    $country_id = (int)($_POST['country_id'] ?? 0);
    $city_id = (int)($_POST['city_id'] ?? 0);
    $shop_name = clean($_POST['shop_name'] ?? '');
    $shop_address = clean($_POST['shop_address'] ?? '');
    $specializations = $_POST['specializations'] ?? [];
    
    // ========================================
    // التحقق من البيانات
    // ========================================
    if (empty($name) || empty($email) || empty($password)) {
        $error = 'يرجى ملء جميع الحقول المطلوبة';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'البريد الإلكتروني غير صالح';
    } elseif (strlen($password) < 6) {
        $error = 'كلمة المرور يجب أن تكون 6 أحرف على الأقل';
    } elseif ($password !== $confirm_password) {
        $error = 'كلمتا المرور غير متطابقتين';
    } elseif (!in_array($user_type, ['employer', 'contractor', 'shop'])) {
        $error = 'يرجى اختيار نوع الحساب';
    } elseif (in_array($user_type, ['employer', 'contractor']) && empty($entity_type)) {
        $error = 'يرجى اختيار تصنيف الحساب (فرد أو شركة)';
    } elseif ($user_type === 'shop' && empty($shop_name)) {
        $error = 'يرجى إدخال اسم المحل';
    } else {
        // ========================================
        // التحقق من وجود البريد الإلكتروني
        // ========================================
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = 'البريد الإلكتروني مسجل مسبقاً';
        } else {
            // ========================================
            // إنشاء رمز OTP
            // ========================================
            $otp_code = rand(100000, 999999);
            $otp_expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));
            
            // تخزين بيانات التسجيل المؤقتة في الجلسة
            $_SESSION['temp_registration'] = [
                'name' => $name,
                'email' => $email,
                'password' => password_hash($password, PASSWORD_DEFAULT),
                'user_type' => $user_type,
                'entity_type' => $entity_type ?: null,
                'phone' => $phone,
                'whatsapp' => $whatsapp,
                'country_id' => $country_id,
                'city_id' => $city_id,
                'shop_name' => $shop_name,
                'shop_address' => $shop_address,
                'specializations' => $specializations,
                'otp' => $otp_code,
                'otp_expiry' => $otp_expires
            ];
            
            // ========================================
            // إرسال OTP
            // ========================================
            $otpData = [
                'email' => $email,
                'name' => $name,
                'otp' => $otp_code
            ];
            
            $otpResponse = sendOTP($otpData);
            
            // ========================================
            // معالجة الاستجابة
            // ========================================
            if (isset($otpResponse['status']) && $otpResponse['status'] === 'success') {
                // حفظ البريد الإلكتروني والاسم في الجلسة
                $_SESSION['temp_email'] = $email;
                $_SESSION['temp_name'] = $name;
                
                // التوجيه إلى صفحة التحقق
                header('Location: ' . SITE_URL . '/verify_otp.php');
                exit;
            } else {
                // عرض رسالة الخطأ
                $error = $otpResponse['message'] ?? 'حدث خطأ أثناء إرسال رمز التحقق';
                
                // إذا كان هناك معلومات تشخيص إضافية (للتجربة)
                if (isset($otpResponse['debug'])) {
                    $error .= ' (Debug: ' . $otpResponse['debug'] . ')';
                }
                if (isset($otpResponse['raw'])) {
                    $error .= ' (Response: ' . $otpResponse['raw'] . ')';
                }
                
                // حذف البيانات المؤقتة في حالة الفشل
                unset($_SESSION['temp_registration']);
            }
        }
    }
}

$page_title = 'تسجيل حساب جديد';
include 'includes/header.php';
?>

<div class="min-h-screen py-12">
    <div class="max-w-2xl mx-auto px-4">
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <!-- Header -->
            <div class="gradient-hero text-white p-8 text-center">
                <h1 class="text-3xl font-bold mb-2">إنشاء حساب جديد</h1>
                <p class="text-blue-200">انضم لمزاد البناء وابدأ الآن</p>
            </div>
            
            <div class="p-8">
                <?php if ($error): ?>
                <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl mb-6 flex items-start gap-2">
                    <i class="fas fa-exclamation-circle mt-1"></i>
                    <div>
                        <span><?= $error ?></span>
                        <?php if (strpos($error, 'Debug:') !== false): ?>
                        <details class="mt-2 text-xs text-gray-500">
                            <summary>معلومات التشخيص</summary>
                            <pre class="mt-1 p-2 bg-gray-100 rounded overflow-x-auto"><?= htmlspecialchars($error) ?></pre>
                        </details>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-xl mb-6 flex items-center gap-2">
                    <i class="fas fa-check-circle"></i>
                    <span><?= $success ?></span>
                </div>
                <?php endif; ?>
                
                <form method="POST" id="register-form">
                    <!-- 1. User Type Selection -->
                    <div class="mb-8">
                        <label class="block text-sm font-bold text-gray-700 mb-3">نوع الحساب <span class="text-red-500">*</span></label>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mb-3">
                            <label class="relative cursor-pointer">
                                <input type="radio" name="user_type" value="employer" class="peer sr-only" required onchange="toggleUserTypeFields()">
                                <div class="border-2 border-gray-200 peer-checked:border-primary-500 peer-checked:bg-primary-50 rounded-xl p-5 text-center transition-all hover:border-primary-300 h-full">
                                    <i class="fas fa-building text-2xl text-gray-400 mb-2 block"></i>
                                    <div class="font-bold text-gray-700">صاحب عمل</div>
                                    <div class="text-xs text-gray-400 mt-1">أطرح مشاريعي وأبحث عن مقاولين</div>
                                </div>
                            </label>
                            <label class="relative cursor-pointer">
                                <input type="radio" name="user_type" value="contractor" class="peer sr-only" onchange="toggleUserTypeFields()">
                                <div class="border-2 border-gray-200 peer-checked:border-primary-500 peer-checked:bg-primary-50 rounded-xl p-5 text-center transition-all hover:border-primary-300 h-full">
                                    <i class="fas fa-hard-hat text-2xl text-gray-400 mb-2 block"></i>
                                    <div class="font-bold text-gray-700">مقاول</div>
                                    <div class="text-xs text-gray-400 mt-1">أقدم عطاءاتي وأعمل على المشاريع</div>
                                </div>
                            </label>
                        </div>
                        <label class="relative cursor-pointer block">
                            <input type="radio" name="user_type" value="shop" class="peer sr-only" onchange="toggleUserTypeFields()">
                            <div class="border-2 border-dashed border-gray-200 peer-checked:border-primary-500 peer-checked:bg-primary-50 rounded-xl p-3 text-center transition-all hover:border-primary-300 flex items-center justify-center gap-3">
                                <i class="fas fa-store text-xl text-gray-400"></i>
                                <div class="text-right">
                                    <div class="font-bold text-gray-700 text-sm">محل مواد بناء</div>
                                    <div class="text-xs text-gray-400">أعرض بضاعتي وأبيع مواد البناء</div>
                                </div>
                            </div>
                        </label>
                    </div>

                    <!-- 2. Entity Type Selection -->
                    <div id="entity-fields" class="hidden mb-8">
                        <label class="block text-sm font-bold text-gray-700 mb-3">تصنيف الحساب <span class="text-red-500">*</span></label>
                        <div class="grid grid-cols-2 gap-3">
                            <label class="relative cursor-pointer">
                                <input type="radio" name="entity_type" value="individual" class="peer sr-only">
                                <div class="border-2 border-gray-200 peer-checked:border-primary-500 peer-checked:bg-primary-50 rounded-xl p-4 text-center transition-all hover:border-primary-300">
                                    <i class="fas fa-user text-xl text-gray-400 mb-1 block"></i>
                                    <div class="font-bold text-gray-700 text-sm">فرد</div>
                                </div>
                            </label>
                            <label class="relative cursor-pointer">
                                <input type="radio" name="entity_type" value="company" class="peer sr-only">
                                <div class="border-2 border-gray-200 peer-checked:border-primary-500 peer-checked:bg-primary-50 rounded-xl p-4 text-center transition-all hover:border-primary-300">
                                    <i class="fas fa-city text-xl text-gray-400 mb-1 block"></i>
                                    <div class="font-bold text-gray-700 text-sm">شركة</div>
                                </div>
                            </label>
                        </div>
                    </div>
                    
                    <!-- Name -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">الاسم الكامل <span class="text-red-500">*</span></label>
                            <input type="text" name="name" required value="<?= clean($_POST['name'] ?? '') ?>" 
                                class="w-full border border-gray-300 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-all" placeholder="أدخل اسمك الكامل">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">البريد الإلكتروني <span class="text-red-500">*</span></label>
                            <input type="email" name="email" required value="<?= clean($_POST['email'] ?? '') ?>"
                                class="w-full border border-gray-300 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-all" placeholder="example@email.com" dir="ltr">
                        </div>
                    </div>
                    
                    <!-- Password -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">كلمة المرور <span class="text-red-500">*</span></label>
                            <div class="relative">
                                <input type="password" name="password" id="reg-password" required
                                    class="w-full border border-gray-300 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-all" placeholder="6 أحرف على الأقل" dir="ltr">
                                <button type="button" onclick="togglePassword('reg-password')" class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">تأكيد كلمة المرور <span class="text-red-500">*</span></label>
                            <div class="relative">
                                <input type="password" name="confirm_password" id="reg-confirm-password" required
                                    class="w-full border border-gray-300 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-all" placeholder="أعد كتابة كلمة المرور" dir="ltr">
                                <button type="button" onclick="togglePassword('reg-confirm-password')" class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Phone -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">رقم الجوال</label>
                            <input type="tel" name="phone" value="<?= clean($_POST['phone'] ?? '') ?>"
                                class="w-full border border-gray-300 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-all" placeholder="+966 5X XXX XXXX" dir="ltr">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">واتساب</label>
                            <input type="tel" name="whatsapp" value="<?= clean($_POST['whatsapp'] ?? '') ?>"
                                class="w-full border border-gray-300 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-all" placeholder="+966 5X XXX XXXX" dir="ltr">
                        </div>
                    </div>
                    
                    <!-- Country & City -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">الدولة <span class="text-red-500">*</span></label>
                            <select name="country_id" id="reg-country" required onchange="loadCities('reg-country', 'reg-city')"
                                class="w-full border border-gray-300 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-all bg-white">
                                <option value="">اختر الدولة</option>
                                <?php foreach ($countries as $country): ?>
                                <option value="<?= $country['id'] ?>" <?= (($_POST['country_id'] ?? '') == $country['id']) ? 'selected' : '' ?>>
                                    <?= $country['flag'] ?> <?= $country['name_ar'] ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">المدينة <span class="text-red-500">*</span></label>
                            <select name="city_id" id="reg-city" required
                                class="w-full border border-gray-300 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-all bg-white">
                                <option value="">اختر المدينة</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Shop Fields -->
                    <div id="shop-fields" class="hidden">
                        <div class="border-t border-gray-100 pt-4 mt-4">
                            <h3 class="font-bold text-gray-700 mb-3"><i class="fas fa-store text-primary-500 ml-2"></i>معلومات المحل</h3>
                            <div class="grid grid-cols-1 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">اسم المحل <span class="text-red-500">*</span></label>
                                    <input type="text" name="shop_name" value="<?= clean($_POST['shop_name'] ?? '') ?>"
                                        class="w-full border border-gray-300 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-all" placeholder="مثال: مؤسسة النور لمواد البناء">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">عنوان المحل</label>
                                    <input type="text" name="shop_address" value="<?= clean($_POST['shop_address'] ?? '') ?>"
                                        class="w-full border border-gray-300 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-all" placeholder="الشارع - الحي - المدينة">
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Contractor Specializations -->
                    <div id="contractor-fields" class="hidden">
                        <div class="border-t border-gray-100 pt-4 mt-4">
                            <h3 class="font-bold text-gray-700 mb-3"><i class="fas fa-hard-hat text-primary-500 ml-2"></i>التخصصات</h3>
                            <div class="grid grid-cols-2 md:grid-cols-3 gap-2">
                                <?php foreach (getProjectCategories() as $cat => $icon): ?>
                                <label class="flex items-center gap-2 bg-gray-50 hover:bg-primary-50 border border-gray-200 hover:border-primary-300 rounded-lg px-3 py-2 cursor-pointer transition-all">
                                    <input type="checkbox" name="specializations[]" value="<?= $cat ?>" class="text-primary-600 focus:ring-primary-500">
                                    <span class="text-sm"><?= $icon ?> <?= $cat ?></span>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Submit -->
                    <div class="mt-6">
                        <button type="submit" class="w-full bg-primary-600 hover:bg-primary-700 text-white font-bold py-3 rounded-xl transition-all hover:shadow-lg">
                            <i class="fas fa-user-plus ml-2"></i> تسجيل الحساب
                        </button>
                    </div>
                    
                    <div class="text-center mt-4 text-sm text-gray-500">
                        لديك حساب بالفعل؟ <a href="<?= SITE_URL ?>/login.php" class="text-primary-600 font-medium hover:text-primary-700">تسجيل الدخول</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function toggleUserTypeFields() {
    const selectedType = document.querySelector('input[name="user_type"]:checked')?.value;
    const entityFields = document.getElementById('entity-fields');
    const shopFields = document.getElementById('shop-fields');
    const contractorFields = document.getElementById('contractor-fields');
    
    // إخفاء الكل أولاً
    if (entityFields) entityFields.classList.add('hidden');
    if (shopFields) shopFields.classList.add('hidden');
    if (contractorFields) contractorFields.classList.add('hidden');
    
    if (selectedType === 'employer' || selectedType === 'contractor') {
        if (entityFields) entityFields.classList.remove('hidden');
        if (selectedType === 'contractor' && contractorFields) {
            contractorFields.classList.remove('hidden');
        }
    } else if (selectedType === 'shop') {
        if (shopFields) shopFields.classList.remove('hidden');
    }
}

function togglePassword(fieldId) {
    const field = document.getElementById(fieldId);
    const icon = field.nextElementSibling.querySelector('i');
    if (field.type === 'password') {
        field.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        field.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}

// ============================================
// دالة تحميل المدن - المعدلة (مع دعم data.data)
// ============================================
function loadCities(countrySelectId, citySelectId) {
    const countrySelect = document.getElementById(countrySelectId);
    const citySelect = document.getElementById(citySelectId);
    const countryId = countrySelect.value;
    
    citySelect.innerHTML = '<option value="">جاري التحميل...</option>';
    
    if (!countryId || countryId == 0) {
        citySelect.innerHTML = '<option value="">اختر المدينة</option>';
        return;
    }
    
    fetch('api.php?action=get_cities&country_id=' + countryId)
        .then(response => response.json())
        .then(data => {
            citySelect.innerHTML = '<option value="">اختر المدينة</option>';
            
            // ✅ التصحيح: استخدام data.data بدلاً من data
            if (data.status === 'success' && data.data && data.data.length > 0) {
                data.data.forEach(city => {
                    const option = document.createElement('option');
                    option.value = city.id;
                    option.textContent = city.name_ar;
                    citySelect.appendChild(option);
                });
                console.log('✅ تم تحميل المدن:', data.data.length);
            } else {
                console.warn('⚠️ لا توجد مدن');
            }
        })
        .catch(error => {
            console.error('❌ خطأ في تحميل المدن:', error);
            citySelect.innerHTML = '<option value="">خطأ في التحميل</option>';
        });
}

// تحميل المدن إذا كانت الدولة محددة مسبقاً
document.addEventListener('DOMContentLoaded', function() {
    const selectedCountry = document.getElementById('reg-country').value;
    if (selectedCountry) {
        loadCities('reg-country', 'reg-city');
    }
    toggleUserTypeFields();
});
</script>

<?php include 'includes/footer.php'; ?>