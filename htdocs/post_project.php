<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';

// ============================================
// معالجة طلبات API المدمجة (للحصول على العملة)
// ============================================
if (isset($_GET['action']) && $_GET['action'] === 'get_currency') {
    header('Content-Type: application/json');
    $country_id = (int)($_GET['country_id'] ?? 0);
    $currency = '';
    if ($country_id > 0) {
        try {
            $stmt = $pdo->prepare("SELECT currency_ar FROM countries WHERE id = ?");
            $stmt->execute([$country_id]);
            $currency = $stmt->fetchColumn() ?: '';
        } catch (Exception $e) {
            error_log("Error fetching currency: " . $e->getMessage());
        }
    }
    echo json_encode(['currency' => $currency]);
    exit;
}

if (!isLoggedIn() || getUserType() !== 'employer') {    
    redirect('register.php');
}

$current_user = getUser($_SESSION['user_id']);

$countries = getCountries();
$categories = getProjectCategories();
$error = '';
$success = '';

// ============================================
// التأكد من وجود جدول project_images والأعمدة المطلوبة
// ============================================
function ensureProjectImagesTable() {
    global $pdo;
    try {
        // 1. التحقق من وجود الجدول
        $stmt = $pdo->query("SHOW TABLES LIKE 'project_images'");
        if ($stmt->rowCount() == 0) {
            // إنشاء الجدول مع جميع الأعمدة
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS project_images (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    project_id INT NOT NULL,
                    image_path VARCHAR(255) NOT NULL,
                    is_main TINYINT(1) DEFAULT 0,
                    uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
                )
            ");
        } else {
            // 2. التحقق من وجود عمود is_main
            $stmt = $pdo->query("SHOW COLUMNS FROM project_images LIKE 'is_main'");
            if ($stmt->rowCount() == 0) {
                $pdo->exec("ALTER TABLE project_images ADD COLUMN is_main TINYINT(1) DEFAULT 0");
            }
            
            // 3. التحقق من وجود عمود uploaded_at
            $stmt = $pdo->query("SHOW COLUMNS FROM project_images LIKE 'uploaded_at'");
            if ($stmt->rowCount() == 0) {
                $pdo->exec("ALTER TABLE project_images ADD COLUMN uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP");
            }
        }
    } catch (Exception $e) {
        error_log("Error ensuring project_images table: " . $e->getMessage());
    }
}
ensureProjectImagesTable();

// ============================================
// التأكد من وجود جدول project_attachments
// ============================================
function ensureProjectAttachmentsTable() {
    global $pdo;
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'project_attachments'");
        if ($stmt->rowCount() == 0) {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS project_attachments (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    project_id INT NOT NULL,
                    filename VARCHAR(255) NOT NULL,
                    original_name VARCHAR(255) NOT NULL,
                    file_size INT NOT NULL,
                    file_type VARCHAR(100),
                    file_ext VARCHAR(10),
                    uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
                )
            ");
        } else {
            // التحقق من وجود uploaded_at
            $stmt = $pdo->query("SHOW COLUMNS FROM project_attachments LIKE 'uploaded_at'");
            if ($stmt->rowCount() == 0) {
                $pdo->exec("ALTER TABLE project_attachments ADD COLUMN uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP");
            }
        }
    } catch (Exception $e) {
        error_log("Error ensuring project_attachments table: " . $e->getMessage());
    }
}
ensureProjectAttachmentsTable();

// ============================================
// وضع التعديل (إذا تم إرسال ID)
// ============================================
$edit_mode = false;
$project_data = null;
$project_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($project_id > 0) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM projects WHERE id = ? AND employer_id = ?");
        $stmt->execute([$project_id, $_SESSION['user_id']]);
        $project_data = $stmt->fetch();
        
        if ($project_data) {
            $edit_mode = true;
            // جلب الصور المرفقة
            $stmt_img = $pdo->prepare("SELECT * FROM project_images WHERE project_id = ? ORDER BY is_main DESC, id ASC");
            $stmt_img->execute([$project_id]);
            $project_images = $stmt_img->fetchAll();
            
            // جلب الملفات الأخرى
            $stmt_files = $pdo->prepare("SELECT * FROM project_attachments WHERE project_id = ? ORDER BY uploaded_at DESC");
            $stmt_files->execute([$project_id]);
            $project_attachments = $stmt_files->fetchAll();
        }
    } catch (Exception $e) {
        error_log("Error loading project: " . $e->getMessage());
    }
}

// ============================================
// تحديد العملة المعروضة
// ============================================
$currency = '';
// أولاً: إذا كان في وضع التعديل وللمشروع دولة محددة، نأخذ عملتها
if ($edit_mode && $project_data && !empty($project_data['country_id'])) {
    $curr = getCurrency($project_data['country_id']);
    $currency = $curr['currency_ar'] ?? '';
}
// وإلا نأخذ عملة المستخدم المسجل
if (empty($currency) && isset($current_user) && $current_user && $current_user['country_id']) {
    $curr = getCurrency($current_user['country_id']);
    $currency = $curr['currency_ar'] ?? '';
}

