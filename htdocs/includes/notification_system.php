<?php
/**
 * =============================================================
 * نظام الإشعارات المتكامل – الإصدار النهائي (متوافق مع الجوال)
 * =============================================================
 * 
 * كيفية الاستخدام:
 * 1. ضع هذا الملف في مجلد includes/
 * 2. أضف السطر التالي في أعلى ملف header.php:
 *    require_once __DIR__ . '/notification_system.php';
 * 3. استخدم الدالة sendNotification() في أي مكان في مشروعك
 * 
 * المميزات:
 * - إرسال إشعارات فورية
 * - عرض Toast مع صوت
 * - عداد الإشعارات
 * - إشعارات سطح المكتب
 * - دعم تعطيل التنبيهات والصوت من الملف الشخصي
 * - منع تكرار الإشعارات
 * - متوافق مع الجوال (AudioContext)
 * 
 * =============================================================
 */

// ============================
// 1. دوال PHP الأساسية
// ============================

if (!function_exists('sendNotification')) {
    function sendNotification($pdo, $recipientId, $title, $message, $link = '', $type = 'info') {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO notifications (user_id, title, message, link, type, created_at) 
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$recipientId, $title, $message, $link, $type]);
            return true;
        } catch (PDOException $e) {
            error_log("Notification error: " . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('getUnreadNotificationsCount')) {
    function getUnreadNotificationsCount($pdo, $userId) {
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
            $stmt->execute([$userId]);
            return (int) $stmt->fetchColumn();
        } catch (Exception $e) {
            return 0;
        }
    }
}

if (!function_exists('markAllNotificationsAsRead')) {
    function markAllNotificationsAsRead($pdo, $userId) {
        try {
            $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
            $stmt->execute([$userId]);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
}

if (!function_exists('getUserAlertSettings')) {
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
            return null;
        }
    }
}

if (!function_exists('updateAlertSettings')) {
    function updateAlertSettings($pdo, $userId, $userType, $data) {
        $table = ($userType === 'contractor') ? 'contractor_alerts' : 'employer_alerts';
        $idField = ($userType === 'contractor') ? 'contractor_id' : 'employer_id';
        
        try {
            if (isset($data['is_active'])) {
                $stmt = $pdo->prepare("UPDATE $table SET is_active = ? WHERE $idField = ?");
                $stmt->execute([$data['is_active'], $userId]);
            }
            if (isset($data['alert_sound'])) {
                $stmt = $pdo->prepare("UPDATE $table SET alert_sound = ? WHERE $idField = ?");
                $stmt->execute([$data['alert_sound'], $userId]);
            }
            if (isset($data['alert_email'])) {
                $stmt = $pdo->prepare("UPDATE $table SET alert_email = ? WHERE $idField = ?");
                $stmt->execute([$data['alert_email'], $userId]);
            }
            return true;
        } catch (Exception $e) {
            error_log("Update alert settings error: " . $e->getMessage());
            return false;
        }
    }
}

// ============================
// 2. معالج API المدمج
// ============================

