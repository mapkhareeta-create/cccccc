<?php
require_once 'config.php';

// التأكد من تسجيل الدخول
if (!isLoggedIn()) {
    redirect('login.php');
}

$user_id = $_SESSION['user_id'];
$user = getUser($user_id);

$error = '';
$success = '';

// معالجة تحديث البروفايل
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = clean($_POST['name'] ?? '');
    $phone = clean($_POST['phone'] ?? '');
    $whatsapp = clean($_POST['whatsapp'] ?? '');
    $bio = clean($_POST['bio'] ?? '');
    $country_id = (int)($_POST['country_id'] ?? 0);
    $city_id = (int)($_POST['city_id'] ?? 0);
    $address = clean($_POST['address'] ?? '');
    
    if (empty($name)) {
        $error = 'يرجى إدخال الاسم';
    } else {
        try {
    // تحويل القيم الفارغة (0) إلى NULL لتجنب خطأ المفتاح الأجنبي
$country_id = ($country_id > 0) ? $country_id : null;
$city_id = ($city_id > 0) ? $city_id : null;

$stmt = $pdo->prepare("
    UPDATE users 
    SET name = ?, phone = ?, whatsapp = ?, bio = ?, 
        country_id = ?, city_id = ?, address = ?
    WHERE id = ?
");
$stmt->execute([$name, $phone, $whatsapp, $bio, $country_id, $city_id, $address, $user_id]);
            
            // ============================================
            // ✅ رفع صورة الغلاف
            // ============================================
            if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = __DIR__ . '/assets/uploads/covers/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $file_tmp = $_FILES['cover_image']['tmp_name'];
                $file_name = $_FILES['cover_image']['name'];
                $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                $allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                
                if (in_array($file_ext, $allowed_ext)) {
                    $new_filename = 'cover_' . $user_id . '_' . time() . '.' . $file_ext;
                    $destination = $upload_dir . $new_filename;
                    
                    if (move_uploaded_file($file_tmp, $destination)) {
                        // حذف الصورة القديمة
                        if (!empty($user['cover_image']) && file_exists(__DIR__ . '/' . $user['cover_image'])) {
                            unlink(__DIR__ . '/' . $user['cover_image']);
                        }
                        
                        // حفظ المسار في قاعدة البيانات
                        $stmt = $pdo->prepare("UPDATE users SET cover_image = ? WHERE id = ?");
                        $stmt->execute(['assets/uploads/covers/' . $new_filename, $user_id]);
                    }
                }
            }
            
            // ============================================
            // رفع صورة البروفايل (avatar) - موجود مسبقاً
            // ============================================
            if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = __DIR__ . '/assets/uploads/avatars/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $file_tmp = $_FILES['avatar']['tmp_name'];
                $file_name = $_FILES['avatar']['name'];
                $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                $allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                
                if (in_array($file_ext, $allowed_ext)) {
                    $new_filename = 'user_' . $user_id . '_' . time() . '.' . $file_ext;
                    $destination = $upload_dir . $new_filename;
                    
                    if (move_uploaded_file($file_tmp, $destination)) {
                        if (!empty($user['avatar']) && file_exists(__DIR__ . '/' . $user['avatar'])) {
                            unlink(__DIR__ . '/' . $user['avatar']);
                        }
                        $stmt = $pdo->prepare("UPDATE users SET avatar = ? WHERE id = ?");
                        $stmt->execute(['assets/uploads/avatars/' . $new_filename, $user_id]);
                    }
                }
            }
            
            // تحديث الجلسة
            $_SESSION['user_name'] = $name;
            
            $success = '✅ تم تحديث الملف الشخصي بنجاح!';
            
            // تحديث بيانات المستخدم المعروضة
            $user['name'] = $name;
            $user['phone'] = $phone;
            $user['whatsapp'] = $whatsapp;
            $user['bio'] = $bio;
            $user['country_id'] = $country_id;
            $user['city_id'] = $city_id;
            $user['address'] = $address;
            
        } catch (Exception $e) {
            $error = 'حدث خطأ: ' . $e->getMessage();
        }
    }
}

// جلب الدول والمدن
$countries = getCountries();
$cities = [];
if ($user['country_id'] > 0) {
    $cities = getCities($user['country_id']);
}