// ============================================
// معالجة النموذج (إضافة أو تعديل)
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = clean($_POST['title'] ?? '');
    $description = clean($_POST['description'] ?? '');
    $category = clean($_POST['category'] ?? '');
    $budget_min = !empty($_POST['budget_min']) ? (float)$_POST['budget_min'] : null;
    $budget_max = !empty($_POST['budget_max']) ? (float)$_POST['budget_max'] : null;
    
    // التحقق من صحة country_id و city_id
    $country_id = (int)($_POST['country_id'] ?? 0);
    $city_id = (int)($_POST['city_id'] ?? 0);
    
    if ($country_id > 0) {
        $stmt_check = $pdo->prepare("SELECT id FROM countries WHERE id = ?");
        $stmt_check->execute([$country_id]);
        if (!$stmt_check->fetch()) {
            $country_id = 0;
        }
    }
    
    if ($city_id > 0) {
        $stmt_check = $pdo->prepare("SELECT id FROM cities WHERE id = ? AND country_id = ?");
        $stmt_check->execute([$city_id, $country_id]);
        if (!$stmt_check->fetch()) {
            $city_id = 0;
        }
    }
    
    $country_id = $country_id > 0 ? $country_id : null;
    $city_id = $city_id > 0 ? $city_id : null;
    
    $address = clean($_POST['address'] ?? '');
    $deadline = clean($_POST['deadline'] ?? '');
    
    if (empty($title) || empty($description) || empty($category)) {
        $error = 'يرجى ملء الحقول المطلوبة (*)';
    } else {
        try {
            $pdo->beginTransaction();
            
            if ($edit_mode && $project_data) {
                // تحديث المشروع
                $stmt = $pdo->prepare("
                    UPDATE projects 
                    SET title = ?, description = ?, category = ?, 
                        budget_min = ?, budget_max = ?, 
                        country_id = ?, city_id = ?, address = ?, deadline = ?
                    WHERE id = ? AND employer_id = ?
                ");
                $stmt->execute([
                    $title, $description, $category,
                    $budget_min, $budget_max,
                    $country_id, $city_id, $address,
                    $deadline ?: null,
                    $project_id, $_SESSION['user_id']
                ]);
                $success = '✅ تم تحديث المشروع بنجاح!';
            } else {
                // إضافة مشروع جديد
                $stmt = $pdo->prepare("
                    INSERT INTO projects (
                        employer_id, title, description, category, 
                        budget_min, budget_max, country_id, city_id, address, deadline, status
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
                ");
                $stmt->execute([
                    $_SESSION['user_id'], $title, $description, $category,
                    $budget_min, $budget_max, $country_id, $city_id, $address,
                    $deadline ?: null
                ]);
                $project_id = $pdo->lastInsertId();
                $success = 'تم إرسال المشروع بنجاح! سيتم مراجعته من قبل الإدارة وإشعارك عند الموافقة عليه.';
            }
            
            // ============================================
            // معالجة الصورة الرئيسية
            // ============================================
            if (isset($_FILES['main_image']) && $_FILES['main_image']['error'] === UPLOAD_ERR_OK) {
                $main_image_dir = __DIR__ . '/assets/uploads/projects/';
                if (!is_dir($main_image_dir)) {
                    mkdir($main_image_dir, 0777, true);
                }
                
                $file_tmp = $_FILES['main_image']['tmp_name'];
                $file_name = $_FILES['main_image']['name'];
                $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                $allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                
                if (in_array($file_ext, $allowed_ext)) {
                    $new_filename = 'main_' . $project_id . '_' . time() . '.' . $file_ext;
                    $destination = $main_image_dir . $new_filename;
                    
                    if (move_uploaded_file($file_tmp, $destination)) {
                        // حذف الصورة الرئيسية القديمة (في وضع التعديل)
                        if ($edit_mode) {
                            $stmt_old = $pdo->prepare("SELECT image_path FROM project_images WHERE project_id = ? AND is_main = 1");
                            $stmt_old->execute([$project_id]);
                            $old_main = $stmt_old->fetch();
                            if ($old_main && file_exists(__DIR__ . '/' . $old_main['image_path'])) {
                                unlink(__DIR__ . '/' . $old_main['image_path']);
                            }
                            $stmt_del = $pdo->prepare("DELETE FROM project_images WHERE project_id = ? AND is_main = 1");
                            $stmt_del->execute([$project_id]);
                        }
                        
                        // حفظ الصورة الرئيسية الجديدة
                        $stmt_img = $pdo->prepare("
                            INSERT INTO project_images (project_id, image_path, is_main, uploaded_at) 
                            VALUES (?, ?, 1, NOW())
                        ");
                        $stmt_img->execute([$project_id, 'assets/uploads/projects/' . $new_filename]);
                    }
                }
            }
            
            // ============================================
            // معالجة الملفات الأخرى (PDF, CAD, إلخ)
            // ============================================
            $upload_dir = __DIR__ . '/assets/uploads/projects/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'dwg', 'dxf', 'zip', 'rar'];
            
            if (isset($_FILES['project_files'])) {
                foreach ($_FILES['project_files']['tmp_name'] as $key => $tmp_name) {
                    if ($_FILES['project_files']['error'][$key] === UPLOAD_ERR_OK) {
                        $file_name = $_FILES['project_files']['name'][$key];
                        $file_size = $_FILES['project_files']['size'][$key];
                        $file_tmp = $_FILES['project_files']['tmp_name'][$key];
                        $file_type = $_FILES['project_files']['type'][$key];
                        
                        if ($file_size > 10 * 1024 * 1024) continue;
                        
                        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                        if (!in_array($file_ext, $allowed_ext)) continue;
                        
                        $new_filename = 'project_' . $project_id . '_' . time() . '_' . $key . '.' . $file_ext;
                        $destination = $upload_dir . $new_filename;
                        
                        if (move_uploaded_file($file_tmp, $destination)) {
                            // حفظ في جدول project_attachments
                            try {
                                $stmt_check = $pdo->query("SHOW TABLES LIKE 'project_attachments'");
                                if ($stmt_check->rowCount() > 0) {
                                    $stmt_file = $pdo->prepare("
                                        INSERT INTO project_attachments (project_id, filename, original_name, file_size, file_type, file_ext, uploaded_at) 
                                        VALUES (?, ?, ?, ?, ?, ?, NOW())
                                    ");
                                    $stmt_file->execute([
                                        $project_id,
                                        $new_filename,
                                        $file_name,
                                        $file_size,
                                        $file_type,
                                        $file_ext
                                    ]);
                                }
                            } catch (Exception $e) {
                                error_log("Project attachments table error: " . $e->getMessage());
                            }
                        }
                    }
                }
            }
            
            $pdo->commit();
            
            if (!$edit_mode) {
                echo "<script>setTimeout(() => window.location.href = '" . SITE_URL . "/', 3000);</script>";
            } else {
                // إعادة تحميل بيانات المشروع بعد التعديل
                $stmt = $pdo->prepare("SELECT * FROM projects WHERE id = ? AND employer_id = ?");
                $stmt->execute([$project_id, $_SESSION['user_id']]);
                $project_data = $stmt->fetch();
            }
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = 'خطأ في قاعدة البيانات: ' . $e->getMessage();
            error_log("Database error: " . $e->getMessage());
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'حدث خطأ: ' . $e->getMessage();
            error_log("General error: " . $e->getMessage());
        }
    }
}

$page_title = $edit_mode ? 'تعديل المشروع' : 'طرح مشروع جديد';
include 'includes/header.php';
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> - مزاد البناء</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700;900&display=swap" rel="stylesheet">
    <style>
        * { 
            font-family: 'DM Sans', sans-serif;
            box-sizing: border-box;
        }
        
        html, body {
            min-height: 100vh;
            margin: 0;
            padding: 0;
            overflow-x: hidden;
            width: 100%;
        }
        
        body {
            background: #0a1628;
            position: relative;
        }
        
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-image: url('https://images.unsplash.com/photo-1541888946425-d81bb19240f5?w=1920&q=80');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            z-index: 0;
            animation: zoomIn 20s ease-in-out infinite alternate;
        }
        
        @keyframes zoomIn {
            0% { transform: scale(1); }
            100% { transform: scale(1.05); }
        }
        
        body::after {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, 
                rgba(10, 14, 23, 0.88) 0%,
                rgba(10, 14, 23, 0.75) 30%,
                rgba(10, 14, 23, 0.65) 60%,
                rgba(10, 14, 23, 0.80) 100%
            );
            z-index: 0;
        }
        
        .gradient-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: radial-gradient(ellipse at 50% 30%, 
                rgba(255, 255, 255, 0.04) 0%,
                transparent 70%
            );
            z-index: 0;
            pointer-events: none;
        }
        
        .main-content {
            position: relative;
            z-index: 1;
            padding: 20px 0 50px 0;
            min-height: 100vh;
            width: 100%;
            max-width: 100%;
            overflow-x: hidden;
            margin-top: 60px;
        }
        
        .form-card {
            background: rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.08);
            overflow: hidden;
            box-shadow: 0 8px 40px rgba(0, 0, 0, 0.3);
            width: 100%;
        }
        
        .form-header {
            background: linear-gradient(135deg, rgba(24, 119, 242, 0.3), rgba(13, 101, 217, 0.2));
            padding: 24px 28px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.06);
        }
        
        .form-header h1 {
            color: white;
            font-size: 24px;
            font-weight: 900;
            text-shadow: 0 2px 20px rgba(0, 0, 0, 0.2);
            margin: 0;
        }
        
        .form-header p {
            color: rgba(255, 255, 255, 0.6);
            font-size: 14px;
            margin-top: 4px;
            margin-bottom: 0;
        }
        
        .form-body {
            padding: 28px;
        }
        
        .form-label {
            display: block;
            color: rgba(255, 255, 255, 0.8);
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 4px;
            letter-spacing: 0.3px;
        }
        
        .form-label .required {
            color: #f87171;
        }
        
        .form-input {
            width: 100%;
            padding: 12px 16px;
            border-radius: 12px;
            font-size: 14px;
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.08);
            color: white;
            transition: all 0.3s ease;
            box-sizing: border-box;
        }
        
        .form-input:focus {
            outline: none;
            border-color: rgba(24, 119, 242, 0.4);
            box-shadow: 0 0 0 4px rgba(24, 119, 242, 0.08);
            background: rgba(255, 255, 255, 0.10);
        }
        
        .form-input::placeholder {
            color: rgba(255, 255, 255, 0.3);
        }
        
        .form-input option {
            background: #1e293b;
            color: white;
        }
        
        .form-input[type="number"],
        .form-input[type="date"] {
            color: white;
            direction: ltr;
            text-align: right;
        }
        
        .form-input[type="number"]::-webkit-inner-spin-button {
            opacity: 0.5;
        }
        
        .form-input[type="date"]::-webkit-calendar-picker-indicator {
            filter: invert(1);
            cursor: pointer;
        }
        
        .form-select {
            width: 100%;
            padding: 12px 16px;
            padding-left: 40px;
            border-radius: 12px;
            font-size: 14px;
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.08);
            color: white;
            transition: all 0.3s ease;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='rgba(255,255,255,0.4)' d='M6 8L1 3h10z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: left 16px center;
            cursor: pointer;
            box-sizing: border-box;
        }
        
        .form-select:focus {
            outline: none;
            border-color: rgba(24, 119, 242, 0.4);
            box-shadow: 0 0 0 4px rgba(24, 119, 242, 0.08);
            background: rgba(255, 255, 255, 0.10);
        }
        
        .form-select option {
            background: #1e293b;
            color: white;
        }
        
        .form-textarea {
            width: 100%;
            padding: 12px 16px;
            border-radius: 12px;
            font-size: 14px;
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.08);
            color: white;
            transition: all 0.3s ease;
            resize: vertical;
            min-height: 120px;
            font-family: 'DM Sans', sans-serif;
            box-sizing: border-box;
        }
        
        .form-textarea:focus {
            outline: none;
            border-color: rgba(24, 119, 242, 0.4);
            box-shadow: 0 0 0 4px rgba(24, 119, 242, 0.08);
            background: rgba(255, 255, 255, 0.10);
        }
        
        .form-textarea::placeholder {
            color: rgba(255, 255, 255, 0.3);
        }
        
        /* ============================================ */
        /* منطقة رفع الملفات - متعددة الأنواع */
        /* ============================================ */
        .upload-area {
            border: 2px dashed rgba(255, 255, 255, 0.08);
            border-radius: 14px;
            padding: 32px 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.02);
            width: 100%;
            box-sizing: border-box;
        }
        
        .upload-area:hover {
            border-color: rgba(24, 119, 242, 0.3);
            background: rgba(255, 255, 255, 0.05);
            transform: translateY(-2px);
        }
        
        .upload-area i {
            color: rgba(255, 255, 255, 0.2);
            font-size: 40px;
            display: block;
            margin-bottom: 8px;
        }
        
        .upload-area p {
            color: rgba(255, 255, 255, 0.5);
            font-size: 14px;
            font-weight: 500;
            margin: 0;
        }
        
        .upload-area .sub {
            color: rgba(255, 255, 255, 0.3);
            font-size: 11px;
            margin-top: 4px;
        }
        
        .file-types-badge {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            justify-content: center;
            margin-top: 8px;
        }
        
        .file-types-badge span {
            background: rgba(255, 255, 255, 0.06);
            color: rgba(255, 255, 255, 0.5);
            font-size: 10px;
            padding: 3px 10px;
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.05);
        }
        
        .file-types-badge span i {
            font-size: 10px;
            display: inline;
            margin: 0 4px 0 0;
        }
        
        /* ============================================ */
        /* معاينة الملفات */
        /* ============================================ */
        .files-preview {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 10px;
            margin-top: 12px;
            width: 100%;
        }
        
        .file-preview-item {
            position: relative;
            border-radius: 12px;
            overflow: hidden;
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(255, 255, 255, 0.06);
            padding: 12px 8px;
            text-align: center;
            transition: all 0.3s ease;
        }
        
        .file-preview-item:hover {
            background: rgba(255, 255, 255, 0.08);
        }
        
        .file-preview-item .file-icon {
            font-size: 32px;
            margin-bottom: 4px;
            display: block;
        }
        
        .file-preview-item .file-icon.pdf { color: #ef4444; }
        .file-preview-item .file-icon.image { color: #8b5cf6; }
        .file-preview-item .file-icon.cad { color: #3b82f6; }
        .file-preview-item .file-icon.zip { color: #f59e0b; }
        .file-preview-item .file-icon.default { color: rgba(255,255,255,0.3); }
        
        .file-preview-item .file-name {
            color: rgba(255, 255, 255, 0.6);
            font-size: 10px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 100%;
        }
        
        .file-preview-item .file-size {
            color: rgba(255, 255, 255, 0.3);
            font-size: 9px;
            margin-top: 2px;
        }
        
        /* ============================================ */
        /* معاينة الصورة الرئيسية */
        /* ============================================ */
        .main-image-preview {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-top: 12px;
            flex-wrap: wrap;
        }
        
        .main-image-preview img {
            width: 120px;
            height: 120px;
            object-fit: cover;
            border-radius: 12px;
            border: 2px solid rgba(255,255,255,0.1);
        }
        
        .main-image-preview .placeholder {
            width: 120px;
            height: 120px;
            border-radius: 12px;
            border: 2px dashed rgba(255,255,255,0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            color: rgba(255,255,255,0.2);
            font-size: 12px;
            text-align: center;
        }
        
        .grid-2 { 
            display: grid; 
            grid-template-columns: 1fr 1fr; 
            gap: 16px; 
        }
        
        .mb-4 { margin-bottom: 16px; }
        .mb-6 { margin-bottom: 24px; }
        
        .text-blue-400 { color: rgba(96, 165, 250, 0.8); }
        .text-xs { font-size: 11px; }
        .text-sm { font-size: 13px; }
        .text-gray-400 { color: rgba(255, 255, 255, 0.4); }
        
        .hidden { display: none; }
        
        .max-w-3xl {
            max-width: 768px;
            margin-left: auto;
            margin-right: auto;
            padding-left: 16px;
            padding-right: 16px;
            width: 100%;
            box-sizing: border-box;
        }
        
        .alert-box {
            border-radius: 14px;
            padding: 14px 18px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
            font-weight: 500;
            backdrop-filter: blur(8px);
            width: 100%;
            box-sizing: border-box;
        }
        
        .alert-error {
            background: rgba(239, 68, 68, 0.12);
            border: 1px solid rgba(239, 68, 68, 0.15);
            color: #fca5a5;
        }
        
        .alert-success {
            background: rgba(34, 197, 94, 0.12);
            border: 1px solid rgba(34, 197, 94, 0.15);
            color: #86efac;
        }
        
        .btn-submit {
            width: 100%;
            background: linear-gradient(135deg, #1877f2, #0d65d9);
            color: white;
            font-weight: 700;
            font-size: 16px;
            padding: 14px 20px;
            border-radius: 14px;
            border: none;
            transition: all 0.3s ease;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            box-shadow: 0 4px 20px rgba(24, 119, 242, 0.3);
            box-sizing: border-box;
        }
        
        .btn-submit:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 35px rgba(24, 119, 242, 0.4);
        }
        
        .btn-submit:active {
            transform: scale(0.98);
        }
        
        /* ============================================ */
        /* حالة المشروع */
        /* ============================================ */
        .project-status-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 16px;
        }
        
        .project-status-badge.pending {
            background: rgba(245, 158, 11, 0.15);
            color: #fcd34d;
            border: 1px solid rgba(245, 158, 11, 0.2);
        }
        
        .project-status-badge.active {
            background: rgba(34, 197, 94, 0.15);
            color: #86efac;
            border: 1px solid rgba(34, 197, 94, 0.2);
        }
        
        .project-status-badge.in_progress {
            background: rgba(59, 130, 246, 0.15);
            color: #93bbfc;
            border: 1px solid rgba(59, 130, 246, 0.2);
        }
        
        .project-status-badge.completed {
            background: rgba(139, 92, 246, 0.15);
            color: #a78bfa;
            border: 1px solid rgba(139, 92, 246, 0.2);
        }
        
        .info-note {
            background: rgba(255, 255, 255, 0.04);
            border-radius: 10px;
            padding: 12px 16px;
            color: rgba(255, 255, 255, 0.6);
            font-size: 13px;
            border-right: 3px solid rgba(24, 119, 242, 0.3);
            margin-bottom: 16px;
        }
        
        @media (max-width: 768px) {
            .main-content {
                margin-top: 50px;
                padding: 12px 0 40px 0;
            }
            .max-w-3xl {
                padding-left: 12px;
                padding-right: 12px;
            }
            .grid-2 { 
                grid-template-columns: 1fr; 
                gap: 12px;
            }
            .form-header { 
                padding: 16px 18px; 
            }
            .form-header h1 { 
                font-size: 20px; 
            }
            .form-header p { 
                font-size: 13px; 
            }
            .form-body { 
                padding: 18px; 
            }
            .form-input,
            .form-select,
            .form-textarea {
                font-size: 13px;
                padding: 10px 14px;
            }
            .upload-area {
                padding: 24px 16px;
            }
            .upload-area i {
                font-size: 32px;
            }
            .upload-area p {
                font-size: 13px;
            }
            .btn-submit {
                font-size: 15px;
                padding: 12px 16px;
            }
            .files-preview {
                grid-template-columns: repeat(auto-fill, minmax(90px, 1fr));
                gap: 8px;
            }
            .alert-box {
                font-size: 13px;
                padding: 12px 14px;
            }
            .form-card {
                border-radius: 16px;
            }
            .main-image-preview img,
            .main-image-preview .placeholder {
                width: 80px;
                height: 80px;
            }
        }
        
        @media (max-width: 480px) {
            .main-content {
                margin-top: 45px;
                padding: 8px 0 30px 0;
            }
            .max-w-3xl {
                padding-left: 8px;
                padding-right: 8px;
            }
            .form-header { 
                padding: 14px 14px; 
            }
            .form-header h1 { 
                font-size: 17px; 
            }
            .form-header p { 
                font-size: 12px; 
            }
            .form-body { 
                padding: 14px; 
            }
            .form-input,
            .form-select,
            .form-textarea {
                font-size: 12px;
                padding: 9px 12px;
                border-radius: 10px;
            }
            .form-label {
                font-size: 12px;
            }
            .btn-submit {
                font-size: 14px;
                padding: 11px 14px;
                border-radius: 12px;
            }
            .upload-area {
                padding: 18px 12px;
            }
            .upload-area i {
                font-size: 28px;
            }
            .files-preview {
                grid-template-columns: repeat(auto-fill, minmax(70px, 1fr));
                gap: 6px;
            }
            .form-card {
                border-radius: 14px;
            }
            .main-image-preview img,
            .main-image-preview .placeholder {
                width: 60px;
                height: 60px;
            }
        }
        
        input:-webkit-autofill,
        input:-webkit-autofill:hover,
        input:-webkit-autofill:focus,
        input:-webkit-autofill:active {
            -webkit-box-shadow: 0 0 0 30px rgba(30, 41, 59, 0.9) inset !important;
            -webkit-text-fill-color: white !important;
        }
        
        .container-fluid {
            overflow-x: hidden;
            width: 100%;
        }
        
        .form-select:-moz-focusring {
            color: transparent;
            text-shadow: 0 0 0 white;
        }
    </style>
</head>
<body>

<div class="gradient-overlay"></div>

<div class="main-content">
    <div class="max-w-3xl">
        
        <div class="form-card">
            <div class="form-header">
                <h1 class="flex items-center gap-2">
                    <i class="fas <?= $edit_mode ? 'fa-edit' : 'fa-plus-circle' ?>" style="color: rgba(255,255,255,0.6);"></i>
                    <?= $edit_mode ? 'تعديل المشروع' : 'طرح مشروع جديد' ?>
                </h1>
                <p>
                    <?php if ($edit_mode): ?>
                        قم بتعديل تفاصيل مشروعك. التعديلات ستظهر مباشرة دون الحاجة لموافقة الإدارة.
                    <?php else: ?>
                        أدخل تفاصيل مشروعك وسيتلقى عطاءات من المقاولين بعد موافقة الإدارة.
                    <?php endif; ?>
                </p>
            </div>
            
            <div class="form-body">
                
                <?php if ($error): ?>
                <div class="alert-box alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?= $error ?></span>
                </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                <div class="alert-box alert-success">
                    <i class="fas fa-check-circle"></i>
                    <span><?= $success ?></span>
                </div>
                <?php endif; ?>
                
                <?php if ($edit_mode && $project_data): ?>
                <div class="project-status-badge <?= $project_data['status'] ?>">
                    <i class="fas <?php 
                        echo match($project_data['status']) {
                            'pending' => 'fa-clock',
                            'active' => 'fa-check-circle',
                            'in_progress' => 'fa-spinner',
                            'completed' => 'fa-check-double',
                            default => 'fa-info-circle'
                        };
                    ?>"></i>
                    حالة المشروع: 
                    <?php
                        $status_labels = [
                            'pending' => '⏳ قيد المراجعة',
                            'active' => '✅ نشط',
                            'in_progress' => '🟡 قيد التنفيذ',
                            'completed' => '🔵 مكتمل',
                            'cancelled' => '🔴 ملغي'
                        ];
                        echo $status_labels[$project_data['status']] ?? $project_data['status'];
                    ?>
                </div>
                
                <?php if ($project_data['status'] === 'pending'): ?>
                <div class="info-note">
                    <i class="fas fa-info-circle" style="color: rgba(96,165,250,0.6); margin-left: 8px;"></i>
                    هذا المشروع لا يزال قيد المراجعة من قبل الإدارة. يمكنك تعديل التفاصيل الآن، وسيتم مراجعتها مرة أخرى.
                </div>
                <?php endif; ?>
                <?php endif; ?>
                
                <form method="POST" enctype="multipart/form-data">
                    <?php if ($edit_mode): ?>
                    <input type="hidden" name="edit_mode" value="1">
                    <?php endif; ?>
                    
                    <div class="mb-4">
                        <label class="form-label">عنوان المشروع <span class="required">*</span></label>
                        <input type="text" name="title" required value="<?= clean($edit_mode ? $project_data['title'] : ($_POST['title'] ?? '')) ?>" 
                               class="form-input" placeholder="مثال: تشطيب شقة 3 غرف في الرياض">
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label">نوع المشروع <span class="required">*</span></label>
                        <select name="category" required class="form-select">
                            <option value="">اختر التصنيف</option>
                            <?php 
                            $selected_cat = $edit_mode ? $project_data['category'] : ($_POST['category'] ?? '');
                            foreach ($categories as $cat => $icon): 
                            ?>
                            <option value="<?= $cat ?>" <?= ($selected_cat === $cat) ? 'selected' : '' ?>>
                                <?= $icon ?> <?= $cat ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label">وصف المشروع <span class="required">*</span></label>
                        <textarea name="description" required rows="5" class="form-textarea" 
                                  placeholder="اكتب تفاصيل المشروع، المواصفات المطلوبة، المساحة، وكل ما يساعد المقاول في تقديم عطاء دقيق..."><?= clean($edit_mode ? $project_data['description'] : ($_POST['description'] ?? '')) ?></textarea>
                    </div>
                    
                    <div class="grid-2 mb-4">
                        <div>
                            <label class="form-label">الميزانية (من) <span class="text-gray-400 text-xs">(اختياري)</span></label>
                            <input type="number" name="budget_min" value="<?= $edit_mode ? ($project_data['budget_min'] ?? '') : ($_POST['budget_min'] ?? '') ?>" 
                                   min="0" step="0.01" class="form-input" placeholder="0" dir="ltr">
                        </div>
                        <div>
                            <label class="form-label">الميزانية (إلى) <span class="text-gray-400 text-xs">(اختياري)</span></label>
                            <input type="number" name="budget_max" value="<?= $edit_mode ? ($project_data['budget_max'] ?? '') : ($_POST['budget_max'] ?? '') ?>" 
                                   min="0" step="0.01" class="form-input" placeholder="0" dir="ltr">
                            <?php if ($currency): ?>
                            <span id="currency-label" class="text-xs text-gray-400"><?= $currency ?></span>
                            <?php else: ?>
                            <span id="currency-label" class="text-xs text-gray-400"></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="grid-2 mb-4">
                        <div>
                            <label class="form-label">الدولة <span class="text-gray-400 text-xs">(اختياري)</span></label>
                            <select name="country_id" id="project-country" onchange="loadCities('project-country', 'project-city')" class="form-select">
                                <option value="">اختر الدولة</option>
                                <?php 
                                $selected_country = $edit_mode ? $project_data['country_id'] : ($_POST['country_id'] ?? ($current_user['country_id'] ?? 0));
                                foreach ($countries as $country): 
                                ?>
                                <option value="<?= $country['id'] ?>" <?= ($selected_country == $country['id']) ? 'selected' : '' ?>>
                                    <?= $country['flag'] ?> <?= $country['name_ar'] ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">المدينة <span class="text-gray-400 text-xs">(اختياري)</span></label>
                            <select name="city_id" id="project-city" class="form-select">
                                <option value="">اختر المدينة</option>
                                <?php 
                                $selected_city = $edit_mode ? $project_data['city_id'] : ($_POST['city_id'] ?? 0);
                                if ($selected_city > 0 && $edit_mode):
                                    try {
                                        $stmt_city = $pdo->prepare("SELECT name_ar FROM cities WHERE id = ?");
                                        $stmt_city->execute([$selected_city]);
                                        $city_name = $stmt_city->fetchColumn();
                                        if ($city_name) {
                                            echo '<option value="' . $selected_city . '" selected>' . $city_name . '</option>';
                                        }
                                    } catch (Exception $e) {}
                                endif;
                                ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label">العنوان التفصيلي</label>
                        <input type="text" name="address" value="<?= clean($edit_mode ? $project_data['address'] : ($_POST['address'] ?? '')) ?>" 
                               class="form-input" placeholder="الحي - الشارع - أقرب معلم">
                    </div>
                    
                    <div class="mb-6">
                        <label class="form-label">الموعد النهائي لاستقبال العطاءات</label>
                        <input type="date" name="deadline" value="<?= $edit_mode ? ($project_data['deadline'] ?? '') : ($_POST['deadline'] ?? '') ?>" 
                               min="<?= date('Y-m-d') ?>" class="form-input" dir="ltr">
                    </div>
                    
                    <!-- ============================================ -->
                    <!-- الصورة الرئيسية للمشروع -->
                    <!-- ============================================ -->
                    <div class="mb-6">
                        <label class="form-label">🖼️ الصورة الرئيسية للمشروع <span class="text-gray-400 text-xs">(اختياري)</span></label>
                        <div class="upload-area" onclick="document.getElementById('main-image').click()" style="border-color: rgba(24, 119, 242, 0.2);">
                            <i class="fas fa-image" style="color: rgba(96,165,250,0.4);"></i>
                            <p>اضغط لرفع الصورة الرئيسية</p>
                            <div class="sub">JPG, PNG, WEBP (يوصى بحجم 800x600)</div>
                            <input type="file" name="main_image" id="main-image" accept=".jpg,.jpeg,.png,.gif,.webp" 
                                   class="hidden" onchange="previewMainImage(this)">
                        </div>
                        <div id="main-image-preview" class="main-image-preview">
                            <?php if ($edit_mode && !empty($project_images)): 
                                $main_image = null;
                                foreach ($project_images as $img) {
                                    if ($img['is_main']) {
                                        $main_image = $img;
                                        break;
                                    }
                                }
                                if ($main_image): ?>
                                <img src="<?= SITE_URL . '/' . $main_image['image_path'] ?>" alt="الصورة الرئيسية">
                                <span class="text-xs text-gray-400">الصورة الرئيسية الحالية</span>
                                <?php else: ?>
                                <div class="placeholder">
                                    <span>لا توجد صورة رئيسية</span>
                                </div>
                                <?php endif; ?>
                            <?php else: ?>
                            <div class="placeholder">
                                <span>لا توجد صورة رئيسية</span>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="text-xs text-gray-400 mt-1">اختر صورة لتظهر كصورة رئيسية للمشروع في قائمة المشاريع</div>
                    </div>
                    
                    <!-- ============================================ -->
                    <!-- رفع الملفات الأخرى (PDF, CAD, إلخ) -->
                    <!-- ============================================ -->
                    <div class="mb-6">
                        <label class="form-label">📎 ملفات إضافية <span class="text-gray-400 text-xs">(اختياري)</span></label>
                        <div class="upload-area" onclick="document.getElementById('project-files').click()">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <p>اضغط لرفع الملفات</p>
                            <div class="sub">PNG, JPG, WEBP, PDF, DWG, DXF, ZIP, RAR (حد أقصى 10MB)</div>
                            <div class="file-types-badge">
                                <span><i class="fas fa-file-image"></i> صور</span>
                                <span><i class="fas fa-file-pdf"></i> PDF</span>
                                <span><i class="fas fa-file-cad"></i> CAD</span>
                                <span><i class="fas fa-file-archive"></i> مضغوط</span>
                            </div>
                            <input type="file" name="project_files[]" id="project-files" multiple 
                                   accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.dwg,.dxf,.zip,.rar" 
                                   class="hidden" onchange="previewFiles(this, 'files-preview')">
                        </div>
                        <div id="files-preview" class="files-preview">
                            <?php if ($edit_mode && !empty($project_attachments)): 
                                foreach ($project_attachments as $file): 
                                    $ext = strtolower(pathinfo($file['original_name'], PATHINFO_EXTENSION));
                                    $icons = [
                                        'pdf' => 'fa-file-pdf',
                                        'jpg' => 'fa-file-image',
                                        'jpeg' => 'fa-file-image',
                                        'png' => 'fa-file-image',
                                        'gif' => 'fa-file-image',
                                        'webp' => 'fa-file-image',
                                        'dwg' => 'fa-file-cad',
                                        'dxf' => 'fa-file-cad',
                                        'zip' => 'fa-file-archive',
                                        'rar' => 'fa-file-archive'
                                    ];
                                    $icon = $icons[$ext] ?? 'fa-file';
                            ?>
                            <div class="file-preview-item">
                                <span class="file-icon"><i class="fas <?= $icon ?>"></i></span>
                                <div class="file-name" title="<?= clean($file['original_name']) ?>"><?= clean($file['original_name']) ?></div>
                                <div class="file-size"><?= number_format($file['file_size'] / 1024 / 1024, 2) ?> MB</div>
                            </div>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn-submit">
                        <i class="fas <?= $edit_mode ? 'fa-save' : 'fa-paper-plane' ?>"></i>
                        <?= $edit_mode ? 'تحديث المشروع' : 'إرسال المشروع للمراجعة' ?>
                    </button>
                    
                </form>
            </div>
        </div>
        
    </div>
</div>

<script>
// ============================================
// دالة تحميل المدن
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
            console.error('Error loading cities:', error);
            citySelect.innerHTML = '<option value="">خطأ في التحميل</option>';
        });
}

// ============================================
// دالة معاينة الملفات
// ============================================
function previewFiles(input, containerId) {
    const container = document.getElementById(containerId);
    container.innerHTML = '';
    
    const fileIcons = {
        'pdf': { icon: 'fa-file-pdf', cls: 'pdf' },
        'jpg': { icon: 'fa-file-image', cls: 'image' },
        'jpeg': { icon: 'fa-file-image', cls: 'image' },
        'png': { icon: 'fa-file-image', cls: 'image' },
        'gif': { icon: 'fa-file-image', cls: 'image' },
        'webp': { icon: 'fa-file-image', cls: 'image' },
        'dwg': { icon: 'fa-file-cad', cls: 'cad' },
        'dxf': { icon: 'fa-file-cad', cls: 'cad' },
        'zip': { icon: 'fa-file-archive', cls: 'zip' },
        'rar': { icon: 'fa-file-archive', cls: 'zip' }
    };
    
    if (input.files) {
        Array.from(input.files).forEach(file => {
            const ext = file.name.split('.').pop().toLowerCase();
            const info = fileIcons[ext] || { icon: 'fa-file', cls: 'default' };
            const size = (file.size / 1024 / 1024).toFixed(2);
            
            const div = document.createElement('div');
            div.className = 'file-preview-item';
            div.innerHTML = `
                <span class="file-icon ${info.cls}"><i class="fas ${info.icon}"></i></span>
                <div class="file-name" title="${file.name}">${file.name}</div>
                <div class="file-size">${size} MB</div>
            `;
            container.appendChild(div);
        });
    }
}

// ============================================
// دالة معاينة الصورة الرئيسية
// ============================================
function previewMainImage(input) {
    const container = document.getElementById('main-image-preview');
    container.innerHTML = '';
    
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const img = document.createElement('img');
            img.src = e.target.result;
            img.alt = 'الصورة الرئيسية';
            container.appendChild(img);
            
            const label = document.createElement('span');
            label.className = 'text-xs text-gray-400';
            label.textContent = 'صورة جديدة (سيتم استبدال القديمة)';
            container.appendChild(label);
        }
        reader.readAsDataURL(input.files[0]);
    } else {
        // إعادة عرض الصورة القديمة إذا كانت موجودة
        <?php if ($edit_mode && isset($main_image) && $main_image): ?>
        const img = document.createElement('img');
        img.src = '<?= SITE_URL . '/' . $main_image['image_path'] ?>';
        img.alt = 'الصورة الرئيسية';
        container.appendChild(img);
        
        const label = document.createElement('span');
        label.className = 'text-xs text-gray-400';
        label.textContent = 'الصورة الرئيسية الحالية';
        container.appendChild(label);
        <?php else: ?>
        const placeholder = document.createElement('div');
        placeholder.className = 'placeholder';
        placeholder.innerHTML = '<span>لا توجد صورة رئيسية</span>';
        container.appendChild(placeholder);
        <?php endif; ?>
    }
}

