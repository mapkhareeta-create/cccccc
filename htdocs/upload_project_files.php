<?php
require_once 'config.php';

if (!isLoggedIn() || $_SESSION['user_type'] !== 'employer') {
    redirect('login.php');
}

$bid_id = (int)($_GET['bid_id'] ?? 0);
$project_id = (int)($_GET['project_id'] ?? 0);

if (!$bid_id || !$project_id) {
    redirect('my_bids.php');
}

// التحقق من أن المشروع يخص صاحب العمل
$stmt = $pdo->prepare("
    SELECT p.*, b.id as bid_id, b.contractor_id, u.name as contractor_name
    FROM projects p
    JOIN bids b ON b.project_id = p.id
    JOIN users u ON b.contractor_id = u.id
    WHERE p.id = ? AND p.employer_id = ? AND b.id = ?
");
$stmt->execute([$project_id, $_SESSION['user_id'], $bid_id]);
$project = $stmt->fetch();

if (!$project) {
    redirect('my_bids.php');
}

// جلب الملفات المرفوعة سابقاً
$stmt = $pdo->prepare("
    SELECT * FROM project_files 
    WHERE project_id = ? AND bid_id = ? 
    ORDER BY uploaded_at DESC
");
$stmt->execute([$project_id, $bid_id]);
$files = $stmt->fetchAll();

$upload_error = '';
$upload_success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_files'])) {
    $description = clean($_POST['description'] ?? '');
    
    // رفع الملفات
    $upload_dir = __DIR__ . '/assets/uploads/project_files/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    $uploaded_files = [];
    if (isset($_FILES['attachments'])) {
        foreach ($_FILES['attachments']['tmp_name'] as $key => $tmp_name) {
            if ($_FILES['attachments']['error'][$key] === UPLOAD_ERR_OK) {
                $file_name = $_FILES['attachments']['name'][$key];
                $file_size = $_FILES['attachments']['size'][$key];
                $file_tmp = $_FILES['attachments']['tmp_name'][$key];
                $file_type = $_FILES['attachments']['type'][$key];
                
                if ($file_size > 10 * 1024 * 1024) {
                    continue;
                }
                
                $allowed_ext = ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'zip', 'rar', 'dwg', 'dxf'];
                $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                
                if (!in_array($file_ext, $allowed_ext)) {
                    continue;
                }
                
                $new_filename = 'project_' . $project_id . '_' . time() . '_' . $key . '.' . $file_ext;
                $destination = $upload_dir . $new_filename;
                
                if (move_uploaded_file($file_tmp, $destination)) {
                    $uploaded_files[] = [
                        'filename' => $new_filename,
                        'original_name' => $file_name,
                        'file_size' => $file_size,
                        'file_type' => $file_type,
                        'file_ext' => $file_ext
                    ];
                }
            }
        }
    }
    
    if (!empty($uploaded_files)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO project_files (project_id, bid_id, uploader_id, filename, original_name, file_size, file_type, file_ext, description, uploaded_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            foreach ($uploaded_files as $file) {
                $stmt->execute([
                    $project_id,
                    $bid_id,
                    $_SESSION['user_id'],
                    $file['filename'],
                    $file['original_name'],
                    $file['file_size'],
                    $file['file_type'],
                    $file['file_ext'],
                    $description
                ]);
            }
            
            // إشعار للمقاول
            $stmt = $pdo->prepare("
                INSERT INTO notifications (user_id, title, message, link, type, created_at) 
                VALUES (?, ?, ?, ?, 'info', NOW())
            ");
            $stmt->execute([
                $project['contractor_id'],
                '📎 تم رفع ملفات جديدة للمشروع',
                'صاحب العمل ' . $_SESSION['user_name'] . ' رفع ملفات جديدة للمشروع: ' . $project['title'],
                SITE_URL . '/my_bids.php'
            ]);
            
            $upload_success = '✅ تم رفع ' . count($uploaded_files) . ' ملف بنجاح! تم إشعار المقاول.';
            
            // تحديث قائمة الملفات
            $stmt = $pdo->prepare("
                SELECT * FROM project_files 
                WHERE project_id = ? AND bid_id = ? 
                ORDER BY uploaded_at DESC
            ");
            $stmt->execute([$project_id, $bid_id]);
            $files = $stmt->fetchAll();
            
        } catch (Exception $e) {
            $upload_error = 'حدث خطأ: ' . $e->getMessage();
        }
    } else {
        $upload_error = 'لم يتم رفع أي ملف. تأكد من اختيار ملفات صالحة.';
    }
}

// حذف ملف
if (isset($_GET['delete_file']) && is_numeric($_GET['delete_file'])) {
    $file_id = (int)$_GET['delete_file'];
    
    $stmt = $pdo->prepare("SELECT * FROM project_files WHERE id = ? AND project_id = ?");
    $stmt->execute([$file_id, $project_id]);
    $file = $stmt->fetch();
    
    if ($file) {
        $file_path = __DIR__ . '/assets/uploads/project_files/' . $file['filename'];
        if (file_exists($file_path)) {
            unlink($file_path);
        }
        $stmt = $pdo->prepare("DELETE FROM project_files WHERE id = ?");
        $stmt->execute([$file_id]);
        redirect('upload_project_files.php?bid_id=' . $bid_id . '&project_id=' . $project_id);
    }
}

$page_title = 'رفع ملفات المشروع';
include 'includes/header.php';
?>

<style>
    .upload-container {
        max-width: 800px;
        margin: 0 auto;
        background: white;
        border-radius: 16px;
        padding: 30px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.05);
        border: 1px solid #e2e8f0;
    }
    
    .file-upload-area {
        border: 2px dashed #e2e8f0;
        border-radius: 12px;
        padding: 30px;
        text-align: center;
        cursor: pointer;
        transition: all 0.3s;
    }
    
    .file-upload-area:hover {
        border-color: #3b82f6;
        background: #f8fafc;
    }
    
    .file-upload-area .icon {
        font-size: 48px;
        color: #94a3b8;
        display: block;
        margin-bottom: 12px;
    }
    
    .file-list {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-top: 12px;
    }
    
    .file-item {
        background: #f1f5f9;
        padding: 6px 12px;
        border-radius: 8px;
        font-size: 12px;
        display: flex;
        align-items: center;
        gap: 6px;
        color: #1e293b;
    }
    
    .file-item .remove {
        cursor: pointer;
        color: #ef4444;
        font-weight: 700;
        margin-left: 4px;
    }
    
    .btn-upload {
        background: linear-gradient(135deg, #8b5cf6, #7c3aed);
        color: white;
        border: none;
        padding: 12px 32px;
        border-radius: 12px;
        font-weight: 700;
        font-size: 16px;
        cursor: pointer;
        transition: all 0.3s;
        width: 100%;
        margin-top: 16px;
    }
    
    .btn-upload:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(139,92,246,0.3);
    }
    
    .file-card {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        padding: 12px 16px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        transition: all 0.3s;
    }
    
    .file-card:hover {
        background: #f1f5f9;
    }
    
    .file-card .file-info {
        display: flex;
        align-items: center;
        gap: 12px;
    }
    
    .file-card .file-info i {
        font-size: 24px;
        color: #3b82f6;
    }
    
    .file-card .file-info .pdf { color: #dc2626; }
    .file-card .file-info .image { color: #8b5cf6; }
    .file-card .file-info .cad { color: #2563eb; }
    .file-card .file-info .zip { color: #f59e0b; }
    
    .file-card .file-actions a {
        color: #64748b;
        transition: all 0.3s;
    }
    
    .file-card .file-actions a:hover {
        color: #ef4444;
    }
</style>

<div class="py-8">
    <div class="max-w-4xl mx-auto px-4">
        <div class="upload-container">
            
            <!-- Header -->
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800">📎 رفع ملفات المشروع</h1>
                    <p class="text-gray-500 text-sm"><?= clean($project['title']) ?></p>
                    <p class="text-gray-400 text-xs">للمقاول: <?= clean($project['contractor_name']) ?></p>
                </div>
                <a href="<?= SITE_URL ?>/my_bids.php" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </a>
            </div>
            
            <?php if ($upload_error): ?>
            <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl mb-4 text-sm">
                <i class="fas fa-exclamation-circle ml-2"></i> <?= $upload_error ?>
            </div>
            <?php endif; ?>
            
            <?php if ($upload_success): ?>
            <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-xl mb-4 text-sm">
                <i class="fas fa-check-circle ml-2"></i> <?= $upload_success ?>
            </div>
            <?php endif; ?>
            
            <!-- رفع الملفات -->
            <form method="POST" action="" enctype="multipart/form-data">
                <input type="hidden" name="upload_files" value="1">
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">📝 وصف الملفات (اختياري)</label>
                    <input type="text" name="description" class="w-full border border-gray-300 rounded-xl px-4 py-2 focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-all" placeholder="مثال: مخططات الكهرباء، ملفات CAD، صور المشروع...">
                </div>
                
                <div class="file-upload-area" onclick="document.getElementById('fileInput').click()">
                    <span class="icon"><i class="fas fa-cloud-upload-alt"></i></span>
                    <div class="text-lg font-semibold text-gray-700">اضغط لرفع الملفات</div>
                    <div class="text-sm text-gray-500">PDF, CAD (DWG/DXF), صور, ZIP (حد أقصى 10MB لكل ملف)</div>
                    <input type="file" name="attachments[]" id="fileInput" class="hidden" multiple accept=".pdf,.jpg,.jpeg,.png,.gif,.webp,.zip,.rar,.dwg,.dxf" onchange="updateFileList(this)">
                </div>
                
                <div class="file-list" id="fileList"></div>
                
                <button type="submit" class="btn-upload">
                    <i class="fas fa-upload ml-2"></i> رفع الملفات
                </button>
            </form>
            
            <hr class="my-6 border-gray-200">
            
            <!-- الملفات المرفوعة -->
            <div>
                <h3 class="text-lg font-bold text-gray-800 mb-4">📂 الملفات المرفوعة</h3>
                
                <?php if (empty($files)): ?>
                <div class="text-center py-8 text-gray-400">
                    <i class="fas fa-folder-open text-4xl mb-2 block"></i>
                    <p>لا توجد ملفات مرفوعة حتى الآن</p>
                </div>
                <?php else: ?>
                    <?php foreach ($files as $file): ?>
                    <div class="file-card mb-2">
                        <div class="file-info">
                            <?php
                                $ext = strtolower($file['file_ext'] ?? '');
                                $icon_class = 'fa-file';
                                $color_class = '';
                                if ($ext === 'pdf') { $icon_class = 'fa-file-pdf'; $color_class = 'pdf'; }
                                elseif (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) { $icon_class = 'fa-file-image'; $color_class = 'image'; }
                                elseif (in_array($ext, ['dwg', 'dxf'])) { $icon_class = 'fa-file-cad'; $color_class = 'cad'; }
                                elseif (in_array($ext, ['zip', 'rar'])) { $icon_class = 'fa-file-archive'; $color_class = 'zip'; }
                            ?>
                            <i class="fas <?= $icon_class ?> <?= $color_class ?>"></i>
                            <div>
                                <div class="font-medium text-gray-800"><?= clean($file['original_name']) ?></div>
                                <div class="text-xs text-gray-400">
                                    <?= round($file['file_size'] / 1024) ?> KB 
                                    <?php if ($file['description']): ?>
                                    - <?= clean($file['description']) ?>
                                    <?php endif; ?>
                                    <span class="mr-2">📅 <?= date('Y-m-d H:i', strtotime($file['uploaded_at'])) ?></span>
                                </div>
                            </div>
                        </div>
                        <div class="file-actions flex gap-3">
                            <a href="<?= SITE_URL ?>/assets/uploads/project_files/<?= $file['filename'] ?>" target="_blank" class="text-blue-600 hover:text-blue-800" title="تحميل">
                                <i class="fas fa-download"></i>
                            </a>
                            <a href="<?= SITE_URL ?>/upload_project_files.php?delete_file=<?= $file['id'] ?>&bid_id=<?= $bid_id ?>&project_id=<?= $project_id ?>" 
                               onclick="return confirm('هل أنت متأكد من حذف هذا الملف؟')" 
                               class="text-red-500 hover:text-red-700" title="حذف">
                                <i class="fas fa-trash"></i>
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
        </div>
    </div>
</div>

<script>
function updateFileList(input) {
    const fileList = document.getElementById('fileList');
    fileList.innerHTML = '';
    const files = input.files;
    
    const fileIcons = {
        'pdf': 'fa-file-pdf',
        'jpg': 'fa-file-image',
        'jpeg': 'fa-file-image',
        'png': 'fa-file-image',
        'gif': 'fa-file-image',
        'webp': 'fa-file-image',
        'zip': 'fa-file-archive',
        'rar': 'fa-file-archive',
        'dwg': 'fa-file-cad',
        'dxf': 'fa-file-cad'
    };
    
    for (let i = 0; i < files.length; i++) {
        const file = files[i];
        const ext = file.name.split('.').pop().toLowerCase();
        const icon = fileIcons[ext] || 'fa-file';
        const size = (file.size / 1024 / 1024).toFixed(2);
        
        const div = document.createElement('div');
        div.className = 'file-item';
        div.innerHTML = `
            <i class="fas ${icon}"></i>
            ${file.name} (${size}MB)
        `;
        fileList.appendChild(div);
    }
}
</script>

<?php include 'includes/footer.php'; ?>