$page_title = 'تعديل الملف الشخصي';
include 'includes/header.php';
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تعديل الملف الشخصي - مزاد البناء</title>
    <script src="https://cdn.tailwindcss.com/3.4.17"></script>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        * { font-family: 'DM Sans', sans-serif; box-sizing: border-box; }
        
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
        }
        
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
        
        .image-preview {
            width: 100%;
            height: 150px;
            border-radius: 12px;
            overflow: hidden;
            border: 2px solid #e8edf2;
            background: #f8fafc;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #94a3b8;
            font-size: 14px;
            margin-bottom: 8px;
            position: relative;
        }
        .image-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .image-preview .placeholder {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 4px;
        }
        .image-preview .placeholder i {
            font-size: 36px;
            color: #cbd5e1;
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
        
        .upload-btn {
            display: inline-block;
            background: #f1f5f9;
            color: #475569;
            padding: 6px 16px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.25s ease;
            border: 1px solid #e8edf2;
        }
        .upload-btn:hover {
            background: #e8edf2;
        }
        
        @media (max-width: 480px) {
            .form-card { padding: 16px; }
            .image-preview { height: 120px; }
        }
    </style>
</head>
<body>

<div class="main-content">
    <div class="max-w-7xl mx-auto px-3 md:px-6">
        
        <div class="form-card">
            <h2>✏️ تعديل الملف الشخصي</h2>
            
            <?php if ($success): ?>
            <div class="alert-success"><i class="fas fa-check-circle"></i> <?= $success ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
            <div class="alert-error"><i class="fas fa-exclamation-circle"></i> <?= $error ?></div>
            <?php endif; ?>
            
            <form method="POST" action="" enctype="multipart/form-data">
                
                <!-- ===== صورة البروفايل ===== -->
                <div class="form-group">
                    <label>🖼️ صورة البروفايل</label>
                    <div class="image-preview" id="avatarPreview">
                        <?php if (!empty($user['avatar']) && file_exists(__DIR__ . '/' . $user['avatar'])): ?>
                            <img src="<?= SITE_URL . '/' . $user['avatar'] ?>" alt="صورة البروفايل" id="avatarImg">
                        <?php else: ?>
                            <div class="placeholder">
                                <i class="fas fa-user"></i>
                                <span>لا توجد صورة</span>
                            </div>
                        <?php endif; ?>
                    </div>
                    <input type="file" name="avatar" id="avatarInput" accept="image/*" style="display:none;" onchange="previewImage(this, 'avatarPreview', 'avatarImg')">
                    <button type="button" class="upload-btn" onclick="document.getElementById('avatarInput').click()">
                        <i class="fas fa-upload"></i> رفع صورة بروفايل
                    </button>
                </div>
                
                <!-- ===== ✅ صورة الغلاف ===== -->
                <div class="form-group">
                    <label>🖼️ صورة الغلاف (Cover Photo)</label>
                    <div class="image-preview" id="coverPreview">
                        <?php if (!empty($user['cover_image']) && file_exists(__DIR__ . '/' . $user['cover_image'])): ?>
                            <img src="<?= SITE_URL . '/' . $user['cover_image'] ?>" alt="صورة الغلاف" id="coverImg">
                        <?php else: ?>
                            <div class="placeholder">
                                <i class="fas fa-image"></i>
                                <span>لا توجد صورة غلاف</span>
                            </div>
                        <?php endif; ?>
                    </div>
                    <input type="file" name="cover_image" id="coverInput" accept="image/*" style="display:none;" onchange="previewImage(this, 'coverPreview', 'coverImg')">
                    <button type="button" class="upload-btn" onclick="document.getElementById('coverInput').click()">
                        <i class="fas fa-upload"></i> رفع صورة غلاف
                    </button>
                    <div class="help-text">يوصى بحجم 1200x400 بكسل للحصول على أفضل عرض</div>
                </div>
                
                <!-- ===== بيانات المستخدم ===== -->
                <div class="form-group">
                    <label>👤 الاسم الكامل <span class="text-red-500">*</span></label>
                    <input type="text" name="name" value="<?= clean($user['name']) ?>" required>
                </div>
                
                <div class="form-group">
                    <label>📱 رقم الهاتف</label>
                    <input type="text" name="phone" value="<?= clean($user['phone']) ?>" placeholder="05xxxxxxxx">
                </div>
                
                <div class="form-group">
                    <label>💬 رقم واتساب</label>
                    <input type="text" name="whatsapp" value="<?= clean($user['whatsapp']) ?>" placeholder="05xxxxxxxx">
                </div>
                
                <div class="form-group">
                    <label>📝 نبذة عني (Bio)</label>
                    <textarea name="bio" rows="3" placeholder="اكتب نبذة عن نفسك..."><?= clean($user['bio']) ?></textarea>
                </div>
                
                <div class="form-group">
                    <label>🌍 الدولة</label>
                    <select name="country_id" id="edit-country" onchange="loadCities('edit-country', 'edit-city')">
                        <option value="0">اختر الدولة</option>
                        <?php foreach ($countries as $country): ?>
                        <option value="<?= $country['id'] ?>" <?= ($user['country_id'] == $country['id']) ? 'selected' : '' ?>>
                            <?= $country['flag'] ?? '' ?> <?= $country['name_ar'] ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>🏙️ المدينة</label>
                    <select name="city_id" id="edit-city">
                        <option value="0">اختر المدينة</option>
                        <?php foreach ($cities as $city): ?>
                        <option value="<?= $city['id'] ?>" <?= ($user['city_id'] == $city['id']) ? 'selected' : '' ?>>
                            <?= $city['name_ar'] ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>📍 العنوان التفصيلي</label>
                    <input type="text" name="address" value="<?= clean($user['address']) ?>" placeholder="الحي - الشارع - رقم المبنى">
                </div>
                
                <button type="submit" class="btn-save">
                    <i class="fas fa-save"></i> حفظ التغييرات
                </button>
            </form>
        </div>
        
    </div>
</div>

<script>
    // ============================================
    // معاينة الصورة قبل الرفع
    // ============================================
    function previewImage(input, previewId, imgId) {
        const preview = document.getElementById(previewId);
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                preview.innerHTML = `<img src="${e.target.result}" alt="صورة" id="${imgId}" style="width:100%;height:100%;object-fit:cover;">`;
            }
            reader.readAsDataURL(input.files[0]);
        }
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
    
    // تحميل المدن إذا كانت الدولة محددة مسبقاً
    document.addEventListener('DOMContentLoaded', function() {
        const selectedCountry = document.getElementById('edit-country').value;
        if (selectedCountry) {
            loadCities('edit-country', 'edit-city');
        }
    });
</script>

<?php include 'includes/footer.php'; ?>
</body>
</html>