// ============================================
// تحديث العملة تلقائياً عند تغيير الدولة
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    const countrySelect = document.getElementById('project-country');
    const currencyLabel = document.getElementById('currency-label');
    
    if (countrySelect) {
        // عند تحميل الصفحة، تأكد من عرض العملة الصحيحة (تم ضبطها مسبقاً من PHP)
        // لكن إن كانت الدولة محددة، نحدث العملة
        if (countrySelect.value) {
            updateCurrency(countrySelect.value);
        }
        
        // إضافة مستمع التغيير
        countrySelect.addEventListener('change', function() {
            const countryId = this.value;
            updateCurrency(countryId);
        });
    }
    
    function updateCurrency(countryId) {
        if (!currencyLabel) return;
        if (countryId && countryId != 0) {
            fetch('?action=get_currency&country_id=' + countryId)
                .then(response => response.json())
                .then(data => {
                    if (data.currency) {
                        currencyLabel.textContent = data.currency;
                    } else {
                        currencyLabel.textContent = '';
                    }
                })
                .catch(() => {
                    // في حالة الخطأ، لا نقوم بتغيير القيمة الحالية
                });
        } else {
            // في حال اختيار "اختر الدولة" نمسح العملة أو نستعيد العملة الافتراضية للمستخدم
            // يمكننا جلب عملة المستخدم إذا رغبنا، لكننا سنتركها فارغة
            currencyLabel.textContent = '';
        }
    }
});

// ============================================
// تحميل المدن إذا كانت الدولة محددة مسبقاً
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    const selectedCountry = document.getElementById('project-country').value;
    if (selectedCountry) {
        loadCities('project-country', 'project-city');
    }
});
</script>

<?php include 'includes/footer.php'; ?>
</body>
</html>