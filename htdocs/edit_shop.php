<?php
require_once 'config.php';

// التأكد من تسجيل الدخول وأن المستخدم محل
if (!isLoggedIn() || getUserType() !== 'shop') {
    redirect('login.php');
}

$user_id = $_SESSION['user_id'];

// جلب بيانات المحل
$stmt = $pdo->prepare("SELECT * FROM shops WHERE user_id = ?");
$stmt->execute([$user_id]);
$shop = $stmt->fetch();

if (!$shop) {
    redirect('index.php');
}

$error = '';
$success = '';

// معالجة تحديث المحل
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $shop_name = clean($_POST['shop_name'] ?? '');
    $description = clean($_POST['description'] ?? '');
    $phone = clean($_POST['phone'] ?? '');
    $whatsapp = clean($_POST['whatsapp'] ?? '');
    $country_id = (int)($_POST['country_id'] ?? 0);
    $city_id = (int)($_POST['city_id'] ?? 0);
    $address = clean($_POST['address'] ?? '');
    $latitude = (float)($_POST['latitude'] ?? 0);
    $longitude = (float)($_POST['longitude'] ?? 0);

    if (empty($shop_name)) {
        $error = 'يرجى إدخال اسم المحل';
    } else {
        try {
            $stmt = $pdo->prepare("
                UPDATE shops 
                SET shop_name = ?, description = ?, phone = ?, whatsapp = ?, 
                    country_id = ?, city_id = ?, address = ?, 
                    latitude = ?, longitude = ?
                WHERE user_id = ?
            ");
            $stmt->execute([$shop_name, $description, $phone, $whatsapp, 
                           $country_id, $city_id, $address, 
                           $latitude, $longitude, $user_id]);

            $success = '✅ تم التسجيل بنجاح / سيتم المراجعة من قبل الإدارة ونشر المتجر في قائمة المحلات!';
            
            // تحديث البيانات المعروضة
            $shop['shop_name'] = $shop_name;
            $shop['description'] = $description;
            $shop['phone'] = $phone;
            $shop['whatsapp'] = $whatsapp;
            $shop['country_id'] = $country_id;
            $shop['city_id'] = $city_id;
            $shop['address'] = $address;
            $shop['latitude'] = $latitude;
            $shop['longitude'] = $longitude;

        } catch (Exception $e) {
            $error = 'حدث خطأ: ' . $e->getMessage();
        }
    }
}

// جلب الدول والمدن
$countries = getCountries();
$cities = [];
if ($shop['country_id'] > 0) {
    $cities = getCities($shop['country_id']);
}

