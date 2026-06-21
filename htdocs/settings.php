<?php
/**
 * ============================================================
 * SETTINGS.PHP - صفحة الإعدادات (مع التصحيح)
 * ============================================================
 */

require_once 'config.php';

// التأكد من تسجيل الدخول
if (!isLoggedIn()) {
    redirect('login.php');
}

$user_id = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'] ?? 'contractor';

// ============================================================
// التحقق من وجود الدالة getUserAlertSettings()
// ============================================================
if (!function_exists('getUserAlertSettings')) {
    // تعريف الدالة إذا لم تكن موجودة
    function getUserAlertSettings($pdo, $userId, $userType) {
        $table = ($userType === 'contractor') ? 'contractor_alerts' : 'employer_alerts';
        $idField = ($userType === 'contractor') ? 'contractor_id' : 'employer_id';
        
        try {
            $stmt = $pdo->prepare("SELECT is_active, alert_sound, alert_email FROM $table WHERE $idField = ?");
            $stmt->execute([$userId]);
            $settings = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$settings) {
                $stmt = $pdo->prepare("INSERT INTO $table ($idField, is_active, alert_sound, alert_email) VALUES (?, 1, 1, 1)");
                $stmt->execute([$userId]);
                return ['is_active' => 1, 'alert_sound' => 1, 'alert_email' => 1];
            }
            
            return $settings;
        } catch (Exception $e) {
            error_log("Get alert settings error: " . $e->getMessage());
            return ['is_active' => 1, 'alert_sound' => 1, 'alert_email' => 1];
        }
    }
}

// ============================================================
// جلب الإعدادات
// ============================================================
$settings = getUserAlertSettings($pdo, $user_id, $user_type);

$page_title = 'الإعدادات';
include 'includes/header.php';
?>

<div class="py-8">
    <div class="max-w-2xl mx-auto px-4">
        
        <div class="flex items-center gap-3 mb-6">
            <div class="w-12 h-12 bg-blue-50 rounded-2xl flex items-center justify-center">
                <i class="fas fa-cog text-blue-500 text-2xl"></i>
            </div>
            <div>
                <h1 class="text-2xl font-bold text-gray-800">الإعدادات</h1>
                <p class="text-sm text-gray-400">تحكم في إعدادات الصوت والتنبيهات</p>
            </div>
        </div>

        <!-- ===== بطاقة إعدادات الصوت والتنبيهات ===== -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            
            <!-- عنوان البطاقة -->
            <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50">
                <h2 class="font-bold text-gray-700 flex items-center gap-2">
                    <i class="fas fa-sliders-h text-blue-500"></i>
                    إعدادات التنبيهات
                </h2>
            </div>

            <!-- المحتوى -->
            <div class="p-6 space-y-6">

                <!-- ===== خيار الصوت ===== -->
                <div class="flex items-center justify-between p-4 bg-gray-50 rounded-xl hover:bg-gray-100 transition-colors">
                    <div class="flex items-center gap-4">
                        <div class="w-10 h-10 bg-blue-100 rounded-xl flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-volume-up text-blue-500 text-lg"></i>
                        </div>
                        <div>
                            <h3 class="font-bold text-gray-800 text-sm">الصوت</h3>
                            <p class="text-xs text-gray-400">تشغيل أو إيقاف صوت التنبيهات</p>
                        </div>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" id="settings-sound-toggle" 
                               class="sr-only peer" 
                               <?= ($settings['alert_sound'] ?? 1) ? 'checked' : '' ?>
                               onchange="updateSound(this.checked)">
                        <div class="w-11 h-6 bg-gray-200 peer-focus:ring-2 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                    </label>
                </div>

                <!-- ===== خيار التنبيهات ===== -->
                <div class="flex items-center justify-between p-4 bg-gray-50 rounded-xl hover:bg-gray-100 transition-colors">
                    <div class="flex items-center gap-4">
                        <div class="w-10 h-10 bg-amber-100 rounded-xl flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-bell text-amber-500 text-lg"></i>
                        </div>
                        <div>
                            <h3 class="font-bold text-gray-800 text-sm">التنبيهات</h3>
                            <p class="text-xs text-gray-400">تشغيل أو إيقاف جميع التنبيهات</p>
                        </div>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" id="settings-alerts-toggle" 
                               class="sr-only peer" 
                               <?= ($settings['is_active'] ?? 1) ? 'checked' : '' ?>
                               onchange="updateAlerts(this.checked)">
                        <div class="w-11 h-6 bg-gray-200 peer-focus:ring-2 peer-focus:ring-amber-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-amber-500"></div>
                    </label>
                </div>

                <!-- ===== زر عرض الإشعارات ===== -->
                <div class="flex items-center justify-between p-4 bg-gray-50 rounded-xl hover:bg-gray-100 transition-colors">
                    <div class="flex items-center gap-4">
                        <div class="w-10 h-10 bg-purple-100 rounded-xl flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-list-ul text-purple-500 text-lg"></i>
                        </div>
                        <div>
                            <h3 class="font-bold text-gray-800 text-sm">عرض الإشعارات</h3>
                            <p class="text-xs text-gray-400">جميع الإشعارات السابقة</p>
                        </div>
                    </div>
                    <a href="<?= SITE_URL ?>/alerts.php" 
                       class="bg-purple-500 hover:bg-purple-600 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors flex items-center gap-2">
                        <i class="fas fa-arrow-left"></i> عرض
                    </a>
                </div>

                <!-- ===== حالة التنبيهات ===== -->
                <div class="mt-4 p-4 bg-blue-50 rounded-xl border border-blue-100">
                    <div class="flex items-center gap-2 text-sm text-blue-700">
                        <i class="fas fa-info-circle"></i>
                        <span>
                            حالة التنبيهات: 
                            <strong id="settings-status-text">
                                <?= ($settings['is_active'] ?? 1) ? 'مفعلة ✅' : 'معطلة ❌' ?>
                            </strong>
                            | الصوت: 
                            <strong id="settings-sound-text">
                                <?= ($settings['alert_sound'] ?? 1) ? 'مفعل 🔊' : 'معطل 🔇' ?>
                            </strong>
                        </span>
                    </div>
                </div>

            </div>
        </div>

        <!-- ===== زر العودة ===== -->
        <div class="mt-6">
            <a href="<?= SITE_URL ?>/profile.php" 
               class="inline-flex items-center gap-2 text-gray-500 hover:text-gray-700 transition-colors text-sm">
                <i class="fas fa-arrow-right"></i> العودة للملف الشخصي
            </a>
        </div>

    </div>
