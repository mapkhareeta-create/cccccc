<?php
require_once 'config.php';

// دالة مساعدة لجلب الملفات المرفوعة لعطاء معين
function getBidAttachments($bid_id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT * FROM bid_attachments WHERE bid_id = ? ORDER BY uploaded_at DESC");
        $stmt->execute([$bid_id]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

// دالة لجلب غرفة العمل
function getWorkspaceId($project_id, $bid_id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT id FROM project_workspace WHERE project_id = ? AND bid_id = ?");
        $stmt->execute([$project_id, $bid_id]);
        $workspace = $stmt->fetch();
        return $workspace ? $workspace['id'] : null;
    } catch (Exception $e) {
        return null;
    }
}

// دالة لإنشاء غرفة العمل عند قبول العطاء
function createWorkspace($project_id, $bid_id) {
    global $pdo;
    try {
        $existing = getWorkspaceId($project_id, $bid_id);
        if ($existing) return $existing;
        
        $stmt = $pdo->prepare("INSERT INTO project_workspace (project_id, bid_id, status, created_at) VALUES (?, ?, 'active', NOW())");
        $stmt->execute([$project_id, $bid_id]);
        return $pdo->lastInsertId();
    } catch (Exception $e) {
        error_log("Error creating workspace: " . $e->getMessage());
        return null;
    }
}

// جلب ترتيب العرض
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'highest';

// جلب بيانات المشروع
$project_id = (int)($_GET['id'] ?? 0);
if (!$project_id) {
    redirect('projects_list.php');
}

// جلب تفاصيل المشروع
$stmt = $pdo->prepare("
    SELECT p.*, u.name as employer_name, u.id as employer_id,
           c.name_ar as country_name, ci.name_ar as city_name,
           co.currency_ar, co.currency_code
    FROM projects p
    JOIN users u ON p.employer_id = u.id
    LEFT JOIN countries c ON p.country_id = c.id
    LEFT JOIN cities ci ON p.city_id = ci.id
    LEFT JOIN countries co ON p.country_id = co.id
    WHERE p.id = ?
");
$stmt->execute([$project_id]);
$project = $stmt->fetch();

if (!$project) {
    redirect('projects_list.php');
}

// جلب صور المشروع
$stmt = $pdo->prepare("SELECT image_path FROM project_images WHERE project_id = ?");
$stmt->execute([$project_id]);
$images = $stmt->fetchAll();

// جلب عدد العطاءات
$stmt = $pdo->prepare("SELECT COUNT(*) FROM bids WHERE project_id = ?");
$stmt->execute([$project_id]);
$bids_count = $stmt->fetchColumn();

// ============================================
// جلب العطاءات مع ترتيب حسب السعر (لصاحب العمل)
// ============================================
$bids = [];
$all_bids_count = 0;
$avg_bid_amount = 0;

if (isLoggedIn() && $_SESSION['user_id'] == $project['employer_id']) {
    $order_by = $sort === 'highest' ? 'b.amount DESC' : 'b.amount ASC';
    $stmt = $pdo->prepare("
        SELECT 
            b.*,
            u.id as contractor_id,
            u.name as contractor_name,
            u.avatar as contractor_avatar,
            u.email as contractor_email,
            u.phone as contractor_phone,
            (SELECT AVG(rating) FROM reviews WHERE reviewed_id = u.id) as contractor_rating,
            (SELECT COUNT(*) FROM reviews WHERE reviewed_id = u.id) as contractor_reviews_count
        FROM bids b
        JOIN users u ON b.contractor_id = u.id
        WHERE b.project_id = ?
        ORDER BY {$order_by}
    ");
    $stmt->execute([$project_id]);
    $bids = $stmt->fetchAll();
    $all_bids_count = count($bids);
    
    $stmt_avg = $pdo->prepare("SELECT AVG(amount) FROM bids WHERE project_id = ?");
    $stmt_avg->execute([$project_id]);
    $avg_bid_amount = $stmt_avg->fetchColumn();
}

$is_owner = (isLoggedIn() && $_SESSION['user_id'] == $project['employer_id']);

$has_bid = false;
$user_bid_id = null;
if (isLoggedIn() && $_SESSION['user_type'] === 'contractor') {
    $stmt = $pdo->prepare("SELECT id FROM bids WHERE project_id = ? AND contractor_id = ?");
    $stmt->execute([$project_id, $_SESSION['user_id']]);
    $user_bid = $stmt->fetch();
    if ($user_bid) {
        $has_bid = true;
        $user_bid_id = $user_bid['id'];
    }
}

// ============================================
// معالجة قبول/رفض العطاء (لصاحب العمل)
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!isLoggedIn() || !$is_owner) {
        redirect('login.php');
    }
    
    $bid_id = (int)($_POST['bid_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    
    if ($bid_id && in_array($action, ['accept', 'reject'])) {
        try {
            // التحقق من أن العطاء في حالة pending_employer (وافق عليه الأدمن)
            $stmt_check = $pdo->prepare("SELECT status FROM bids WHERE id = ? AND project_id = ?");
            $stmt_check->execute([$bid_id, $project_id]);
            $current_status = $stmt_check->fetchColumn();
            
            if ($current_status !== 'pending_employer') {
                echo "<script>alert('⚠️ لا يمكن قبول أو رفض هذا العطاء لأنه ليس في حالة انتظار موافقتك.');</script>";
                throw new Exception('Invalid bid status');
            }
            
            $new_status = $action === 'accept' ? 'accepted' : 'rejected';
            $stmt = $pdo->prepare("UPDATE bids SET status = ? WHERE id = ? AND project_id = ?");
            $stmt->execute([$new_status, $bid_id, $project_id]);
            
            if ($stmt->rowCount() > 0) {
                // جلب بيانات العطاء للإشعار
                $stmt_bid = $pdo->prepare("SELECT contractor_id, amount FROM bids WHERE id = ?");
                $stmt_bid->execute([$bid_id]);
                $bid_data = $stmt_bid->fetch();
                
                // إشعار للمقاول
                if ($bid_data) {
                    $status_text = $action === 'accept' ? '✅ تم قبول عطائك نهائياً' : '❌ تم رفض عطائك';
                    $stmt_notify = $pdo->prepare("
                        INSERT INTO notifications (user_id, title, message, link, type, created_at) 
                        VALUES (?, ?, ?, ?, 'bid', NOW())
                    ");
                    $stmt_notify->execute([
                        $bid_data['contractor_id'],
                        $status_text,
                        'صاحب العمل ' . $_SESSION['user_name'] . ' ' . ($action === 'accept' ? 'قبل' : 'رفض') . ' عطائك على مشروع: ' . $project['title'] . ' بمبلغ ' . number_format($bid_data['amount']) . ' ر.س',
                        SITE_URL . '/my_bids.php'
                    ]);
                }
                
                // إذا تم القبول، إنشاء غرفة العمل وتحديث حالة المشروع
                if ($action === 'accept') {
                    $workspace_id = createWorkspace($project_id, $bid_id);
                    
                    $stmt_project = $pdo->prepare("UPDATE projects SET status = 'in_progress' WHERE id = ?");
                    $stmt_project->execute([$project_id]);
                    
                    // إشعار إضافي لصاحب العمل والمقاول بإنشاء غرفة العمل
                    createNotification(
                        $_SESSION['user_id'],
                        '✅ تم إنشاء غرفة العمل',
                        'تم إنشاء غرفة العمل للمشروع: ' . $project['title'],
                        'success',
                        SITE_URL . '/project_workspace.php?id=' . $workspace_id
                    );
                    
                    if ($bid_data) {
                        createNotification(
                            $bid_data['contractor_id'],
                            '✅ تم إنشاء غرفة العمل',
                            'تم إنشاء غرفة العمل للمشروع: ' . $project['title'],
                            'success',
                            SITE_URL . '/project_workspace.php?id=' . $workspace_id
                        );
                    }
                }
                
                $message = $action === 'accept' ? '✅ تم قبول العطاء وإنشاء غرفة العمل بنجاح' : '❌ تم رفض العطاء';
                echo "<script>alert('$message'); window.location.href = window.location.href;</script>";
            }
        } catch (Exception $e) {
            echo "<script>alert('حدث خطأ: " . addslashes($e->getMessage()) . "');</script>";
        }
    }
}

// ============================================
// معالجة اكتمال المشروع
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['complete_project'])) {
    if (!isLoggedIn() || !$is_owner) {
        redirect('login.php');
    }
    
    $bid_id = (int)($_POST['bid_id'] ?? 0);
    
    if ($bid_id) {
        try {
            $stmt = $pdo->prepare("UPDATE projects SET status = 'completed' WHERE id = ?");
            $stmt->execute([$project_id]);
            
            // تحديث حالة غرفة العمل إلى مكتملة
            $workspace_id = getWorkspaceId($project_id, $bid_id);
            if ($workspace_id) {
                $stmt_w = $pdo->prepare("UPDATE project_workspace SET status = 'completed' WHERE id = ?");
                $stmt_w->execute([$workspace_id]);
            }
            
            // إشعار للمقاول
            $stmt_bid = $pdo->prepare("SELECT contractor_id FROM bids WHERE id = ?");
            $stmt_bid->execute([$bid_id]);
            $bid_data = $stmt_bid->fetch();
            
            if ($bid_data) {
                $stmt_notify = $pdo->prepare("
                    INSERT INTO notifications (user_id, title, message, link, type, created_at) 
                    VALUES (?, ?, ?, ?, 'project', NOW())
                ");
                $stmt_notify->execute([
                    $bid_data['contractor_id'],
                    '✅ تم اكتمال المشروع',
                    'تم اكتمال المشروع: ' . $project['title'] . ' بنجاح',
                    SITE_URL . '/my_bids.php'
                ]);
            }
            
            echo "<script>alert('✅ تم اكتمال المشروع بنجاح'); window.location.href = window.location.href;</script>";
        } catch (Exception $e) {
            echo "<script>alert('حدث خطأ: " . addslashes($e->getMessage()) . "');</script>";
        }
    }
}

// ============================================
// معالجة تقديم العطاء (المقاول)
// ============================================
$bid_error = '';
$bid_success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_bid'])) {
    if (!isLoggedIn()) {
        redirect('login.php');
    }
    
    if ($_SESSION['user_type'] !== 'contractor') {
        $bid_error = 'يمكن للمقاولين فقط تقديم عطاءات';
    } elseif ($is_owner) {
        $bid_error = 'لا يمكنك تقديم عطاء على مشروعك الخاص';
    } elseif ($has_bid) {
        $bid_error = 'لقد قدمت عطاء بالفعل على هذا المشروع';
    } else {
        $amount = (float)($_POST['amount'] ?? 0);
        $proposal = clean($_POST['proposal'] ?? '');
        $duration_days = (int)($_POST['duration_days'] ?? 0);
        
        if ($amount <= 0) {
            $bid_error = 'يرجى إدخال مبلغ صحيح';
        } elseif (empty($proposal)) {
            $bid_error = 'يرجى كتابة الاقتراح';
        } elseif ($duration_days <= 0) {
            $bid_error = 'يرجى إدخال المدة المتوقعة';
        } else {
            try {
                $pdo->beginTransaction();
                
                // إدراج العطاء بحالة pending (في انتظار موافقة الأدمن)
                $stmt = $pdo->prepare("
                    INSERT INTO bids (project_id, contractor_id, amount, proposal, duration_days, status, created_at) 
                    VALUES (?, ?, ?, ?, ?, 'pending', NOW())
                ");
                $stmt->execute([$project_id, $_SESSION['user_id'], $amount, $proposal, $duration_days]);
                $bid_id = $pdo->lastInsertId();
                
                // رفع الملفات
                $upload_dir = __DIR__ . '/assets/uploads/bid_files/';
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
                            
                            if ($file_size > 10 * 1024 * 1024) continue;
                            
                            $allowed_ext = ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'zip', 'rar', 'dwg', 'dxf'];
                            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                            
                            if (!in_array($file_ext, $allowed_ext)) continue;
                            
                            $new_filename = 'bid_' . $bid_id . '_' . time() . '_' . $key . '.' . $file_ext;
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
                
                // حفظ الملفات في قاعدة البيانات
                if (!empty($uploaded_files)) {
                    try {
                        $stmt = $pdo->query("SHOW TABLES LIKE 'bid_attachments'");
                        if ($stmt->rowCount() > 0) {
                            $stmt = $pdo->prepare("
                                INSERT INTO bid_attachments (bid_id, filename, original_name, file_size, file_type, file_ext, uploaded_at) 
                                VALUES (?, ?, ?, ?, ?, ?, NOW())
                            ");
                            foreach ($uploaded_files as $file) {
                                $stmt->execute([
                                    $bid_id,
                                    $file['filename'],
                                    $file['original_name'],
                                    $file['file_size'],
                                    $file['file_type'],
                                    $file['file_ext']
                                ]);
                            }
                        }
                    } catch (Exception $e) {
                        error_log("Bid attachments table not found: " . $e->getMessage());
                    }
                }
                
                // ===========================================================
                // ❌ تم حذف إشعار صاحب العمل من هنا
                // لن يتم إشعاره إلا بعد موافقة الأدمن (في admin.php)
                // ===========================================================
                
                $pdo->commit();
                
                $bid_success = '✅ تم تقديم عطائك بنجاح! سيتم مراجعته من قبل الإدارة وإشعار صاحب العمل عند الموافقة.';
                $has_bid = true;
                $user_bid_id = $bid_id;
                
            } catch (Exception $e) {
                $pdo->rollBack();
                $bid_error = 'حدث خطأ: ' . $e->getMessage();
            }
        }
    }
}

$categories = getProjectCategories();
$page_title = clean($project['title']);
include 'includes/header.php';
?>

<style>
    /* نفس التنسيقات السابقة ... */
    .project-detail-container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 20px;
    }
    
    .project-header {
        background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 50%, #2563eb 100%);
        border-radius: 16px;
        padding: 30px;
        color: white;
        margin-bottom: 24px;
    }
    
    .project-header .status-badge {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
        background: rgba(255,255,255,0.2);
        backdrop-filter: blur(4px);
    }
    
    .project-header .status-badge.open { background: rgba(34,197,94,0.3); }
    .project-header .status-badge.in_progress { background: rgba(245,158,11,0.3); }
    .project-header .status-badge.completed { background: rgba(59,130,246,0.3); }
    .project-header .status-badge.cancelled { background: rgba(239,68,68,0.3); }
    
    .info-card {
        background: white;
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        border: 1px solid #e2e8f0;
        margin-bottom: 20px;
    }
    
    .info-card .label {
        font-size: 12px;
        font-weight: 600;
        color: #94a3b8;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .info-card .value {
        font-size: 16px;
        font-weight: 600;
        color: #0f172a;
        margin-top: 4px;
    }
    
    .gallery-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
        gap: 12px;
    }
    
    .gallery-grid img {
        width: 100%;
        height: 150px;
        object-fit: cover;
        border-radius: 8px;
        cursor: pointer;
        transition: transform 0.3s;
    }
    
    .gallery-grid img:hover {
        transform: scale(1.05);
    }
    
    .bid-form {
        background: white;
        border-radius: 16px;
        padding: 24px;
        border: 2px solid #e2e8f0;
        margin-top: 20px;
    }
    
    .bid-form .form-group {
        margin-bottom: 16px;
    }
    
    .bid-form label {
        display: block;
        font-weight: 600;
        font-size: 14px;
        color: #1e293b;
        margin-bottom: 4px;
    }
    
    .bid-form .form-control {
        width: 100%;
        padding: 12px 16px;
        border: 2px solid #e2e8f0;
        border-radius: 10px;
        font-size: 14px;
        transition: all 0.3s;
    }
    
    .bid-form .form-control:focus {
        outline: none;
        border-color: #3b82f6;
        box-shadow: 0 0 0 4px rgba(59,130,246,0.1);
    }
    
    .bid-form textarea.form-control {
        min-height: 120px;
        resize: vertical;
    }
    
    .btn-submit-bid {
        background: linear-gradient(135deg, #22c55e, #16a34a);
        color: white;
        border: none;
        padding: 14px 32px;
        border-radius: 12px;
        font-weight: 700;
        font-size: 16px;
        cursor: pointer;
        transition: all 0.3s;
        width: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
    }
    
    .btn-submit-bid:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(34,197,94,0.3);
    }
    
    .btn-submit-bid:disabled {
        background: #94a3b8;
        cursor: not-allowed;
        transform: none;
        box-shadow: none;
    }
    
    .file-upload-area {
        border: 2px dashed #e2e8f0;
        border-radius: 10px;
        padding: 20px;
        text-align: center;
        cursor: pointer;
        transition: all 0.3s;
    }
    
    .file-upload-area:hover {
        border-color: #3b82f6;
        background: #f8fafc;
    }
    
    .file-upload-area .icon {
        font-size: 32px;
        color: #94a3b8;
        display: block;
        margin-bottom: 8px;
    }
    
    .file-upload-area .text {
        font-size: 14px;
        color: #64748b;
    }
    
    .file-upload-area .sub-text {
        font-size: 12px;
        color: #94a3b8;
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
    
    /* ===== العطاءات ===== */
    .bids-section {
        margin-top: 30px;
        background: white;
        border-radius: 16px;
        padding: 24px;
        border: 1px solid #e2e8f0;
    }
    
    .bids-section .section-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 15px;
        margin-bottom: 20px;
        padding-bottom: 15px;
        border-bottom: 2px solid #f1f5f9;
    }
    
    .bids-section .section-header h2 {
        font-size: 20px;
        font-weight: 800;
        color: #0f172a;
    }
    
    .bids-section .section-header .stats {
        display: flex;
        gap: 20px;
        font-size: 14px;
        color: #64748b;
    }
    
    .bids-section .section-header .stats span {
        background: #f1f5f9;
        padding: 4px 12px;
        border-radius: 8px;
    }
    
    .sort-buttons {
        display: flex;
        gap: 10px;
    }
    
    .sort-btn {
        padding: 6px 16px;
        border-radius: 8px;
        font-size: 13px;
        font-weight: 600;
        border: 2px solid #e2e8f0;
        background: white;
        color: #64748b;
        text-decoration: none;
        transition: all 0.3s;
        cursor: pointer;
    }
    
    .sort-btn:hover {
        border-color: #94a3b8;
        color: #1e293b;
    }
    
    .sort-btn.active {
        border-color: #3b82f6;
        background: #eff6ff;
        color: #3b82f6;
    }
    
    .sort-btn.active.highest {
        border-color: #22c55e;
        background: #f0fdf4;
        color: #16a34a;
    }
    
    .sort-btn.active.lowest {
        border-color: #f59e0b;
        background: #fffbeb;
        color: #d97706;
    }
    
    .bid-card {
        background: #f8fafc;
        border-radius: 12px;
        padding: 16px 20px;
        margin-bottom: 12px;
        border: 1px solid #e2e8f0;
        transition: all 0.3s;
    }
    
    .bid-card:hover {
        border-color: #94a3b8;
        box-shadow: 0 4px 12px rgba(0,0,0,0.05);
    }
    
    .bid-card.accepted {
        background: #f0fdf4;
        border-color: #86efac;
    }
    
    .bid-card.rejected {
        background: #fef2f2;
        border-color: #fca5a5;
    }
    
    .bid-card.pending {
        background: #fffbeb;
        border-color: #fcd34d;
    }
    
    .bid-card.pending_employer {
        background: #eff6ff;
        border-color: #93bbfc;
    }
    
    .bid-card .bid-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 10px;
    }
    
    .bid-card .contractor-info {
        display: flex;
        align-items: center;
        gap: 12px;
    }
    
    .bid-card .contractor-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        overflow: hidden;
        background: linear-gradient(135deg, #3b82f6, #6366f1);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: 700;
        font-size: 16px;
        flex-shrink: 0;
    }
    
    .bid-card .contractor-avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    
    .bid-card .contractor-name {
        font-weight: 600;
        color: #0f172a;
    }
    
    .bid-card .contractor-name a {
        color: #0f172a;
        text-decoration: none;
        transition: color 0.3s;
    }
    
    .bid-card .contractor-name a:hover {
        color: #3b82f6;
    }
    
    .bid-card .bid-amount {
        font-size: 18px;
        font-weight: 800;
        color: #16a34a;
    }
    
    .bid-card .bid-status {
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
    }
    
    .bid-card .bid-status.pending {
        background: #fef3c7;
        color: #d97706;
    }
    
    .bid-card .bid-status.pending_employer {
        background: #dbeafe;
        color: #2563eb;
    }
    
    .bid-card .bid-status.accepted {
        background: #d1fae5;
        color: #059669;
    }
    
    .bid-card .bid-status.rejected {
        background: #fee2e2;
        color: #dc2626;
    }
    
    .bid-card .bid-proposal {
        font-size: 14px;
        color: #475569;
        margin-top: 8px;
        padding: 8px 12px;
        background: white;
        border-radius: 8px;
        border-right: 3px solid #e2e8f0;
    }
    
    .bid-card .bid-files {
        margin-top: 8px;
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
    }
    
    .bid-card .bid-files .file-link {
        background: #e2e8f0;
        padding: 3px 10px;
        border-radius: 6px;
        font-size: 11px;
        color: #1e293b;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        transition: all 0.3s;
    }
    
    .bid-card .bid-files .file-link:hover {
        background: #cbd5e1;
    }
    
    .bid-card .bid-actions {
        display: flex;
        gap: 8px;
        margin-top: 10px;
        flex-wrap: wrap;
        padding-top: 10px;
        border-top: 1px solid #e2e8f0;
    }
    
    .bid-card .bid-actions .btn-sm {
        padding: 6px 16px;
        font-size: 12px;
        font-weight: 600;
        border-radius: 8px;
        border: none;
        cursor: pointer;
        transition: all 0.3s;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    
    .bid-card .bid-actions .btn-sm:hover {
        transform: translateY(-2px);
    }
    
    .bid-card .bid-actions .btn-accept {
        background: #22c55e;
        color: white;
    }
    
    .bid-card .bid-actions .btn-accept:hover {
        background: #16a34a;
        box-shadow: 0 4px 12px rgba(34,197,94,0.3);
    }
    
    .bid-card .bid-actions .btn-reject {
        background: #ef4444;
        color: white;
    }
    
    .bid-card .bid-actions .btn-reject:hover {
        background: #dc2626;
        box-shadow: 0 4px 12px rgba(239,68,68,0.3);
    }
    
    .bid-card .bid-actions .btn-chat {
        background: #3b82f6;
        color: white;
    }
    
    .bid-card .bid-actions .btn-chat:hover {
        background: #2563eb;
        box-shadow: 0 4px 12px rgba(59,130,246,0.3);
    }
    
    .bid-card .bid-actions .btn-upload {
        background: #8b5cf6;
        color: white;
    }
    
    .bid-card .bid-actions .btn-upload:hover {
        background: #7c3aed;
        box-shadow: 0 4px 12px rgba(139,92,246,0.3);
    }
    
    .bid-card .bid-actions .btn-workspace {
        background: #6366f1;
        color: white;
    }
    
    .bid-card .bid-actions .btn-workspace:hover {
        background: #4f46e5;
        box-shadow: 0 4px 12px rgba(99,102,241,0.3);
    }
    
    .bid-card .bid-actions .btn-complete {
        background: #f59e0b;
        color: white;
    }
    
    .bid-card .bid-actions .btn-complete:hover {
        background: #d97706;
        box-shadow: 0 4px 12px rgba(245,158,11,0.3);
    }
    
    .bid-card .bid-actions .btn-rate {
        background: #ec4899;
        color: white;
    }
    
    .bid-card .bid-actions .btn-rate:hover {
        background: #db2777;
        box-shadow: 0 4px 12px rgba(236,72,153,0.3);
    }
    
    .bid-card .bid-actions .btn-details {
        background: #64748b;
        color: white;
    }
    
    .bid-card .bid-actions .btn-details:hover {
        background: #475569;
        box-shadow: 0 4px 12px rgba(100,116,139,0.3);
    }
    
    .bid-status-badge-accepted {
        background: #d1fae5;
        color: #059669;
        padding: 4px 14px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 700;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    
    .bid-status-badge-rejected {
        background: #fee2e2;
        color: #dc2626;
        padding: 4px 14px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 700;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    
    .no-bids {
        text-align: center;
        padding: 40px 20px;
        color: #94a3b8;
    }
    
    .no-bids .icon {
        font-size: 48px;
        display: block;
        margin-bottom: 12px;
    }
    
    .btn-back-to-projects {
        display: inline-flex !important;
        align-items: center !important;
        gap: 8px !important;
        padding: 8px 20px !important;
        background: rgba(255,255,255,0.1) !important;
        color: white !important;
        border-radius: 10px !important;
        text-decoration: none !important;
        font-weight: 600 !important;
        font-size: 14px !important;
        transition: all 0.3s ease !important;
        border: 1px solid rgba(255,255,255,0.1) !important;
    }
    
    .btn-back-to-projects:hover {
        background: rgba(255,255,255,0.2) !important;
        transform: translateX(-4px) !important;
    }
    
    .btn-back-to-bids {
        display: inline-flex !important;
        align-items: center !important;
        gap: 8px !important;
        padding: 8px 20px !important;
        background: rgba(59,130,246,0.15) !important;
        color: #60a5fa !important;
        border-radius: 10px !important;
        text-decoration: none !important;
        font-weight: 600 !important;
        font-size: 14px !important;
        transition: all 0.3s ease !important;
        border: 1px solid rgba(59,130,246,0.2) !important;
    }
    
    .btn-back-to-bids:hover {
        background: rgba(59,130,246,0.25) !important;
        transform: translateX(-4px) !important;
    }
    
    .my-bid-tag {
        background: rgba(59,130,246,0.15);
        color: #60a5fa;
        padding: 2px 10px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: 600;
        border: 1px solid rgba(59,130,246,0.2);
    }
    
    @media (max-width: 768px) {
        .project-header { padding: 20px; }
        .project-header h1 { font-size: 20px; }
        .gallery-grid { grid-template-columns: repeat(auto-fill, minmax(100px, 1fr)); }
        .gallery-grid img { height: 100px; }
        .bids-section .section-header { flex-direction: column; align-items: stretch; }
        .sort-buttons { justify-content: center; }
        .bid-card .bid-row { flex-direction: column; align-items: stretch; }
        .bid-card .bid-actions { justify-content: center; }
        .bid-card .bid-actions .btn-sm { font-size: 11px; padding: 4px 10px; }
    }
</style>

<div class="project-detail-container">
    
    <!-- رأس المشروع -->
    <div class="project-header">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <div class="flex items-center gap-3 flex-wrap mb-2">
                    <h1 class="text-2xl md:text-3xl font-bold"><?= clean($project['title']) ?></h1>
                    <span class="status-badge <?= $project['status'] ?>">
                        <?php
                            $status_labels = [
                                'open' => '🟢 مفتوح للعطاءات',
                                'in_progress' => '🟡 قيد التنفيذ',
                                'completed' => '🔵 مكتمل',
                                'cancelled' => '🔴 ملغي'
                            ];
                            echo $status_labels[$project['status']] ?? $project['status'];
                        ?>
                    </span>
                </div>
                <div class="flex flex-wrap gap-4 text-sm text-blue-200">
                    <span><i class="fas fa-user ml-1"></i> <?= clean($project['employer_name']) ?></span>
                    <span><i class="fas fa-map-marker-alt ml-1"></i> <?= clean($project['city_name'] ?? '') ?>, <?= clean($project['country_name'] ?? '') ?></span>
                    <span><i class="fas fa-calendar ml-1"></i> <?= date('Y-m-d', strtotime($project['created_at'])) ?></span>
                </div>
            </div>
            <div class="text-left">
                <div class="text-2xl font-bold text-white">
                    <?php if ($project['budget_min'] && $project['budget_max']): ?>
                        <?= number_format($project['budget_min']) ?> - <?= number_format($project['budget_max']) ?> <?= clean($project['currency_ar'] ?? '') ?>
                    <?php elseif ($project['budget_min']): ?>
                        من <?= number_format($project['budget_min']) ?> <?= clean($project['currency_ar'] ?? '') ?>
                    <?php else: ?>
                        حسب الاتفاق
                    <?php endif; ?>
                </div>
                <div class="text-sm text-blue-200">
                    <i class="fas fa-gavel ml-1"></i> <?= $bids_count ?> عطاء
                </div>
            </div>
        </div>
        
        <div class="flex flex-wrap gap-3 mt-4 pt-4 border-t border-blue-400/20">
            <a href="<?= SITE_URL ?>/projects_list.php" class="btn-back-to-projects">
                <i class="fas fa-arrow-right"></i> العودة للمشاريع
            </a>
            <a href="<?= SITE_URL ?>/my_bids.php" class="btn-back-to-bids">
                <i class="fas fa-arrow-right"></i> العودة للعطاءات
            </a>
        </div>
    </div>
    
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        <!-- العمود الأيسر: معلومات المشروع -->
        <div class="lg:col-span-2">
            
            <div class="info-card">
                <div class="label">📝 وصف المشروع</div>
                <div class="value" style="font-weight: 400; line-height: 1.8;">
                    <?= nl2br(clean($project['description'])) ?>
                </div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="info-card">
                    <div class="label">🏷️ التصنيف</div>
                    <div class="value"><?= $categories[$project['category']] ?? '🏗️' ?> <?= clean($project['category']) ?></div>
                </div>
                <div class="info-card">
                    <div class="label">📍 الموقع</div>
                    <div class="value"><?= clean($project['city_name'] ?? '') ?>, <?= clean($project['country_name'] ?? '') ?></div>
                    <?php if ($project['address']): ?>
                    <div class="text-sm text-gray-500 mt-1"><?= clean($project['address']) ?></div>
                    <?php endif; ?>
                </div>
                <?php if ($project['deadline']): ?>
                <div class="info-card">
                    <div class="label">⏰ الموعد النهائي</div>
                    <div class="value"><?= date('Y-m-d', strtotime($project['deadline'])) ?></div>
                </div>
                <?php endif; ?>
                <div class="info-card">
                    <div class="label">📅 تاريخ النشر</div>
                    <div class="value"><?= date('Y-m-d', strtotime($project['created_at'])) ?></div>
                </div>
            </div>
            
            <?php if (!empty($images)): ?>
            <div class="info-card">
                <div class="label">🖼️ صور المشروع</div>
                <div class="gallery-grid mt-3">
                    <?php foreach ($images as $img): ?>
                    <img src="<?= SITE_URL ?>/<?= $img['image_path'] ?>" alt="صورة المشروع" onclick="openImage(this.src)">
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
        </div>
        
        <!-- العمود الأيمن: تقديم العطاء -->
        <div class="lg:col-span-1">
            
            <div class="info-card">
                <div class="label">👤 صاحب العمل</div>
                <div class="value text-lg"><?= clean($project['employer_name']) ?></div>
                <?php if ($is_owner): ?>
                <div class="text-sm text-blue-600 font-semibold mt-1">هذا مشروعك الخاص</div>
                <?php endif; ?>
            </div>
            
            <?php if ($project['status'] === 'open'): ?>
                
                <?php if (!isLoggedIn()): ?>
                <div class="info-card text-center">
                    <p class="text-gray-600">🔒 يرجى <a href="<?= SITE_URL ?>/login.php" class="text-blue-600 font-bold">تسجيل الدخول</a> لتقديم عطاء</p>
                </div>
                
                <?php elseif ($_SESSION['user_type'] !== 'contractor'): ?>
                <div class="info-card text-center">
                    <p class="text-gray-600">🔒 فقط المقاولون يمكنهم تقديم عطاءات</p>
                    <a href="<?= SITE_URL ?>/register.php?type=contractor" class="text-blue-600 font-bold">سجل كمقاول</a>
                </div>
                
                <?php elseif ($is_owner): ?>
                <div class="info-card text-center">
                    <p class="text-gray-600">⛔ لا يمكنك تقديم عطاء على مشروعك الخاص</p>
                </div>
                
                <?php elseif ($has_bid): ?>
                <div class="info-card text-center bg-green-50 border-green-200">
                    <p class="text-green-700 font-bold">✅ لقد قدمت عطاء بالفعل على هذا المشروع</p>
                    <a href="<?= SITE_URL ?>/my_bids.php" class="text-blue-600 font-bold text-sm mt-2 inline-block">عرض عطاءاتي</a>
                </div>
                
                <?php else: ?>
                
                <div class="bid-form">
                    <h3 class="text-xl font-bold text-gray-800 mb-4">💰 تقديم عطاء</h3>
                    
                    <?php if ($bid_error): ?>
                    <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl mb-4 text-sm">
                        <i class="fas fa-exclamation-circle ml-2"></i> <?= $bid_error ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($bid_success): ?>
                    <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-xl mb-4 text-sm">
                        <i class="fas fa-check-circle ml-2"></i> <?= $bid_success ?>
                    </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="" enctype="multipart/form-data">
                        <input type="hidden" name="submit_bid" value="1">
                        
                        <div class="form-group">
                            <label>💰 المبلغ المقترح (ر.س) <span class="text-red-500">*</span></label>
                            <input type="number" name="amount" class="form-control" placeholder="أدخل المبلغ" min="1" step="0.01" required>
                        </div>
                        
                        <div class="form-group">
                            <label>⏱️ المدة المتوقعة (بالأيام) <span class="text-red-500">*</span></label>
                            <input type="number" name="duration_days" class="form-control" placeholder="عدد الأيام المتوقعة" min="1" required>
                        </div>
                        
                        <div class="form-group">
                            <label>📝 الاقتراح التفصيلي <span class="text-red-500">*</span></label>
                            <textarea name="proposal" class="form-control" placeholder="اكتب خطة عملك، المواد المستخدمة، فريق العمل..." required></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label>📎 ملفات إضافية (اختياري)</label>
                            <div class="file-upload-area" onclick="document.getElementById('fileInput').click()">
                                <span class="icon"><i class="fas fa-cloud-upload-alt"></i></span>
                                <div class="text">اضغط لرفع الملفات</div>
                                <div class="sub-text">PDF, CAD, صور, ZIP (حد أقصى 10MB لكل ملف)</div>
                                <input type="file" name="attachments[]" id="fileInput" class="form-control" style="display: none;" multiple accept=".pdf,.jpg,.jpeg,.png,.gif,.webp,.zip,.rar,.dwg,.dxf" onchange="updateFileList(this)">
                            </div>
                            <div class="file-list" id="fileList"></div>
                            <div class="text-xs text-gray-400 mt-1">يمكنك رفع مستندات PDF, ملفات CAD (DWG/DXF), صور, أو ملفات مضغوطة</div>
                        </div>
                        
                        <button type="submit" class="btn-submit-bid">
                            <i class="fas fa-paper-plane"></i> تقديم العطاء
                        </button>
                    </form>
                </div>
                
                <?php endif; ?>
                
            <?php elseif ($project['status'] === 'in_progress'): ?>
            <div class="info-card text-center bg-amber-50 border-amber-200">
                <p class="text-amber-700 font-bold">🟡 هذا المشروع قيد التنفيذ</p>
                <p class="text-sm text-gray-500 mt-1">لا يمكن تقديم عطاءات على مشاريع قيد التنفيذ</p>
            </div>
            
            <?php elseif ($project['status'] === 'completed'): ?>
            <div class="info-card text-center bg-blue-50 border-blue-200">
                <p class="text-blue-700 font-bold">🔵 هذا المشروع مكتمل</p>
                <p class="text-sm text-gray-500 mt-1">لا يمكن تقديم عطاءات على مشاريع مكتملة</p>
            </div>
            
            <?php elseif ($project['status'] === 'cancelled'): ?>
            <div class="info-card text-center bg-red-50 border-red-200">
                <p class="text-red-700 font-bold">🔴 هذا المشروع ملغي</p>
                <p class="text-sm text-gray-500 mt-1">لا يمكن تقديم عطاءات على مشاريع ملغية</p>
            </div>
            <?php endif; ?>
            
        </div>
        
    </div>
    
    <!-- ========================================== -->
    <!-- عرض جميع العطاءات (لصاحب العمل فقط) -->
    <!-- ========================================== -->
    <?php if ($is_owner): ?>
        <?php if (!empty($bids)): ?>
        <div class="bids-section">
            <div class="section-header">
                <div>
                    <h2>📋 عطاءات المشروع</h2>
                    <div class="stats">
                        <span>📊 <?= $all_bids_count ?> عطاء</span>
                        <?php if ($avg_bid_amount > 0): ?>
                        <span>📈 متوسط: <?= number_format($avg_bid_amount) ?> ر.س</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="sort-buttons">
                    <a href="?id=<?= $project_id ?>&sort=highest" class="sort-btn highest <?= $sort === 'highest' ? 'active' : '' ?>">
                        ⬆️ الأعلى سعراً
                    </a>
                    <a href="?id=<?= $project_id ?>&sort=lowest" class="sort-btn lowest <?= $sort === 'lowest' ? 'active' : '' ?>">
                        ⬇️ الأقل سعراً
                    </a>
                </div>
            </div>
            
            <?php foreach ($bids as $bid): 
                $bid_attachments = getBidAttachments($bid['id']);
                $workspace_id = getWorkspaceId($project_id, $bid['id']);
                $bid_status = $bid['status'];
            ?>
            <div class="bid-card <?= $bid_status ?>">
                <div class="bid-row">
                    <div class="contractor-info">
                        <div class="contractor-avatar">
                            <?php if (!empty($bid['contractor_avatar']) && $bid['contractor_avatar'] !== 'default-avatar.png'): ?>
                            <img src="<?= SITE_URL ?>/<?= $bid['contractor_avatar'] ?>" alt="<?= clean($bid['contractor_name']) ?>">
                            <?php else: ?>
                            <?= mb_substr($bid['contractor_name'], 0, 1) ?>
                            <?php endif; ?>
                        </div>
                        <div>
                            <div class="contractor-name">
                                <a href="<?= SITE_URL ?>/profile.php?id=<?= $bid['contractor_id'] ?>">
                                    <?= clean($bid['contractor_name']) ?>
                                </a>
                            </div>
                            <div class="text-xs text-gray-500">
                                <?php if ($bid['contractor_rating'] > 0): ?>
                                ⭐ <?= number_format($bid['contractor_rating'], 1) ?> (<?= $bid['contractor_reviews_count'] ?> تقييم)
                                <?php endif; ?>
                                <span class="mr-2"><i class="fas fa-clock"></i> <?= timeAgo($bid['created_at']) ?></span>
                            </div>
                        </div>
                    </div>
                    <div>
                        <span class="bid-amount"><?= number_format($bid['amount']) ?> ر.س</span>
                        <span class="bid-status <?= $bid_status ?>">
                            <?php
                                $bid_status_labels = [
                                    'pending' => '⏳ قيد مراجعة الإدارة',
                                    'pending_employer' => '⏳ في انتظار موافقتك',
                                    'accepted' => '✅ مقبول',
                                    'rejected' => '❌ مرفوض'
                                ];
                                echo $bid_status_labels[$bid_status] ?? $bid_status;
                            ?>
                        </span>
                    </div>
                </div>
                
                <?php if (!empty($bid['proposal'])): ?>
                <div class="bid-proposal">
                    <?= clean(substr($bid['proposal'], 0, 200)) ?><?= strlen($bid['proposal']) > 200 ? '...' : '' ?>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($bid_attachments)): ?>
                <div class="bid-files">
                    <?php foreach ($bid_attachments as $file): ?>
                    <a href="<?= SITE_URL ?>/assets/uploads/bid_files/<?= $file['filename'] ?>" target="_blank" class="file-link">
                        <i class="fas fa-paperclip"></i> <?= substr(clean($file['original_name']), 0, 20) ?>
                    </a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                
                <!-- ===== جميع أزرار الإجراءات ===== -->
                <div class="bid-actions">
                    
<!-- 1. أزرار قبول/رفض (فقط للعطاءات التي وافق عليها الأدمن - pending_employer) -->
<?php if ($project['status'] === 'open' && $bid_status === 'pending_employer'): ?>
    <form method="POST" action="" style="display:inline;" onsubmit="return confirm('هل أنت متأكد من قبول هذا العطاء؟')">
        <input type="hidden" name="bid_id" value="<?= $bid['id'] ?>">
        <input type="hidden" name="action" value="accept">
        <button type="submit" class="btn-sm btn-accept">
            <i class="fas fa-check"></i> قبول نهائي
        </button>
    </form>
    <form method="POST" action="" style="display:inline;" onsubmit="return confirm('هل أنت متأكد من رفض هذا العطاء؟')">
        <input type="hidden" name="bid_id" value="<?= $bid['id'] ?>">
        <input type="hidden" name="action" value="reject">
        <button type="submit" class="btn-sm btn-reject">
            <i class="fas fa-times"></i> رفض
        </button>
    </form>
<?php endif; ?>
                    
                    <!-- 2. محادثة (دائماً) -->
                    <a href="<?= SITE_URL ?>/chat.php?user_id=<?= $bid['contractor_id'] ?>&bid_id=<?= $bid['id'] ?>" class="btn-sm btn-chat">
                        <i class="fas fa-comments"></i> محادثة
                    </a>
                    
                    <!-- 3. رفع ملفات (للعطاءات المقبولة فقط) -->
                    <?php if ($bid_status === 'accepted'): ?>
                        <a href="<?= SITE_URL ?>/upload_project_files.php?bid_id=<?= $bid['id'] ?>&project_id=<?= $project_id ?>" class="btn-sm btn-upload">
                            <i class="fas fa-upload"></i> رفع ملفات
                        </a>
                    <?php endif; ?>
                    
                    <!-- 4. غرفة العمل (إذا كانت موجودة) -->
                    <?php if ($workspace_id): ?>
                        <a href="<?= SITE_URL ?>/project_workspace.php?id=<?= $workspace_id ?>" class="btn-sm btn-workspace">
                            <i class="fas fa-door-open"></i> غرفة العمل
                        </a>
                    <?php endif; ?>
                    
                    <!-- 5. اكتمال المشروع (للعطاءات المقبولة والمشروع قيد التنفيذ) -->
                    <?php if ($bid_status === 'accepted' && $project['status'] === 'in_progress'): ?>
                        <form method="POST" action="" style="display:inline;" onsubmit="return confirm('هل أنت متأكد من اكتمال المشروع؟')">
                            <input type="hidden" name="bid_id" value="<?= $bid['id'] ?>">
                            <input type="hidden" name="complete_project" value="1">
                            <button type="submit" class="btn-sm btn-complete">
                                <i class="fas fa-check-double"></i> اكتمال
                            </button>
                        </form>
                    <?php endif; ?>
                    
                    <!-- 6. تقييم المقاول (للمشاريع المكتملة) -->
                    <?php if ($bid_status === 'accepted' && $project['status'] === 'completed'): ?>
                        <a href="<?= SITE_URL ?>/rate_contractor.php?bid_id=<?= $bid['id'] ?>&project_id=<?= $project_id ?>" class="btn-sm btn-rate">
                            <i class="fas fa-star"></i> تقييم
                        </a>
                    <?php endif; ?>
                    
                    <!-- 7. عرض العطاء (تفاصيل) -->
                    <a href="<?= SITE_URL ?>/project_detail.php?id=<?= $project_id ?>" class="btn-sm btn-details">
                        <i class="fas fa-eye"></i> تفاصيل
                    </a>
                    
                    <!-- 8. حالة العطاء (مقبول/مرفوض) -->
                    <?php if ($bid_status === 'accepted'): ?>
                        <span class="bid-status-badge-accepted">
                            <i class="fas fa-check-circle"></i> تم القبول
                        </span>
                    <?php elseif ($bid_status === 'rejected'): ?>
                        <span class="bid-status-badge-rejected">
                            <i class="fas fa-times-circle"></i> تم الرفض
                        </span>
                    <?php elseif ($bid_status === 'pending_employer'): ?>
                        <span class="text-blue-600 text-xs font-bold bg-blue-50 px-3 py-1 rounded-full flex items-center gap-1">
                            <i class="fas fa-clock"></i> في انتظار قرارك
                        </span>
                    <?php elseif ($bid_status === 'pending'): ?>
                        <span class="text-amber-600 text-xs font-bold bg-amber-50 px-3 py-1 rounded-full flex items-center gap-1">
                            <i class="fas fa-spinner"></i> قيد مراجعة الإدارة
                        </span>
                    <?php endif; ?>
                    
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="bids-section">
            <div class="section-header">
                <h2>📋 عطاءات المشروع</h2>
            </div>
            <div class="no-bids">
                <span class="icon">📭</span>
                <p>لا توجد عطاءات على هذا المشروع حتى الآن</p>
            </div>
        </div>
        <?php endif; ?>
    <?php endif; ?>
    
</div>

<!-- نموذج عرض الصور -->
<div id="imageModal" class="fixed inset-0 bg-black/90 z-50 hidden flex items-center justify-center" onclick="this.classList.add('hidden')">
    <img id="modalImage" src="" alt="صورة المشروع" class="max-w-[90vw] max-h-[90vh] object-contain rounded-lg">
    <button class="absolute top-4 left-4 text-white text-3xl hover:text-gray-300" onclick="document.getElementById('imageModal').classList.add('hidden')">
        <i class="fas fa-times"></i>
    </button>
</div>

<script>
function openImage(src) {
    const modal = document.getElementById('imageModal');
    const img = document.getElementById('modalImage');
    img.src = src;
    modal.classList.remove('hidden');
}

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