if (isset($_GET['notification_action']) || isset($_POST['notification_action'])) {
    define('NOTIFICATION_API_CALL', true);
    
    header('Content-Type: application/json');
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
    
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (!defined('SITE_URL')) {
        require_once __DIR__ . '/../config.php';
    }
    
    if (!function_exists('isLoggedIn') || !isLoggedIn()) {
        echo json_encode(['status' => 'error', 'message' => 'غير مسجل دخول']);
        exit;
    }
    
    $action = $_GET['notification_action'] ?? $_POST['notification_action'] ?? '';
    $userId = (int) $_SESSION['user_id'];
    $userType = $_SESSION['user_type'] ?? '';
    $response = ['status' => 'error', 'message' => 'إجراء غير معروف'];
    
    try {
        switch ($action) {
            case 'get_notifications':
                $lastId = isset($_GET['last_id']) ? (int)$_GET['last_id'] : 0;
                $settings = getUserAlertSettings($pdo, $userId, $userType);
                
                $stmt = $pdo->prepare("
                    SELECT id, title, message, link, type, created_at 
                    FROM notifications 
                    WHERE user_id = ? AND id > ? AND is_read = 0
                    ORDER BY id DESC
                ");
                $stmt->execute([$userId, $lastId]);
                $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                if (!empty($notifications)) {
                    $ids = array_column($notifications, 'id');
                    $placeholders = implode(',', array_fill(0, count($ids), '?'));
                    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id IN ($placeholders)");
                    $stmt->execute($ids);
                }
                
                $unreadCount = getUnreadNotificationsCount($pdo, $userId);
                $response = [
                    'status' => 'success',
                    'notifications' => $notifications,
                    'unread_count' => $unreadCount,
                    'settings' => $settings
                ];
                break;
            
            case 'mark_all_read':
                markAllNotificationsAsRead($pdo, $userId);
                $response = ['status' => 'success', 'message' => 'تم تعليم الكل كمقروء'];
                break;
            
            case 'update_alert':
                $input = json_decode(file_get_contents('php://input'), true);
                if (updateAlertSettings($pdo, $userId, $userType, $input)) {
                    $newSettings = getUserAlertSettings($pdo, $userId, $userType);
                    $response = [
                        'status' => 'success',
                        'message' => 'تم تحديث الإعدادات',
                        'settings' => $newSettings
                    ];
                } else {
                    $response = ['status' => 'error', 'message' => 'فشل تحديث الإعدادات'];
                }
                break;
            
            default:
                $response = ['status' => 'error', 'message' => 'إجراء غير معروف'];
        }
    } catch (Exception $e) {
        error_log("Notification API error: " . $e->getMessage());
        $response = ['status' => 'error', 'message' => 'حدث خطأ في الخادم'];
    }
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

// ============================
// 3. كود JavaScript (يتم حقنه في الصفحة)
// ============================

if (!defined('NOTIFICATION_API_CALL')) {
    if (function_exists('isLoggedIn') && isLoggedIn()) {
        global $pdo;
        if (!isset($pdo)) {
            if (file_exists(__DIR__ . '/../config.php')) {
                require_once __DIR__ . '/../config.php';
            }
        }
        
        $siteUrl = defined('SITE_URL') ? SITE_URL : '';
        $userId = (int) $_SESSION['user_id'];
        $userType = $_SESSION['user_type'] ?? '';
        $settings = getUserAlertSettings($pdo, $userId, $userType);
        $unreadCount = getUnreadNotificationsCount($pdo, $userId);
        ?>
        <style>
            .notification-toast {
                direction: rtl;
                font-family: inherit;
                max-width: 400px;
                min-width: 280px;
                box-shadow: 0 10px 40px rgba(0,0,0,0.15);
                animation: notificationSlideIn 0.5s ease;
            }
            .notification-toast .toast-content {
                display: flex;
                align-items: flex-start;
                gap: 12px;
            }
            .notification-toast .toast-icon {
                flex-shrink: 0;
                width: 40px;
                height: 40px;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 18px;
            }
            .notification-toast .toast-body {
                flex: 1;
                min-width: 0;
            }
            .notification-toast .toast-title {
                font-weight: 700;
                font-size: 14px;
                color: #1e293b;
                margin-bottom: 4px;
            }
            .notification-toast .toast-message {
                font-size: 13px;
                color: #475569;
                line-height: 1.5;
                word-break: break-word;
            }
            .notification-toast .toast-link {
                display: inline-block;
                margin-top: 6px;
                font-size: 12px;
                font-weight: 600;
                text-decoration: none;
            }
            .notification-toast .toast-close {
                flex-shrink: 0;
                background: none;
                border: none;
                color: #94a3b8;
                cursor: pointer;
                font-size: 16px;
                padding: 4px;
                transition: color 0.2s;
            }
            .notification-toast .toast-close:hover {
                color: #475569;
            }
            @keyframes notificationSlideIn {
                from { transform: translateX(100%); opacity: 0; }
                to { transform: translateX(0); opacity: 1; }
            }
            @keyframes notificationSlideOut {
                from { transform: translateX(0); opacity: 1; }
                to { transform: translateX(100%); opacity: 0; }
            }
            .notification-toast.slide-out {
                animation: notificationSlideOut 0.4s ease forwards;
            }
            @media (max-width: 640px) {
                .notification-toast {
                    max-width: 90%;
                    min-width: unset;
                    right: 5%;
                    left: 5%;
                    top: 10px;
                }
            }
        </style>
        
        <script>
        (function() {
            'use strict';
            
            // ============================================================
            // المتغيرات الأساسية
            // ============================================================
            const SITE_URL = '<?= $siteUrl ?>';
            const USER_TYPE = '<?= $userType ?>';
            let lastNotificationId = parseInt(localStorage.getItem('last_notification_id')) || 0;
            let isFetching = false;
            
            // ============================================================
            // إعدادات المستخدم (يتم تحديثها من الخادم ومن localStorage)
            // ============================================================
            let userSettings = {
                is_active: 1,
                alert_sound: 1,
                alert_email: 1
            };
            
            try {
                const saved = localStorage.getItem('user_settings');
                if (saved) {
                    const parsed = JSON.parse(saved);
                    userSettings = { ...userSettings, ...parsed };
                }
            } catch(e) {}
            
            const phpSettings = {
                is_active: <?= $settings['is_active'] ?? 1 ?>,
                alert_sound: <?= $settings['alert_sound'] ?? 1 ?>,
                alert_email: <?= $settings['alert_email'] ?? 1 ?>
            };
            userSettings = { ...userSettings, ...phpSettings };
            localStorage.setItem('user_settings', JSON.stringify(userSettings));
            
            console.log('🔔 إعدادات المستخدم الأولية:', userSettings);
            
            // ============================================================
            // تهيئة الصوت (متوافق مع الجوال)
            // ============================================================
            let audioContext = null;
            let notificationSound = null;
            
            function initAudio() {
                try {
                    if (window.AudioContext || window.webkitAudioContext) {
                        audioContext = new (window.AudioContext || window.webkitAudioContext)();
                        const xhr = new XMLHttpRequest();
                        xhr.open('GET', SITE_URL + '/assets/sounds/alert.mp3', true);
                        xhr.responseType = 'arraybuffer';
                        xhr.onload = function() {
                            if (xhr.status === 200) {
                                audioContext.decodeAudioData(xhr.response, function(buffer) {
                                    notificationSound = buffer;
                                    console.log('✅ تم تحميل الصوت بنجاح (AudioContext)');
                                }, function(e) {
                                    console.log('❌ فشل فك تشفير الصوت:', e);
                                });
                            }
                        };
                        xhr.onerror = function() {
                            console.log('❌ فشل تحميل الصوت');
                        };
                        xhr.send();
                    } else {
                        notificationSound = new Audio(SITE_URL + '/assets/sounds/alert.mp3');
                        notificationSound.volume = 0.5;
                        notificationSound.preload = 'auto';
                        console.log('✅ تم تهيئة الصوت بالطريقة التقليدية');
                    }
                } catch(e) {
                    console.log('❌ خطأ في تهيئة الصوت:', e);
                    notificationSound = null;
                }
            }
            
            // تهيئة الصوت عند أول تفاعل للمستخدم
            document.addEventListener('click', initAudio, { once: true });
            document.addEventListener('touchstart', initAudio, { once: true });
            setTimeout(initAudio, 5000);
            
            // ============================================================
            // تحديث العداد
            // ============================================================
            function updateBadge(count) {
                const badge = document.getElementById('notification-badge');
                if (badge) {
                    if (count > 0) {
                        badge.textContent = count > 99 ? '99+' : count;
                        badge.classList.remove('hidden');
                        badge.style.display = 'flex';
                    } else {
                        badge.classList.add('hidden');
                        badge.style.display = 'none';
                    }
                }
                document.querySelectorAll('.notification-badge-count').forEach(function(el) {
                    if (count > 0) {
                        el.textContent = count > 99 ? '99+' : count;
                        el.style.display = 'inline-flex';
                    } else {
                        el.style.display = 'none';
                    }
                });
            }
            
            updateBadge(<?= $unreadCount ?>);
            
            // ============================================================
            // عرض التوست
            // ============================================================
            function showToast(title, message, link, type) {
                const colors = {
                    info: { bg: '#eff6ff', border: '#3b82f6', text: '#1e40af', icon: 'fa-info-circle' },
                    success: { bg: '#ecfdf5', border: '#22c55e', text: '#166534', icon: 'fa-check-circle' },
                    warning: { bg: '#fffbeb', border: '#f59e0b', text: '#92400e', icon: 'fa-exclamation-triangle' },
                    error: { bg: '#fef2f2', border: '#ef4444', text: '#991b1b', icon: 'fa-times-circle' }
                };
                const color = colors[type] || colors.info;
                
                const old = document.querySelector('.notification-toast');
                if (old) {
                    old.classList.add('slide-out');
                    setTimeout(function() { old.remove(); }, 400);
                }
                
                const toast = document.createElement('div');
                toast.className = 'notification-toast';
                toast.style.cssText = [
                    'position: fixed',
                    'top: 20px',
                    'right: 20px',
                    'background: #ffffff',
                    'border-radius: 12px',
                    'padding: 16px 18px',
                    'z-index: 99999',
                    'box-shadow: 0 10px 40px rgba(0,0,0,0.15)',
                    'border-right: 4px solid ' + color.border,
                    'max-width: 420px',
                    'min-width: 280px',
                    'direction: rtl',
                    'font-family: inherit'
                ].join(';');
                
                toast.innerHTML = `
                    <div style="display:flex;align-items:flex-start;gap:12px;">
                        <div style="flex-shrink:0;width:40px;height:40px;border-radius:50%;background:${color.bg};display:flex;align-items:center;justify-content:center;font-size:18px;color:${color.border};">
                            <i class="fas ${color.icon}"></i>
                        </div>
                        <div style="flex:1;min-width:0;">
                            <div style="font-weight:700;font-size:14px;color:#1e293b;margin-bottom:4px;">${title}</div>
                            <div style="font-size:13px;color:#475569;line-height:1.5;word-break:break-word;">${message}</div>
                            ${link ? `<a href="${link}" style="display:inline-block;margin-top:6px;font-size:12px;font-weight:600;color:${color.border};text-decoration:none;">عرض التفاصيل ←</a>` : ''}
                        </div>
                        <button onclick="this.parentElement.parentElement.remove()" style="flex-shrink:0;background:none;border:none;color:#94a3b8;cursor:pointer;font-size:16px;padding:4px;">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                `;
                
                document.body.appendChild(toast);
                
                setTimeout(function() {
                    if (toast.parentElement) {
                        toast.classList.add('slide-out');
                        setTimeout(function() { 
                            if (toast.parentElement) toast.remove(); 
                        }, 400);
                    }
                }, 8000);
            }
            
            // ============================================================
            // تشغيل الصوت (مع التحقق الصارم من الإعدادات)
            // ============================================================
            function playSound() {
                console.log('🔊 محاولة تشغيل الصوت، alert_sound =', userSettings.alert_sound);
                
                if (userSettings.alert_sound !== 1) {
                    console.log('🔇 الصوت معطل (alert_sound = ' + userSettings.alert_sound + ')');
                    return;
                }
                
                if (!notificationSound && !audioContext) {
                    console.log('🔇 الصوت غير جاهز، محاولة التهيئة...');
                    initAudio();
                    return;
                }
                
                try {
                    if (audioContext && notificationSound) {
                        const source = audioContext.createBufferSource();
                        source.buffer = notificationSound;
                        const gainNode = audioContext.createGain();
                        gainNode.gain.value = 0.5;
                        source.connect(gainNode);
                        gainNode.connect(audioContext.destination);
                        source.start(0);
                        console.log('🔊 تم تشغيل الصوت (AudioContext)');
                    } else if (notificationSound && typeof notificationSound.play === 'function') {
                        notificationSound.currentTime = 0;
                        notificationSound.play().catch(function(e) {
                            console.log('Sound play error:', e);
                        });
                        console.log('🔊 تم تشغيل الصوت (Audio)');
                    } else {
                        console.log('❌ لا يمكن تشغيل الصوت - غير جاهز');
                    }
                } catch(e) {
                    console.log('❌ خطأ في تشغيل الصوت:', e);
                }
            }
            
            // ============================================================
            // عرض إشعار سطح المكتب
            // ============================================================
            function showDesktopNotification(title, message, link) {
                if (!('Notification' in window) || Notification.permission !== 'granted') return;
                try {
                    const notif = new Notification(title, {
                        body: message,
                        icon: SITE_URL + '/assets/images/logo.png',
                        tag: 'notification_' + Date.now(),
                        requireInteraction: false
                    });
                    notif.onclick = function() {
                        if (link) window.location.href = link;
                        notif.close();
                    };
                    setTimeout(function() { notif.close(); }, 10000);
                } catch(e) {}
            }
            
            // ============================================================
            // جلب الإشعارات الجديدة
            // ============================================================
            function fetchNewNotifications() {
                if (userSettings.is_active !== 1) {
                    console.log('🔕 التنبيهات معطلة (is_active = ' + userSettings.is_active + ')');
                    return;
                }
                if (isFetching) return;
                isFetching = true;
                
                const url = SITE_URL + '/includes/notification_system.php?notification_action=get_notifications&last_id=' + lastNotificationId;
                
                fetch(url, {
                    method: 'GET',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Cache-Control': 'no-cache'
                    }
                })
                .then(function(response) { return response.json(); })
                .then(function(data) {
                    isFetching = false;
                    if (data.status === 'success') {
                        if (data.settings) {
                            userSettings = { ...userSettings, ...data.settings };
                            localStorage.setItem('user_settings', JSON.stringify(userSettings));
                            console.log('📥 تم تحديث الإعدادات من الخادم:', userSettings);
                        }
                        if (data.unread_count !== undefined) {
                            updateBadge(data.unread_count);
                        }
                        if (data.notifications && data.notifications.length > 0) {
                            data.notifications.forEach(function(notif) {
                                if (notif.id > lastNotificationId) {
                                    showToast(notif.title, notif.message, notif.link, notif.type);
                                    showDesktopNotification(notif.title, notif.message, notif.link);
                                    playSound();
                                    if (notif.id > lastNotificationId) {
                                        lastNotificationId = notif.id;
                                        localStorage.setItem('last_notification_id', String(lastNotificationId));
                                    }
                                }
                            });
                        }
                    }
                })
                .catch(function() {
                    isFetching = false;
                });
            }
            
            // ============================================================
            // دوال للاستخدام الخارجي (من profile.php)
            // ============================================================
            
            window.updateAlertSettings = function(userType, sound, email) {
                console.log('📤 تحديث إعدادات الصوت إلى:', sound);
                fetch(SITE_URL + '/includes/notification_system.php?notification_action=update_alert', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        alert_sound: sound ? 1 : 0,
                        alert_email: email ? 1 : 0,
                        user_type: userType
                    })
                })
                .then(function(response) { return response.json(); })
                .then(function(data) {
                    if (data.status === 'success' && data.settings) {
                        userSettings = { ...userSettings, ...data.settings };
                        localStorage.setItem('user_settings', JSON.stringify(userSettings));
                        console.log('📥 تم تحديث الإعدادات محلياً:', userSettings);
                        showToast('✅ تم تحديث الإعدادات', '', '', 'success');
                        if (sound && userSettings.alert_sound === 1) {
                            playSound();
                        }
                    }
                })
                .catch(function() {});
            };
            
            window.toggleAlerts = function(userType, currentState) {
                const newState = currentState ? 0 : 1;
                const btn = document.getElementById('alert-toggle-btn');
                const dot = document.getElementById('alert-toggle-dot');
                
                fetch(SITE_URL + '/includes/notification_system.php?notification_action=update_alert', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        is_active: newState,
                        user_type: userType
                    })
                })
                .then(function(response) { return response.json(); })
                .then(function(data) {
                    if (data.status === 'success' && data.settings) {
                        userSettings = { ...userSettings, ...data.settings };
                        localStorage.setItem('user_settings', JSON.stringify(userSettings));
                        console.log('📥 تم تحديث الإعدادات محلياً:', userSettings);
                        
                        if (btn && dot) {
                            if (newState) {
                                btn.classList.remove('bg-gray-300');
                                btn.classList.add('bg-blue-600');
                                dot.classList.add('translate-x-6');
                                dot.classList.remove('translate-x-1');
                            } else {
                                btn.classList.remove('bg-blue-600');
                                btn.classList.add('bg-gray-300');
                                dot.classList.remove('translate-x-6');
                                dot.classList.add('translate-x-1');
                            }
                            btn.setAttribute('onclick', 'toggleAlerts("' + userType + '", ' + newState + ')');
                        }
                        showToast(newState ? '✅ تم تفعيل التنبيهات' : '❌ تم إلغاء التنبيهات', '', '', 'success');
                        if (newState && userSettings.alert_sound === 1) {
                            playSound();
                        }
                    }
                })
                .catch(function() {});
            };
            
            // ============================================================
            // بدء الجلب الدوري
            // ============================================================
            if ('Notification' in window && Notification.permission === 'default') {
                setTimeout(function() { Notification.requestPermission(); }, 5000);
            }
            
            setTimeout(fetchNewNotifications, 2000);
            setInterval(fetchNewNotifications, 10000);
            
            document.addEventListener('visibilitychange', function() {
                if (!document.hidden) fetchNewNotifications();
            });
            
            console.log('🔔 نظام الإشعارات يعمل - الإعدادات النهائية:', userSettings);
            
        })();
        </script>
        <?php
    }
}
?>