</div>

<script>
// ============================================================
// دوال تحديث الإعدادات
// ============================================================

var SITE_URL = '<?= SITE_URL ?>';
var USER_TYPE = '<?= $user_type ?>';

function updateSound(checked) {
    var sound = checked ? 1 : 0;
    
    fetch(SITE_URL + '/includes/notification_system.php?notification_action=update_alert', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            alert_sound: sound,
            user_type: USER_TYPE
        })
    })
    .then(function(response) { return response.json(); })
    .then(function(data) {
        if (data.status === 'success') {
            document.getElementById('settings-sound-text').textContent = checked ? 'مفعل 🔊' : 'معطل 🔇';
            if (data.settings) {
                localStorage.setItem('user_settings', JSON.stringify(data.settings));
            }
            if (checked) {
                try {
                    var audio = new Audio(SITE_URL + '/assets/sounds/alert.mp3');
                    audio.volume = 0.3;
                    audio.play().catch(function() {});
                } catch(e) {}
            }
            showToast(checked ? '🔊 تم تفعيل الصوت' : '🔇 تم إلغاء الصوت');
        }
    })
    .catch(function() {
        showToast('❌ حدث خطأ، حاول مرة أخرى');
    });
}

function updateAlerts(checked) {
    var state = checked ? 1 : 0;
    
    fetch(SITE_URL + '/includes/notification_system.php?notification_action=update_alert', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            is_active: state,
            user_type: USER_TYPE
        })
    })
    .then(function(response) { return response.json(); })
    .then(function(data) {
        if (data.status === 'success') {
            document.getElementById('settings-status-text').textContent = checked ? 'مفعلة ✅' : 'معطلة ❌';
            if (data.settings) {
                localStorage.setItem('user_settings', JSON.stringify(data.settings));
            }
            showToast(checked ? '✅ تم تفعيل التنبيهات' : '❌ تم إلغاء التنبيهات');
        }
    })
    .catch(function() {
        showToast('❌ حدث خطأ، حاول مرة أخرى');
    });
}

function showToast(message) {
    var old = document.querySelector('.settings-toast');
    if (old) old.remove();
    
    var toast = document.createElement('div');
    toast.className = 'settings-toast fixed bottom-4 left-1/2 transform -translate-x-1/2 bg-gray-800 text-white px-6 py-3 rounded-xl text-sm shadow-lg z-50 transition-all duration-300';
    toast.textContent = message;
    document.body.appendChild(toast);
    
    setTimeout(function() {
        toast.style.opacity = '0';
        setTimeout(function() { toast.remove(); }, 300);
    }, 2500);
}
</script>

<?php include 'includes/footer.php'; ?>