$page_title = 'إعدادات المحل';
include 'includes/header.php';
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إعدادات المحل - مزاد البناء</title>
    <!-- Leaflet CSS (خريطة مجانية) -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/lucide@0.263.0/dist/umd/lucide.min.js"></script>
    <style>
        * { 
            font-family: 'DM Sans', sans-serif; 
            box-sizing: border-box;
        }
        
        body {
            min-height: 100vh;
            margin: 0;
            padding: 0;
            background: #1a0e0a;
            position: relative;
            overflow-x: hidden;
        }
        
        .main-content {
            position: relative;
            z-index: 1;
            padding: 16px 0 60px 0;
            min-height: 100vh;
        }
        
        .form-card {
            background: #ffffff;
            border-radius: 16px;
            border: 1px solid #e8edf2;
            padding: 24px;
            max-width: 700px;
            margin: 0 auto;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
        }
        
        .form-card h2 {
            color: #0f172a;
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 20px;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .form-card h2 i { color: #2563eb; }
        
        .form-group {
            margin-bottom: 16px;
        }
        
        .form-group label {
            display: block;
            font-weight: 600;
            font-size: 13px;
            color: #334155;
            margin-bottom: 4px;
        }
        
        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 10px 14px;
            border: 2px solid #e8edf2;
            border-radius: 10px;
            font-size: 14px;
            transition: all 0.25s ease;
            background: #f8fafc;
            color: #0f172a;
        }
        
        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            border-color: #2563eb;
            outline: none;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
            background: #ffffff;
        }
        
        .form-group textarea {
            min-height: 80px;
            resize: vertical;
        }
        
        .form-group .help-text {
            font-size: 11px;
            color: #94a3b8;
            margin-top: 4px;
        }
        
        .btn-save {
            background: #2563eb;
            color: #ffffff;
            border: none;
            padding: 12px 24px;
            border-radius: 10px;
            font-weight: 700;
            font-size: 15px;
            cursor: pointer;
            transition: all 0.25s ease;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .btn-save:hover {
            background: #1d4ed8;
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(37, 99, 235, 0.25);
        }
        
        .alert-success {
            background: #dcfce7;
            color: #166534;
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 16px;
            border: 1px solid #86efac;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 16px;
            border: 1px solid #fca5a5;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        /* ============================================ */
        /* الخريطة */
        /* ============================================ */
        #map {
            height: 280px;
            border-radius: 12px;
            border: 2px solid #e8edf2;
            margin-bottom: 8px;
            z-index: 1;
        }
        
        .map-coords {
            display: flex;
            gap: 12px;
            font-size: 12px;
            color: #64748b;
            flex-wrap: wrap;
        }
        
        .map-coords span {
            background: #f1f5f9;
            padding: 4px 14px;
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        
        .map-coords .coord-value {
            font-weight: 700;
            color: #0f172a;
        }
        
        .map-instruction {
            font-size: 12px;
            color: #94a3b8;
            margin-top: 4px;
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
        }
        
        .btn-locate {
            background: #f1f5f9;
            border: 1px solid #e8edf2;
            color: #475569;
            padding: 6px 14px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.25s ease;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        
        .btn-locate:hover {
            background: #e8edf2;
        }
        
        .btn-locate:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        .location-note {
            background: #fef3c7;
            border: 1px solid #fcd34d;
            border-radius: 8px;
            padding: 8px 12px;
            font-size: 12px;
            color: #92400e;
            margin-top: 6px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        @media (max-width: 480px) {
            .form-card {
                padding: 16px;
            }
            #map {
                height: 200px;
            }
            .map-coords {
                font-size: 10px;
                gap: 6px;
            }
            .map-coords span {
                padding: 2px 10px;
            }
        }
    </style>
</head>
<body>

<div class="main-content">
    <div class="max-w-7xl mx-auto px-3 md:px-6">
        
        <div class="form-card">
            <h2><i class="fas fa-store"></i> إعدادات المحل</h2>
            
            <?php if ($success): ?>
            <div class="alert-success">
                <i class="fas fa-check-circle"></i>
                <?= $success ?>
            </div>
            <?php endif; ?>
            <?php if ($error): ?>
            <div class="alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?= $error ?>
            </div>
            <?php endif; ?>
            
            <form method="POST" action="" id="shopForm">
                <!-- اسم المحل -->
                <div class="form-group">
                    <label>🏪 اسم المحل <span class="text-red-500">*</span></label>
                    <input type="text" name="shop_name" value="<?= clean($shop['shop_name']) ?>" required>
                </div>
                
                <!-- وصف المحل -->
                <div class="form-group">
                    <label>📝 وصف المحل</label>
                    <textarea name="description" rows="3"><?= clean($shop['description']) ?></textarea>
                </div>
                
                <!-- الهاتف -->
                <div class="form-group">
                    <label>📞 رقم الهاتف</label>
                    <input type="text" name="phone" value="<?= clean($shop['phone']) ?>" placeholder="05xxxxxxxx">
                </div>
                
                <!-- واتساب -->
                <div class="form-group">
                    <label>💬 رقم واتساب</label>
                    <input type="text" name="whatsapp" value="<?= clean($shop['whatsapp']) ?>" placeholder="05xxxxxxxx">
                    <div class="help-text">سيظهر زر واتساب في صفحة المحل للتواصل المباشر</div>
                </div>
                
                <!-- الدولة -->
                <div class="form-group">
                    <label>🌍 الدولة</label>
                    <select name="country_id" id="edit-country" onchange="loadCities('edit-country', 'edit-city')">
                        <option value="0">اختر الدولة</option>
                        <?php foreach ($countries as $country): ?>
                        <option value="<?= $country['id'] ?>" <?= ($shop['country_id'] == $country['id']) ? 'selected' : '' ?>>
                            <?= $country['flag'] ?? '' ?> <?= $country['name_ar'] ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- المدينة -->
                <div class="form-group">
                    <label>🏙️ المدينة</label>
                    <select name="city_id" id="edit-city">
                        <option value="0">اختر المدينة</option>
                        <?php foreach ($cities as $city): ?>
                        <option value="<?= $city['id'] ?>" <?= ($shop['city_id'] == $city['id']) ? 'selected' : '' ?>>
                            <?= $city['name_ar'] ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- العنوان التفصيلي + الخريطة -->
                <div class="form-group">
                    <label>📍 العنوان التفصيلي</label>
                    <input type="text" name="address" id="addressInput" value="<?= clean($shop['address']) ?>" placeholder="الحي - الشارع - رقم المبنى">
                </div>
                
                <div class="form-group">
                    <label>🗺️ حدد موقع المحل على الخريطة</label>
                    <div id="map"></div>
                    
                    <div class="map-coords">
                        <span>
                            <i class="fas fa-arrow-up"></i> خط العرض: 
                            <span class="coord-value" id="latDisplay"><?= $shop['latitude'] ? number_format($shop['latitude'], 6) : 'غير محدد' ?></span>
                        </span>
                        <span>
                            <i class="fas fa-arrow-right"></i> خط الطول: 
                            <span class="coord-value" id="lngDisplay"><?= $shop['longitude'] ? number_format($shop['longitude'], 6) : 'غير محدد' ?></span>
                        </span>
                        <button type="button" class="btn-locate" id="locateBtn" onclick="getCurrentLocation()">
                            <i class="fas fa-crosshairs"></i> موقعي الحالي
                        </button>
                    </div>
                    
                    <div class="map-instruction">
                        <i class="fas fa-hand-pointer text-blue-500"></i>
                        اضغط على الخريطة لتحديد موقع المحل
                        <span style="font-size:11px; color:#94a3b8; margin-right:6px;">|</span>
                        <span style="font-size:11px; color:#94a3b8;">أو استخدم زر "موقعي الحالي"</span>
                    </div>
                    
                    <!-- ✅ ملاحظة حول عمل الموقع -->
                    <div class="location-note">
                        <i class="fas fa-info-circle"></i>
                        <span>إذا لم يعمل زر "موقعي الحالي"، يمكنك الضغط على الخريطة يدوياً لتحديد الموقع.</span>
                    </div>
                    
                    <!-- حقول مخفية للإحداثيات -->
                    <input type="hidden" name="latitude" id="latitudeInput" value="<?= $shop['latitude'] ?? 0 ?>">
                    <input type="hidden" name="longitude" id="longitudeInput" value="<?= $shop['longitude'] ?? 0 ?>">
                </div>
                
                <button type="submit" class="btn-save">
                    <i class="fas fa-save"></i> حفظ التغييرات
                </button>
            </form>
        </div>
        
    </div>
</div>

<script>
    lucide.createIcons();

    // ============================================
    // تهيئة الخريطة
    // ============================================
    let map;
    let marker;
    const defaultLat = <?= $shop['latitude'] ?: 24.7136 ?>;
    const defaultLng = <?= $shop['longitude'] ?: 46.6753 ?>;

    function initMap() {
        map = L.map('map').setView([defaultLat, defaultLng], 13);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
        }).addTo(map);

        <?php if ($shop['latitude'] && $shop['longitude']): ?>
        marker = L.marker([<?= $shop['latitude'] ?>, <?= $shop['longitude'] ?>]).addTo(map);
        <?php endif; ?>

        map.on('click', function(e) {
            const lat = e.latlng.lat;
            const lng = e.latlng.lng;
            updateLocation(lat, lng);
        });
    }

    // ============================================
    // تحديث الموقع
    // ============================================
    function updateLocation(lat, lng) {
        if (marker) {
            map.removeLayer(marker);
        }
        marker = L.marker([lat, lng]).addTo(map);
        map.setView([lat, lng], 15);

        document.getElementById('latitudeInput').value = lat;
        document.getElementById('longitudeInput').value = lng;
        document.getElementById('latDisplay').textContent = lat.toFixed(6);
        document.getElementById('lngDisplay').textContent = lng.toFixed(6);
    }

    // ============================================
    // ✅ الحصول على الموقع الحالي (يعمل على HTTP و HTTPS)
    // ============================================
    function getCurrentLocation() {
        const btn = document.getElementById('locateBtn');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> جاري التحديد...';

        // ============================================
        // المحاولة 1: استخدام Geolocation API (يعمل فقط على HTTPS أو localhost)
        // ============================================
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(
                function(position) {
                    const lat = position.coords.latitude;
                    const lng = position.coords.longitude;
                    updateLocation(lat, lng);
                    fetchAddressFromCoords(lat, lng);
                    btn.innerHTML = '<i class="fas fa-crosshairs"></i> موقعي الحالي';
                    btn.disabled = false;
                    showToast('تم تحديد موقعك بدقة عالية ✅', 'success');
                },
                function(error) {
                    console.warn('Geolocation API failed:', error.message);
                    // إذا فشل Geolocation API، ننتقل إلى البديل
                    getLocationByIP(btn);
                },
                { enableHighAccuracy: true, timeout: 10000 }
            );
        } else {
            // المتصفح لا يدعم Geolocation API
            getLocationByIP(btn);
        }
    }

    // ============================================
    // المحاولة 2: تحديد الموقع عبر IP (يعمل على HTTP و HTTPS)
    // ============================================
    function getLocationByIP(btn) {
        // استخدام خدمة ipapi.co (مجانية، لا تحتاج مفتاح)
        fetch('https://ipapi.co/json/')
            .then(response => {
                if (!response.ok) throw new Error('Network response was not ok');
                return response.json();
            })
            .then(data => {
                if (data.latitude && data.longitude) {
                    const lat = parseFloat(data.latitude);
                    const lng = parseFloat(data.longitude);
                    updateLocation(lat, lng);
                    // جلب العنوان من الإحداثيات (عكسي)
                    fetchAddressFromCoords(lat, lng);
                    btn.innerHTML = '<i class="fas fa-crosshairs"></i> موقعي الحالي';
                    btn.disabled = false;
                    showToast('تم تحديد موقعك (تقريبي) ✅', 'info');
                } else {
                    throw new Error('No coordinates from IP');
                }
            })
            .catch(error => {
                console.error('IP location error:', error);
                btn.innerHTML = '<i class="fas fa-crosshairs"></i> موقعي الحالي';
                btn.disabled = false;
                showToast('لم نتمكن من تحديد موقعك تلقائياً. يرجى النقر على الخريطة يدوياً.', 'error');
            });
    }

    // ============================================
    // جلب العنوان من الإحداثيات (عكسي)
    // ============================================
    function fetchAddressFromCoords(lat, lng) {
        const url = `https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&zoom=18&addressdetails=1&accept-language=ar`;
        fetch(url)
            .then(response => response.json())
            .then(data => {
                if (data.display_name) {
                    document.getElementById('addressInput').value = data.display_name;
                }
            })
            .catch(error => console.error('Error fetching address:', error));
    }

    // ============================================
    // عرض رسائل منبثقة (Toast)
    // ============================================
    function showToast(message, type = 'success') {
        const container = document.getElementById('toast-container');
        if (!container) {
            // إذا لم يكن هناك حاوية، نستخدم alert بسيط
            alert(message);
            return;
        }
        const toast = document.createElement('div');
        toast.className = `toast ${type}`;
        toast.innerHTML = `<span>${message}</span>`;
        container.appendChild(toast);
        setTimeout(() => toast.remove(), 4000);
    }

    // ============================================
    // تحميل المدن
    // ============================================
    function loadCities(countrySelectId, citySelectId) {
        const countrySelect = document.getElementById(countrySelectId);
        const citySelect = document.getElementById(citySelectId);
        const countryId = countrySelect.value;
        
        citySelect.innerHTML = '<option value="0">جاري التحميل...</option>';
        if (!countryId || countryId == 0) {
            citySelect.innerHTML = '<option value="0">اختر المدينة</option>';
            return;
        }
        
        fetch('<?= SITE_URL ?>/api.php?action=get_cities&country_id=' + countryId)
            .then(response => response.json())
            .then(data => {
                citySelect.innerHTML = '<option value="0">اختر المدينة</option>';
                if (data.status === 'success' && data.data && data.data.length > 0) {
                    data.data.forEach(city => {
                        const option = document.createElement('option');
                        option.value = city.id;
                        option.textContent = city.name_ar;
                        citySelect.appendChild(option);
                    });
                }
            })
            .catch(error => {
                console.error('Error:', error);
                citySelect.innerHTML = '<option value="0">اختر المدينة</option>';
            });
    }

    // ============================================
    // تشغيل الخريطة عند تحميل الصفحة
    // ============================================
    document.addEventListener('DOMContentLoaded', function() {
        initMap();
        
        // إذا كان الموقع يعمل على HTTP، نعرض رسالة توجيهية في الكونسول
        if (window.location.protocol === 'http:' && window.location.hostname !== 'localhost') {
            console.info('🔒 للحصول على تحديد موقع دقيق، يرجى تفعيل HTTPS على موقعك.');
        }
    });
</script>

<?php include 'includes/footer.php'; ?>
</body>
</html>