<?php
// ============================================================
// PROJECT WORKSPACE - FIXED VERSION
// ============================================================

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');
ini_set('default_charset', 'UTF-8');
mb_internal_encoding('UTF-8');

// Development only — disable in production
ini_set('display_errors', '1');
error_reporting(E_ALL);

require_once 'config.php';

// ── DATABASE ENCODING ──
if ($pdo) {
    $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("SET CHARACTER SET utf8mb4");
    $pdo->exec("SET character_set_connection = utf8mb4");
    $pdo->exec("SET collation_connection = utf8mb4_unicode_ci");
}

// ============================================================
// 1. AUTHENTICATION & AUTHORIZATION
// ============================================================
if (!isLoggedIn()) {
    redirect('login.php');
}

$userId      = (int) ($_SESSION['user_id']   ?? 0);
$userType    = (string) ($_SESSION['user_type'] ?? '');
$userName    = (string) ($_SESSION['user_name'] ?? 'مستخدم');
$workspaceId = (int) ($_GET['id'] ?? 0);

if ($workspaceId <= 0) {
    redirect('my_bids.php');
}

// ── Fetch workspace with project & users ──
$stmt = $pdo->prepare("
    SELECT w.*,
           p.title  AS project_title,
           p.status AS project_status,
           p.id     AS project_id,
           e.name   AS employer_name,
           c.name   AS contractor_name,
           e.id     AS employer_id,
           c.id     AS contractor_id
    FROM project_workspace w
    JOIN projects p ON w.project_id = p.id
    JOIN users e    ON w.employer_id    = e.id
    JOIN users c    ON w.contractor_id  = c.id
    WHERE w.id = ?
    LIMIT 1
");
$stmt->execute([$workspaceId]);
$workspace = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$workspace) {
    redirect('my_bids.php');
}

// ── Verify membership ──
$isEmployer   = ($userId === (int) $workspace['employer_id']);
$isContractor = ($userId === (int) $workspace['contractor_id']);
if (!$isEmployer && !$isContractor) {
    redirect('my_bids.php');
}

$otherPartyName = $isEmployer ? $workspace['contractor_name'] : $workspace['employer_name'];
$otherPartyId   = $isEmployer ? (int) $workspace['contractor_id'] : (int) $workspace['employer_id'];

// ============================================================
// 2. HELPER FUNCTIONS
// ============================================================

function sanitize(string $input): string {
    return htmlspecialchars(trim($input), ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function handleFileUpload(
    array $file,
    string $uploadDir,
    string $prefix,
    int $workspaceId,
    array $allowedExtensions,
    int $maxSizeBytes = 10 * 1024 * 1024
): ?array {
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }
    if ($file['size'] > $maxSizeBytes) {
        return null;
    }

    $originalName = $file['name'];
    $extension    = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

    if (!in_array($extension, $allowedExtensions, true)) {
        return null;
    }

    $dirPath = __DIR__ . '/assets/uploads/' . $uploadDir . '/';
    if (!is_dir($dirPath)) {
        mkdir($dirPath, 0755, true);
    }

    $newName = sprintf(
        '%s_%d_%d_%s.%s',
        $prefix,
        $workspaceId,
        time(),
        bin2hex(random_bytes(4)),
        $extension
    );

    if (!move_uploaded_file($file['tmp_name'], $dirPath . $newName)) {
        return null;
    }

    return [
        'path'     => 'assets/uploads/' . $uploadDir . '/' . $newName,
        'filename' => $originalName,
    ];
}

function sendNotification(
    PDO $pdo,
    int $recipientId,
    string $title,
    string $message,
    string $link,
    string $type = 'info'
): void {
    $stmt = $pdo->prepare("
        INSERT INTO notifications (user_id, title, message, link, type, created_at)
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([$recipientId, $title, $message, $link, $type]);
}

// ============================================================
// 3. IMPROVED RESPONSE HANDLER - FIXED
// ============================================================

function handleItemResponse(
    PDO $pdo,
    string $table,
    int $workspaceId,
    int $userId,
    int $recordId,
    string $action,
    string $comment,
    array $config,
    array $workspace,
    string $siteUrl
): array {
    $result = ['success' => false, 'message' => 'حدث خطأ غير متوقع'];
    
    $pk = $config['pk'] ?? 'id';
    $creatorField = $config['creator_field'] ?? 'created_by';
    $titleField   = $config['title_field'] ?? 'title';
    $statusField  = $config['status_field'] ?? 'status';
    
    // Fetch the record
    $stmt = $pdo->prepare("SELECT * FROM {$table} WHERE {$pk} = ? AND workspace_id = ?");
    $stmt->execute([$recordId, $workspaceId]);
    $record = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$record) {
        return ['success' => false, 'message' => 'العنصر غير موجود'];
    }
    
    // Prevent self-approval
    if ((int) $record[$creatorField] === $userId) {
        return ['success' => false, 'message' => 'لا يمكنك الموافقة أو رفض طلبك الخاص'];
    }
    
    // Check if already processed
    $currentStatus = $record[$statusField] ?? 'pending';
    if ($currentStatus !== 'pending' && $currentStatus !== 'in_review') {
        return ['success' => false, 'message' => 'تم معالجة هذا الطلب مسبقاً'];
    }
    
    // Define status mapping
    $statusMap = [
        'approve' => ['status' => 'approved', 'notif_type' => 'success', 'notif_icon' => '✅'],
        'reject'  => ['status' => 'rejected', 'notif_type' => 'error', 'notif_icon' => '❌'],
        'answer'  => ['status' => 'answered', 'notif_type' => 'success', 'notif_icon' => '💬'],
        'revise'  => ['status' => 'revision_required', 'notif_type' => 'warning', 'notif_icon' => '🔄'],
    ];
    
    if (!isset($statusMap[$action])) {
        return ['success' => false, 'message' => 'إجراء غير معروف'];
    }
    
    $newStatus = $statusMap[$action]['status'];
    $notifType = $statusMap[$action]['notif_type'];
    $notifIcon = $statusMap[$action]['notif_icon'];
    
    // Start transaction
    $pdo->beginTransaction();
    
    try {
        // Check if responded_by column exists, if not, skip it
        $columns = $pdo->query("SHOW COLUMNS FROM {$table}")->fetchAll(PDO::FETCH_COLUMN);
        $hasRespondedBy = in_array('responded_by', $columns);
        
        // Build the update query dynamically
        $updateFields = "{$statusField} = ?, response_comment = ?, responded_at = NOW(), updated_at = NOW()";
        $params = [$newStatus, $comment];
        
        if ($hasRespondedBy) {
            $updateFields .= ", responded_by = ?";
            $params[] = $userId;
        }
        
        $params[] = $recordId;
        $params[] = $workspaceId;
        
        $stmt = $pdo->prepare("
            UPDATE {$table}
            SET {$updateFields}
            WHERE {$pk} = ? AND workspace_id = ?
        ");
        $stmt->execute($params);
        
        // --- Apply side effects ---
        if ($action === 'approve') {
            applySideEffects($pdo, $table, $record, $workspace);
        }
        
        // --- Send notification ---
        $actionLabels = [
            'approve' => 'قبول',
            'reject' => 'رفض',
            'answer' => 'رد على',
            'revise' => 'طلب مراجعة'
        ];
        
        $notifTitle = $config['notif_title'][$action] ?? "{$notifIcon} تم {$actionLabels[$action]} طلبك";
        $notifBody = ($config['notif_body'][$action] ?? '') . $record[$titleField] . ' في مشروع: ' . $workspace['project_title'];
        if ($comment) {
            $notifBody .= ' | تعليق: ' . $comment;
        }
        
        sendNotification(
            $pdo,
            (int) $record[$creatorField],
            $notifTitle,
            $notifBody,
            $siteUrl . '/project_workspace.php?id=' . $workspaceId,
            $notifType
        );
        
        $pdo->commit();
        $result = ['success' => true, 'message' => 'تمت العملية بنجاح'];
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        $result = ['success' => false, 'message' => 'خطأ في قاعدة البيانات: ' . $e->getMessage()];
    }
    
    return $result;
}

function applySideEffects(PDO $pdo, string $table, array $record, array $workspace): void
{
    switch ($table) {
        case 'workspace_deliveries':
            if (!empty($record['delivery_percentage'])) {
                $stmt = $pdo->prepare("UPDATE projects SET progress = ? WHERE id = ?");
                $stmt->execute([$record['delivery_percentage'], $workspace['project_id']]);
            }
            break;
            
        case 'workspace_reports':
            if (!empty($record['progress_percentage'])) {
                $stmt = $pdo->prepare("UPDATE projects SET progress = ? WHERE id = ?");
                $stmt->execute([$record['progress_percentage'], $workspace['project_id']]);
            }
            break;
            
        case 'workspace_time_extensions':
            if (!empty($record['additional_days'])) {
                $stmt = $pdo->prepare("UPDATE projects SET end_date = DATE_ADD(end_date, INTERVAL ? DAY) WHERE id = ?");
                $stmt->execute([$record['additional_days'], $workspace['project_id']]);
            }
            break;
            
        case 'workspace_change_orders':
            if (!empty($record['additional_cost'])) {
                $stmt = $pdo->prepare("UPDATE projects SET total_price = total_price + ? WHERE id = ?");
                $stmt->execute([$record['additional_cost'], $workspace['project_id']]);
            }
            if (!empty($record['additional_days'])) {
                $stmt = $pdo->prepare("UPDATE projects SET end_date = DATE_ADD(end_date, INTERVAL ? DAY) WHERE id = ?");
                $stmt->execute([$record['additional_days'], $workspace['project_id']]);
            }
            break;
    }
}

// ============================================================
// 4. FILTERS
// ============================================================
$searchQuery = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$filterStatus = isset($_GET['filter_status']) ? sanitize($_GET['filter_status']) : 'all';

// ============================================================
// 5. POST HANDLERS
// ============================================================

// ── 5.1 Chat Message ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    $message = sanitize($_POST['message'] ?? '');
    $fileInfo = null;

    if (!empty($_FILES['message_file'])) {
        $fileInfo = handleFileUpload(
            $_FILES['message_file'],
            'workspace_files',
            'workspace',
            $workspaceId,
            ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'zip', 'rar', 'dwg', 'dxf', 'doc', 'docx', 'xls', 'xlsx']
        );
    }

    if ($message !== '' || $fileInfo) {
        $stmt = $pdo->prepare("
            INSERT INTO workspace_messages
                (workspace_id, sender_id, message, message_type, file_path, file_name, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $workspaceId,
            $userId,
            $message,
            $fileInfo ? 'file' : 'text',
            $fileInfo['path'] ?? null,
            $fileInfo['filename'] ?? null,
        ]);

        $pdo->prepare("UPDATE project_workspace SET updated_at = NOW() WHERE id = ?")
            ->execute([$workspaceId]);

        sendNotification(
            $pdo,
            $otherPartyId,
            '💬 رسالة جديدة في غرفة العمل',
            $userName . ' أرسل رسالة في مشروع: ' . $workspace['project_title'],
            SITE_URL . '/project_workspace.php?id=' . $workspaceId
        );
    }
    redirect('project_workspace.php?id=' . $workspaceId);
}

// ── 5.2 Submit Claim ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_claim'])) {
    $title = sanitize($_POST['claim_title'] ?? '');
    $desc  = sanitize($_POST['claim_description'] ?? '');
    $amount = (float) ($_POST['claim_amount'] ?? 0);

    if ($title !== '' && $desc !== '') {
        $fileInfo = handleFileUpload(
            $_FILES['claim_file'] ?? [],
            'workspace_claims',
            'claim',
            $workspaceId,
            ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx']
        );

        $stmt = $pdo->prepare("
            INSERT INTO workspace_claims
                (workspace_id, claimed_by, title, description, amount, file_path, file_name, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $workspaceId, $userId, $title, $desc, $amount,
            $fileInfo['path'] ?? null,
            $fileInfo['filename'] ?? null,
        ]);

        sendNotification(
            $pdo, $otherPartyId,
            '📋 مطالبة جديدة في المشروع',
            $userName . ' قدم مطالبة: ' . $title . ' في مشروع: ' . $workspace['project_title'],
            SITE_URL . '/project_workspace.php?id=' . $workspaceId,
            'warning'
        );
    }
    redirect('project_workspace.php?id=' . $workspaceId);
}

// ── 5.3 Submit Partial Delivery ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_partial_delivery'])) {
    $title = sanitize($_POST['delivery_title'] ?? '');
    $desc  = sanitize($_POST['delivery_description'] ?? '');
    $percentage = (float) ($_POST['delivery_percentage'] ?? 0);

    if ($title !== '' && $desc !== '' && $percentage > 0) {
        $fileInfo = handleFileUpload(
            $_FILES['delivery_file'] ?? [],
            'workspace_deliveries',
            'delivery',
            $workspaceId,
            ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx', 'zip', 'rar']
        );

        $stmt = $pdo->prepare("
            INSERT INTO workspace_deliveries
                (workspace_id, created_by, title, description, delivery_percentage, file_path, file_name, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $workspaceId, $userId, $title, $desc, $percentage,
            $fileInfo['path'] ?? null,
            $fileInfo['filename'] ?? null,
        ]);

        sendNotification(
            $pdo, $otherPartyId,
            '📦 طلب تسليم جزئي جديد',
            $userName . ' طلب تسليم ' . $percentage . '% من العمل في مشروع: ' . $workspace['project_title'],
            SITE_URL . '/project_workspace.php?id=' . $workspaceId
        );
    }
    redirect('project_workspace.php?id=' . $workspaceId);
}

// ── 5.4 Submit Material Approval ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_material_approval'])) {
    $title = sanitize($_POST['material_title'] ?? '');
    $desc  = sanitize($_POST['material_description'] ?? '');
    $type  = sanitize($_POST['material_type'] ?? '');
    $qty   = (float) ($_POST['material_quantity'] ?? 0);
    $unit  = sanitize($_POST['material_unit'] ?? '');

    if ($title !== '' && $desc !== '') {
        $fileInfo = handleFileUpload(
            $_FILES['material_file'] ?? [],
            'workspace_materials',
            'material',
            $workspaceId,
            ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx', 'xls', 'xlsx']
        );

        $stmt = $pdo->prepare("
            INSERT INTO workspace_materials
                (workspace_id, created_by, title, description, material_type, quantity, unit, file_path, file_name, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $workspaceId, $userId, $title, $desc, $type, $qty, $unit,
            $fileInfo['path'] ?? null,
            $fileInfo['filename'] ?? null,
        ]);

        sendNotification(
            $pdo, $otherPartyId,
            '🧱 طلب موافقة على مواد',
            $userName . ' طلب موافقة على مواد: ' . $title . ' في مشروع: ' . $workspace['project_title'],
            SITE_URL . '/project_workspace.php?id=' . $workspaceId
        );
    }
    redirect('project_workspace.php?id=' . $workspaceId);
}

// ── 5.5 Submit Clarification ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_clarification'])) {
    $title    = sanitize($_POST['clarification_title'] ?? '');
    $desc     = sanitize($_POST['clarification_description'] ?? '');
    $priority = sanitize($_POST['clarification_priority'] ?? 'medium');

    if ($title !== '' && $desc !== '') {
        $fileInfo = handleFileUpload(
            $_FILES['clarification_file'] ?? [],
            'workspace_clarifications',
            'clarification',
            $workspaceId,
            ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx', 'dwg', 'dxf']
        );

        $stmt = $pdo->prepare("
            INSERT INTO workspace_clarifications
                (workspace_id, created_by, title, description, priority, file_path, file_name, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $workspaceId, $userId, $title, $desc, $priority,
            $fileInfo['path'] ?? null,
            $fileInfo['filename'] ?? null,
        ]);

        sendNotification(
            $pdo, $otherPartyId,
            '❓ طلب توضيح جديد',
            $userName . ' طلب توضيح: ' . $title . ' في مشروع: ' . $workspace['project_title'],
            SITE_URL . '/project_workspace.php?id=' . $workspaceId
        );
    }
    redirect('project_workspace.php?id=' . $workspaceId);
}

// ── 5.6 Submit Progress Report ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_progress_report'])) {
    $title      = sanitize($_POST['report_title'] ?? '');
    $desc       = sanitize($_POST['report_description'] ?? '');
    $progress   = (float) ($_POST['report_progress'] ?? 0);
    $workDone   = sanitize($_POST['report_work_done'] ?? '');
    $workPlanned= sanitize($_POST['report_work_planned'] ?? '');
    $challenges = sanitize($_POST['report_challenges'] ?? '');

    if ($title !== '' && $desc !== '') {
        $fileInfo = handleFileUpload(
            $_FILES['report_file'] ?? [],
            'workspace_reports',
            'report',
            $workspaceId,
            ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx']
        );

        $stmt = $pdo->prepare("
            INSERT INTO workspace_reports
                (workspace_id, created_by, title, description, progress_percentage, work_done, work_planned, challenges, file_path, file_name, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $workspaceId, $userId, $title, $desc, $progress, $workDone, $workPlanned, $challenges,
            $fileInfo['path'] ?? null,
            $fileInfo['filename'] ?? null,
        ]);

        sendNotification(
            $pdo, $otherPartyId,
            '📊 تقرير مرحلي جديد',
            $userName . ' رفع تقرير مرحلي: ' . $title . ' في مشروع: ' . $workspace['project_title'],
            SITE_URL . '/project_workspace.php?id=' . $workspaceId
        );
    }
    redirect('project_workspace.php?id=' . $workspaceId);
}

// ── 5.7 Submit Time Extension ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_time_extension'])) {
    $title       = sanitize($_POST['extension_title'] ?? '');
    $desc        = sanitize($_POST['extension_description'] ?? '');
    $days        = (int) ($_POST['extension_days'] ?? 0);
    $reason      = sanitize($_POST['extension_reason'] ?? '');

    if ($title !== '' && $desc !== '' && $days > 0) {
        $fileInfo = handleFileUpload(
            $_FILES['extension_file'] ?? [],
            'workspace_extensions',
            'extension',
            $workspaceId,
            ['pdf', 'doc', 'docx']
        );

        $stmt = $pdo->prepare("
            INSERT INTO workspace_time_extensions
                (workspace_id, created_by, title, description, additional_days, reason, file_path, file_name, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $workspaceId, $userId, $title, $desc, $days, $reason,
            $fileInfo['path'] ?? null,
            $fileInfo['filename'] ?? null,
        ]);

        sendNotification(
            $pdo, $otherPartyId,
            '⏰ طلب تمديد الوقت',
            $userName . ' طلب تمديد ' . $days . ' يوم في مشروع: ' . $workspace['project_title'],
            SITE_URL . '/project_workspace.php?id=' . $workspaceId,
            'warning'
        );
    }
    redirect('project_workspace.php?id=' . $workspaceId);
}

// ── 5.8 Submit Task ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_task'])) {
    $title      = sanitize($_POST['task_title'] ?? '');
    $desc       = sanitize($_POST['task_description'] ?? '');
    $assignedTo = (int) ($_POST['task_assigned_to'] ?? 0);
    $dueDate    = sanitize($_POST['task_due_date'] ?? '');
    $priority   = sanitize($_POST['task_priority'] ?? 'medium');

    if ($title !== '' && $desc !== '' && $assignedTo > 0) {
        $stmt = $pdo->prepare("
            INSERT INTO workspace_tasks
                (workspace_id, created_by, assigned_to, title, description, due_date, priority, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
        ");
        $stmt->execute([$workspaceId, $userId, $assignedTo, $title, $desc, $dueDate, $priority]);

        sendNotification(
            $pdo, $assignedTo,
            '📋 مهمة جديدة',
            'تم تعيين مهمة جديدة لك في مشروع: ' . $workspace['project_title'] . ' - ' . $title,
            SITE_URL . '/project_workspace.php?id=' . $workspaceId
        );
    }
    redirect('project_workspace.php?id=' . $workspaceId);
}

// ── 5.9 Update Task Status ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_task_status'])) {
    $taskId = (int) ($_POST['task_id'] ?? 0);
    $status = sanitize($_POST['task_status'] ?? '');
    $pdo->prepare("UPDATE workspace_tasks SET status = ?, updated_at = NOW() WHERE id = ? AND workspace_id = ?")
        ->execute([$status, $taskId, $workspaceId]);
    redirect('project_workspace.php?id=' . $workspaceId);
}

// ── 5.10 Submit Milestone ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_milestone'])) {
    $title    = sanitize($_POST['milestone_title'] ?? '');
    $desc     = sanitize($_POST['milestone_description'] ?? '');
    $dueDate  = sanitize($_POST['milestone_due_date'] ?? '');
    $amount   = (float) ($_POST['milestone_amount'] ?? 0);

    if ($title !== '' && $dueDate !== '') {
        $stmt = $pdo->prepare("
            INSERT INTO workspace_milestones
                (workspace_id, created_by, title, description, due_date, amount, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())
        ");
        $stmt->execute([$workspaceId, $userId, $title, $desc, $dueDate, $amount]);

        sendNotification(
            $pdo, $otherPartyId,
            '🎯 معلم رئيسي جديد',
            'تم تحديد معلم رئيسي جديد في مشروع: ' . $workspace['project_title'] . ' - ' . $title,
            SITE_URL . '/project_workspace.php?id=' . $workspaceId
        );
    }
    redirect('project_workspace.php?id=' . $workspaceId);
}

// ── 5.11 Update Milestone Status ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_milestone_status'])) {
    $milestoneId = (int) ($_POST['milestone_id'] ?? 0);
    $status      = sanitize($_POST['milestone_status'] ?? '');
    $pdo->prepare("UPDATE workspace_milestones SET status = ?, updated_at = NOW() WHERE id = ? AND workspace_id = ?")
        ->execute([$status, $milestoneId, $workspaceId]);
    redirect('project_workspace.php?id=' . $workspaceId);
}

// ── 5.12 Submit Change Order ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_change_order'])) {
    $title          = sanitize($_POST['change_title'] ?? '');
    $desc           = sanitize($_POST['change_description'] ?? '');
    $additionalCost = (float) ($_POST['additional_cost'] ?? 0);
    $additionalDays = (int) ($_POST['additional_days'] ?? 0);

    if ($title !== '' && $desc !== '') {
        $fileInfo = handleFileUpload(
            $_FILES['change_file'] ?? [],
            'workspace_change_orders',
            'change',
            $workspaceId,
            ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx', 'dwg', 'dxf']
        );

        $stmt = $pdo->prepare("
            INSERT INTO workspace_change_orders
                (workspace_id, created_by, title, description, additional_cost, additional_days, file_path, file_name, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $workspaceId, $userId, $title, $desc, $additionalCost, $additionalDays,
            $fileInfo['path'] ?? null,
            $fileInfo['filename'] ?? null,
        ]);

        sendNotification(
            $pdo, $otherPartyId,
            '📝 أمر تغييري جديد',
            $userName . ' طلب تغيير: ' . $title . ' في مشروع: ' . $workspace['project_title'],
            SITE_URL . '/project_workspace.php?id=' . $workspaceId,
            'warning'
        );
    }
    redirect('project_workspace.php?id=' . $workspaceId);
}

// ============================================================
// 5.13 ENHANCED RESPONSE HANDLERS - FIXED
// ============================================================

$responseHandlers = [
    'respond_claim' => [
        'table' => 'workspace_claims',
        'pk' => 'id',
        'creator' => 'claimed_by',
        'title' => 'title',
        'status' => 'status',
        'notif' => [
            'approve' => ['✅ تم قبول مطالبتك', 'تم قبول مطالبتك: '],
            'reject' => ['❌ تم رفض مطالبتك', 'تم رفض مطالبتك: '],
            'revise' => ['🔄 طلب مراجعة مطالبتك', 'طلب مراجعة مطالبتك: ']
        ]
    ],
    'respond_delivery' => [
        'table' => 'workspace_deliveries',
        'pk' => 'id',
        'creator' => 'created_by',
        'title' => 'title',
        'status' => 'status',
        'notif' => [
            'approve' => ['✅ تم قبول تسليمك الجزئي', 'تم قبول تسليمك الجزئي: '],
            'reject' => ['❌ تم رفض تسليمك الجزئي', 'تم رفض تسليمك الجزئي: '],
            'revise' => ['🔄 طلب مراجعة تسليمك', 'طلب مراجعة تسليمك: ']
        ]
    ],
    'respond_material' => [
        'table' => 'workspace_materials',
        'pk' => 'id',
        'creator' => 'created_by',
        'title' => 'title',
        'status' => 'status',
        'notif' => [
            'approve' => ['✅ تمت الموافقة على موادك', 'تمت الموافقة على موادك: '],
            'reject' => ['❌ تم رفض موادك', 'تم رفض موادك: '],
            'revise' => ['🔄 طلب مراجعة موادك', 'طلب مراجعة موادك: ']
        ]
    ],
    'respond_clarification' => [
        'table' => 'workspace_clarifications',
        'pk' => 'id',
        'creator' => 'created_by',
        'title' => 'title',
        'status' => 'status',
        'notif' => [
            'answer' => ['💬 تم الرد على استفسارك', 'تم الرد على استفسارك: '],
            'reject' => ['❌ تم رفض استفسارك', 'تم رفض استفسارك: '],
            'revise' => ['🔄 طلب مراجعة استفسارك', 'طلب مراجعة استفسارك: ']
        ]
    ],
    'respond_report' => [
        'table' => 'workspace_reports',
        'pk' => 'id',
        'creator' => 'created_by',
        'title' => 'title',
        'status' => 'status',
        'notif' => [
            'approve' => ['✅ تم اعتماد تقريرك المرحلي', 'تم اعتماد تقريرك المرحلي: '],
            'reject' => ['❌ تم رفض تقريرك المرحلي', 'تم رفض تقريرك المرحلي: '],
            'revise' => ['🔄 طلب مراجعة تقريرك', 'طلب مراجعة تقريرك: ']
        ]
    ],
    'respond_extension' => [
        'table' => 'workspace_time_extensions',
        'pk' => 'id',
        'creator' => 'created_by',
        'title' => 'title',
        'status' => 'status',
        'notif' => [
            'approve' => ['✅ تم قبول طلب تمديد الوقت', 'تم قبول طلب تمديد الوقت: '],
            'reject' => ['❌ تم رفض طلب تمديد الوقت', 'تم رفض طلب تمديد الوقت: '],
            'revise' => ['🔄 طلب مراجعة تمديد الوقت', 'طلب مراجعة تمديد الوقت: ']
        ]
    ],
    'respond_change' => [
        'table' => 'workspace_change_orders',
        'pk' => 'id',
        'creator' => 'created_by',
        'title' => 'title',
        'status' => 'status',
        'notif' => [
            'approve' => ['✅ تم قبول أمر التغيير', 'تم قبول أمر التغيير: '],
            'reject' => ['❌ تم رفض أمر التغيير', 'تم رفض أمر التغيير: '],
            'revise' => ['🔄 طلب مراجعة أمر التغيير', 'طلب مراجعة أمر التغيير: ']
        ]
    ],
];

// Process response handlers
foreach ($responseHandlers as $postKey => $config) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST[$postKey])) {
        $recordId = (int) ($_POST[str_replace('respond_', '', $postKey) . '_id'] ?? 0);
        $action   = $_POST[str_replace('respond_', '', $postKey) . '_action'] ?? '';
        $comment  = sanitize($_POST['response_comment'] ?? '');
        
        // Validate action
        $validActions = ['approve', 'reject', 'answer', 'revise'];
        if (!in_array($action, $validActions)) {
            $action = 'approve';
        }
        
        // For clarification, if action is 'answer', use it, otherwise default to approve
        if ($postKey === 'respond_clarification' && $action === 'answer') {
            // Keep as answer
        } elseif ($postKey === 'respond_clarification' && !in_array($action, ['answer', 'reject', 'revise'])) {
            $action = 'answer';
        }

        $result = handleItemResponse(
            $pdo,
            $config['table'],
            $workspaceId,
            $userId,
            $recordId,
            $action,
            $comment,
            [
                'pk'            => $config['pk'],
                'creator_field' => $config['creator'],
                'title_field'   => $config['title'],
                'status_field'  => $config['status'],
                'notif_title'   => array_map(fn($v) => $v[0], $config['notif']),
                'notif_body'    => array_map(fn($v) => $v[1], $config['notif']),
            ],
            $workspace,
            SITE_URL
        );
        
        $_SESSION['response_result'] = $result;
        redirect('project_workspace.php?id=' . $workspaceId . '&tab=' . $_GET['tab'] ?? '');
    }
}

// ============================================================
// 6. FETCH DATA
// ============================================================

// Fetch messages
$messages = $pdo->prepare("
    SELECT m.*, u.name AS sender_name, u.user_type AS sender_type
    FROM workspace_messages m
    JOIN users u ON m.sender_id = u.id
    WHERE m.workspace_id = ?
    ORDER BY m.created_at ASC
");
$messages->execute([$workspaceId]);
$messages = $messages->fetchAll(PDO::FETCH_ASSOC);

function fetchFilteredData(
    PDO $pdo,
    string $table,
    int $workspaceId,
    string $searchQuery,
    string $filterStatus,
    array $columns = ['title', 'description'],
    string $orderBy = 'created_at DESC'
): array {
    $params = [$workspaceId];
    $where  = 'workspace_id = ?';

    if ($searchQuery !== '') {
        $searchLike = '%' . $searchQuery . '%';
        $orParts = [];
        foreach ($columns as $col) {
$orParts[] = "{$col} LIKE ? COLLATE utf8mb4_unicode_ci";
            $params[] = $searchLike;
        }
        $where .= ' AND (' . implode(' OR ', $orParts) . ')';
    }

    if ($filterStatus !== 'all') {
        $where .= ' AND status = ?';
        $params[] = $filterStatus;
    }

    $sql = "SELECT * FROM {$table} WHERE {$where} ORDER BY {$orderBy}";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$claims        = fetchFilteredData($pdo, 'workspace_claims',         $workspaceId, $searchQuery, $filterStatus);
$deliveries    = fetchFilteredData($pdo, 'workspace_deliveries',     $workspaceId, $searchQuery, $filterStatus);
$materials     = fetchFilteredData($pdo, 'workspace_materials',      $workspaceId, $searchQuery, $filterStatus);
$clarifications= fetchFilteredData($pdo, 'workspace_clarifications', $workspaceId, $searchQuery, $filterStatus);
$reports       = fetchFilteredData($pdo, 'workspace_reports',        $workspaceId, $searchQuery, $filterStatus);
$extensions    = fetchFilteredData($pdo, 'workspace_time_extensions',$workspaceId, $searchQuery, $filterStatus);
$changeOrders  = fetchFilteredData($pdo, 'workspace_change_orders',  $workspaceId, $searchQuery, $filterStatus);

$tasks = $pdo->prepare("
    SELECT t.*, u1.name AS creator_name, u2.name AS assigned_name
    FROM workspace_tasks t
    JOIN users u1 ON t.created_by = u1.id
    JOIN users u2 ON t.assigned_to = u2.id
    WHERE t.workspace_id = ?
    ORDER BY t.due_date ASC, t.created_at DESC
");
$tasks->execute([$workspaceId]);
$tasks = $tasks->fetchAll(PDO::FETCH_ASSOC);

$milestones = $pdo->prepare("
    SELECT m.*, u.name AS creator_name
    FROM workspace_milestones m
    JOIN users u ON m.created_by = u.id
    WHERE m.workspace_id = ?
    ORDER BY m.due_date ASC
");
$milestones->execute([$workspaceId]);
$milestones = $milestones->fetchAll(PDO::FETCH_ASSOC);

// Stats
$stats = $pdo->prepare("
    SELECT
        (SELECT COUNT(*) FROM workspace_claims          WHERE workspace_id = ? AND status = 'pending') AS pending_claims,
        (SELECT COUNT(*) FROM workspace_deliveries      WHERE workspace_id = ? AND status = 'pending') AS pending_deliveries,
        (SELECT COUNT(*) FROM workspace_materials       WHERE workspace_id = ? AND status = 'pending') AS pending_materials,
        (SELECT COUNT(*) FROM workspace_clarifications  WHERE workspace_id = ? AND status = 'pending') AS pending_clarifications,
        (SELECT COUNT(*) FROM workspace_reports         WHERE workspace_id = ? AND status = 'pending') AS pending_reports,
        (SELECT COUNT(*) FROM workspace_time_extensions WHERE workspace_id = ? AND status = 'pending') AS pending_extensions,
        (SELECT COUNT(*) FROM workspace_tasks           WHERE workspace_id = ? AND status = 'pending') AS pending_tasks,
        (SELECT COUNT(*) FROM workspace_milestones      WHERE workspace_id = ? AND status = 'pending') AS pending_milestones,
        (SELECT COUNT(*) FROM workspace_change_orders   WHERE workspace_id = ? AND status = 'pending') AS pending_changes
");
$stats->execute(array_fill(0, 9, $workspaceId));
$stats = $stats->fetch(PDO::FETCH_ASSOC);

$usersForTasks = [
    ['id' => $otherPartyId, 'name' => $otherPartyName]
];

// ============================================================
// 7. STATUS & UI HELPERS
// ============================================================

function statusBadge(string $status): string {
    $map = [
        'pending'           => ['class' => 'status-pending',     'label' => '⏳ معلق'],
        'approved'          => ['class' => 'status-approved',    'label' => '✅ مقبول'],
        'rejected'          => ['class' => 'status-rejected',    'label' => '❌ مرفوض'],
        'answered'          => ['class' => 'status-answered',    'label' => '💬 تم الرد'],
        'completed'         => ['class' => 'status-completed',   'label' => '✔️ مكتمل'],
        'in_progress'       => ['class' => 'status-in-progress', 'label' => '🔄 قيد التنفيذ'],
        'revision_required' => ['class' => 'status-revision',    'label' => '🔄 مطلوب مراجعة'],
    ];
    $info = $map[$status] ?? ['class' => 'status-pending', 'label' => $status];
    return '<span class="status ' . $info['class'] . '">' . $info['label'] . '</span>';
}

function priorityLabel(string $priority): string {
    return match ($priority) {
        'high'   => '🔴 عاجل',
        'medium' => '🟡 متوسط',
        'low'    => '🟢 عادي',
        default  => $priority,
    };
}

function priorityColor(string $priority): string {
    return match ($priority) {
        'high'   => 'color: #dc2626;',
        'medium' => 'color: #d97706;',
        'low'    => 'color: #16a34a;',
        default  => '',
    };
}

function renderDate(string $date): string {
    return date('Y-m-d H:i', strtotime($date));
}

function renderShortDate(string $date): string {
    return date('Y-m-d', strtotime($date));
}

function isOverdue(string $date, string $status): bool {
    return strtotime($date) < time() && $status !== 'completed';
}

$pageTitle = 'غرفة العمل - ' . sanitize($workspace['project_title']);
include 'includes/header.php';
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $pageTitle ?></title>
<style>
/* ===== CSS RESET & BASE ===== */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html, body { overflow-x: hidden; width: 100%; direction: rtl; background: #f1f5f9; font-family: system-ui, -apple-system, 'Segoe UI', Tahoma, sans-serif; line-height: 1.6; }
img { max-width: 100%; height: auto; }

/* ===== CONTAINER ===== */
.ws-container { max-width: 1400px; margin: 0 auto; padding: 20px; width: 100%; }

/* ===== HEADER ===== */
.ws-header {
    background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 50%, #2563eb 100%);
    border-radius: 16px; padding: 24px 30px; color: #fff; margin-bottom: 24px;
    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
}
.ws-header__row { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 10px; }
.ws-header__title { font-size: 24px; font-weight: 800; margin: 0; word-break: break-word; }
.ws-header__subtitle { color: rgba(255,255,255,0.9); font-size: 14px; margin-top: 8px; line-height: 1.6; display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
.ws-header__badge { background: rgba(255,255,255,0.2); color: #fff; padding: 2px 10px; border-radius: 20px; font-size: 12px; }
.ws-header__actions { display: flex; gap: 10px; flex-wrap: wrap; }
.ws-header__actions a { color: rgba(255,255,255,0.9); text-decoration: none; font-size: 14px; background: rgba(255,255,255,0.1); padding: 6px 14px; border-radius: 8px; transition: all 0.3s; display: inline-flex; align-items: center; gap: 6px; }
.ws-header__actions a:hover { background: rgba(255,255,255,0.2); color: #fff; }

/* ===== STATS ===== */
.stats-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 10px; margin-bottom: 24px; }
.stat-card { background: #f8fafc; border-radius: 12px; padding: 14px; text-align: center; border: 1px solid #e2e8f0; transition: all 0.3s; }
.stat-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
.stat-card .number { font-size: 22px; font-weight: 700; color: #0f172a; }
.stat-card .label { font-size: 11px; color: #64748b; margin-top: 4px; }
.stat-card.pending .number { color: #f59e0b; }

/* ===== TABS ===== */
.tabs-wrapper { background: #fff; border-radius: 12px; margin-bottom: 20px; border: 1px solid #e2e8f0; overflow-x: auto; -webkit-overflow-scrolling: touch; }
.tabs { display: flex; flex-wrap: nowrap; gap: 0; list-style: none; padding: 0; margin: 0; min-width: max-content; }
.tabs li { flex-shrink: 0; }
.tabs a { display: block; padding: 12px 18px; font-size: 14px; font-weight: 600; color: #64748b; text-decoration: none; border-bottom: 3px solid transparent; transition: all 0.3s; white-space: nowrap; }
.tabs a:hover { color: #1e293b; background: #f8fafc; }
.tabs a.active { color: #2563eb; border-bottom-color: #2563eb; background: #eff6ff; }
.tabs .badge { background: #e2e8f0; color: #475569; padding: 2px 8px; border-radius: 12px; font-size: 11px; margin-right: 4px; }
.tabs a.active .badge { background: #dbeafe; color: #2563eb; }

/* ===== TAB CONTENT ===== */
.tab-content { display: none; padding: 20px; background: #fff; border-radius: 12px; border: 1px solid #e2e8f0; margin-bottom: 16px; }
.tab-content.active { display: block; }
.section-title { font-size: 18px; font-weight: 700; color: #0f172a; margin-bottom: 16px; padding-bottom: 12px; border-bottom: 2px solid #f1f5f9; display: flex; align-items: center; gap: 8px; }

/* ===== CARDS ===== */
.item-card { background: #f8fafc; padding: 18px; border-radius: 12px; margin-bottom: 14px; border: 1px solid #e2e8f0; transition: all 0.3s; word-break: break-word; }
.item-card:hover { border-color: #cbd5e1; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
.item-card__header { display: flex; flex-wrap: wrap; align-items: flex-start; justify-content: space-between; gap: 10px; margin-bottom: 8px; }
.item-card__body { flex: 1; min-width: 0; }
.item-card__title { font-weight: 700; color: #0f172a; font-size: 15px; margin-bottom: 6px; }
.item-card__desc { color: #475569; font-size: 14px; margin-top: 6px; white-space: pre-wrap; line-height: 1.7; }
.item-card__meta { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; margin-top: 10px; font-size: 13px; color: #64748b; }
.item-card__meta .amount { font-weight: 700; color: #059669; background: #ecfdf5; padding: 2px 8px; border-radius: 4px; }
.item-card__meta .days { color: #d97706; font-weight: 600; background: #fffbeb; padding: 2px 8px; border-radius: 4px; }
.item-card__file { display: inline-block; margin-top: 8px; font-size: 13px; color: #2563eb; font-weight: 600; word-break: break-all; }
.item-card__file:hover { text-decoration: underline; }
.item-card__actions { display: flex; gap: 10px; margin-top: 12px; flex-wrap: wrap; }

/* ===== STATUSES ===== */
.status { font-size: 12px; padding: 4px 12px; border-radius: 20px; font-weight: 700; display: inline-block; }
.status-pending     { background: #fef3c7; color: #92400e; }
.status-approved    { background: #dcfce7; color: #166534; }
.status-rejected    { background: #fee2e2; color: #991b1b; }
.status-answered    { background: #dbeafe; color: #1e40af; }
.status-completed   { background: #d1fae5; color: #065f46; }
.status-in-progress { background: #fef3c7; color: #92400e; }
.status-revision    { background: #fef3c7; color: #92400e; border: 1px dashed #f59e0b; }

/* ===== RESPONSE COMMENT ===== */
.response-comment { 
    background: #fffbeb; padding: 12px 16px; border-radius: 8px; margin-top: 12px; 
    font-size: 13px; border-right: 4px solid #f59e0b; line-height: 1.6; 
}
.response-comment.approved { border-right-color: #22c55e; background: #f0fdf4; }
.response-comment.rejected { border-right-color: #ef4444; background: #fef2f2; }
.response-comment.answered { border-right-color: #3b82f6; background: #eff6ff; }
.response-comment.revision { border-right-color: #f59e0b; background: #fffbeb; }

/* ===== BUTTONS ===== */
.btn { display: inline-flex; align-items: center; justify-content: center; gap: 6px; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.25s; font-size: 14px; padding: 8px 18px; text-decoration: none; }
.btn:hover { transform: translateY(-1px); }
.btn-approve { background: #22c55e; color: #fff; flex: 1; min-width: 80px; }
.btn-approve:hover { background: #16a34a; }
.btn-reject { background: #ef4444; color: #fff; flex: 1; min-width: 80px; }
.btn-reject:hover { background: #dc2626; }
.btn-revise { background: #f59e0b; color: #fff; flex: 1; min-width: 80px; }
.btn-revise:hover { background: #d97706; }
.btn-answer { background: #3b82f6; color: #fff; flex: 1; min-width: 80px; }
.btn-answer:hover { background: #2563eb; }

.btn-add { background: #f8fafc; border: 2px dashed #cbd5e1; padding: 14px; border-radius: 12px; width: 100%; text-align: center; cursor: pointer; font-weight: 700; color: #64748b; font-size: 14px; margin-top: 10px; transition: all 0.3s; }
.btn-add:hover { background: #f1f5f9; border-color: #3b82f6; color: #3b82f6; }
.btn-sm { font-size: 12px; padding: 5px 14px; border-radius: 6px; border: none; cursor: pointer; font-weight: 600; transition: all 0.3s; }
.btn-sm-success { background: #22c55e; color: #fff; }
.btn-sm-success:hover { background: #16a34a; }
.btn-sm-warning { background: #f59e0b; color: #fff; }
.btn-sm-warning:hover { background: #d97706; }
.btn-sm-info { background: #3b82f6; color: #fff; }
.btn-sm-info:hover { background: #2563eb; }
.btn-submit { background: linear-gradient(135deg, #3b82f6, #1d4ed8); color: #fff; border: none; padding: 12px; border-radius: 10px; font-weight: 700; cursor: pointer; width: 100%; font-size: 15px; margin-top: 10px; transition: all 0.3s; }
.btn-submit:hover { transform: translateY(-2px); box-shadow: 0 10px 30px rgba(59,130,246,0.4); }
.btn-close { background: #f1f5f9; border: none; padding: 10px; border-radius: 10px; cursor: pointer; font-weight: 600; margin-top: 10px; width: 100%; font-size: 14px; color: #64748b; transition: all 0.3s; }
.btn-close:hover { background: #e2e8f0; color: #0f172a; }

/* ===== SEARCH & FILTER ===== */
.filter-bar { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 16px; padding: 14px; background: #f8fafc; border-radius: 12px; border: 1px solid #e2e8f0; }
.filter-bar form { display: flex; flex-wrap: wrap; gap: 10px; width: 100%; align-items: center; }
.filter-bar input[type="text"], .filter-bar select {
    flex: 1; min-width: 150px; padding: 8px 14px; border: 2px solid #e2e8f0; border-radius: 8px;
    font-size: 13px; background: #fff; transition: all 0.3s; color: #1e293b; font-family: inherit;
}
.filter-bar input:focus, .filter-bar select:focus { outline: none; border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,0.1); }
.filter-bar .btn-search { background: #3b82f6; color: #fff; border: none; padding: 8px 20px; border-radius: 8px; font-weight: 600; cursor: pointer; font-size: 13px; white-space: nowrap; transition: all 0.3s; }
.filter-bar .btn-search:hover { background: #1d4ed8; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(59,130,246,0.3); }
.filter-bar .btn-reset { background: #e2e8f0; color: #475569; border: none; padding: 8px 16px; border-radius: 8px; font-weight: 600; cursor: pointer; font-size: 13px; text-decoration: none; display: inline-flex; align-items: center; white-space: nowrap; transition: all 0.3s; }
.filter-bar .btn-reset:hover { background: #cbd5e1; }

/* ===== CHAT ===== */
.chat-box { height: 400px; overflow-y: auto; padding: 16px; background: #f1f5f9; border-radius: 12px; margin-bottom: 16px; scroll-behavior: smooth; }
.chat-msg { margin-bottom: 14px; padding: 12px 16px; border-radius: 16px; max-width: 80%; width: fit-content; position: relative; line-height: 1.5; font-size: 14px; animation: fadeIn 0.3s ease; }
.chat-msg.sent { background: #3b82f6; color: #fff; margin-right: auto; margin-left: 0; border-bottom-right-radius: 4px; }
.chat-msg.received { background: #fff; color: #0f172a; border: 1px solid #e2e8f0; margin-left: auto; margin-right: 0; border-bottom-left-radius: 4px; }
.chat-msg .time { font-size: 11px; opacity: 0.7; display: block; margin-top: 6px; text-align: left; }
.chat-msg .sender { font-weight: 700; font-size: 12px; margin-bottom: 4px; display: block; }
.chat-msg .sender small { font-weight: 400; opacity: 0.7; }
.chat-form { display: flex; gap: 8px; align-items: flex-end; flex-wrap: wrap; }
.chat-form textarea { flex: 1; min-width: 150px; padding: 10px 14px; border: 2px solid #e2e8f0; border-radius: 12px; font-size: 14px; resize: none; min-height: 45px; max-height: 100px; transition: all 0.3s; font-family: inherit; }
.chat-form textarea:focus { outline: none; border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,0.1); }
.chat-form .btn-send { background: linear-gradient(135deg, #3b82f6, #1d4ed8); color: #fff; border: none; padding: 10px 22px; border-radius: 12px; font-weight: 700; cursor: pointer; min-height: 45px; display: flex; align-items: center; gap: 6px; transition: all 0.3s; }
.chat-form .btn-send:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(59,130,246,0.3); }
.chat-form .file-btn { background: #fff; border: 2px solid #e2e8f0; border-radius: 12px; padding: 10px 14px; cursor: pointer; display: flex; align-items: center; gap: 6px; min-height: 45px; color: #64748b; font-weight: 600; font-size: 13px; transition: all 0.3s; }
.chat-form .file-btn:hover { background: #f8fafc; border-color: #94a3b8; }
.chat-empty { text-align: center; color: #94a3b8; padding: 40px 0; }
.chat-empty i { font-size: 2.5rem; display: block; margin-bottom: 12px; opacity: 0.6; }

@keyframes fadeIn { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }

/* ===== MODAL ===== */
.modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 9999; align-items: center; justify-content: center; padding: 20px; backdrop-filter: blur(3px); }
.modal-overlay.open { display: flex; }
.modal-box { background: #fff; border-radius: 16px; padding: 28px; width: 100%; max-width: 520px; max-height: 90vh; overflow-y: auto; position: relative; margin: auto; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); }
.modal-box h2 { font-size: 20px; font-weight: 700; color: #0f172a; margin-bottom: 20px; text-align: center; }
.form-group { margin-bottom: 16px; }
.form-group label { display: block; font-weight: 600; font-size: 13px; color: #1e293b; margin-bottom: 6px; }
.form-group input, .form-group textarea, .form-group select {
    width: 100%; padding: 10px 14px; border: 2px solid #e2e8f0; border-radius: 10px; font-size: 14px;
    transition: all 0.3s; font-family: inherit; background: #f8fafc; color: #0f172a;
}
.form-group input:focus, .form-group textarea:focus, .form-group select:focus { outline: none; border-color: #3b82f6; background: #fff; box-shadow: 0 0 0 3px rgba(59,130,246,0.1); }
.form-group textarea { resize: vertical; min-height: 80px; }
.form-row { display: flex; gap: 12px; }
.form-row > .form-group { flex: 1; }
.form-group .req { color: #ef4444; }

/* ===== SCROLLBAR ===== */
.chat-box::-webkit-scrollbar, .modal-box::-webkit-scrollbar { width: 6px; }
.chat-box::-webkit-scrollbar-track, .modal-box::-webkit-scrollbar-track { background: #f1f1f1; border-radius: 8px; }
.chat-box::-webkit-scrollbar-thumb, .modal-box::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 8px; }
.chat-box::-webkit-scrollbar-thumb:hover, .modal-box::-webkit-scrollbar-thumb:hover { background: #94a3b8; }

/* ===== TOAST ===== */
.toast-container { position: fixed; top: 20px; left: 50%; transform: translateX(-50%); z-index: 10000; width: 100%; max-width: 500px; padding: 0 20px; pointer-events: none; }
.toast { background: #fff; border-radius: 12px; padding: 16px 20px; margin-bottom: 10px; box-shadow: 0 10px 40px rgba(0,0,0,0.15); display: flex; align-items: center; gap: 12px; pointer-events: auto; animation: slideDown 0.3s ease; border-right: 4px solid #64748b; }
.toast.success { border-right-color: #22c55e; }
.toast.error { border-right-color: #ef4444; }
.toast.warning { border-right-color: #f59e0b; }
.toast .icon { font-size: 24px; flex-shrink: 0; }
.toast .msg { font-size: 14px; color: #0f172a; flex: 1; }
.toast .close { background: none; border: none; font-size: 18px; color: #94a3b8; cursor: pointer; padding: 4px; flex-shrink: 0; }
.toast .close:hover { color: #475569; }
@keyframes slideDown { from { opacity: 0; transform: translateY(-20px); } to { opacity: 1; transform: translateY(0); } }

/* ===== RESPONSIVE ===== */
@media (max-width: 768px) {
    .ws-container { padding: 12px; }
    .ws-header { padding: 18px; text-align: center; }
    .ws-header__title { font-size: 18px; }
    .ws-header__subtitle { font-size: 12px; justify-content: center; }
    .ws-header__row { flex-direction: column; }
    .ws-header__actions { justify-content: center; width: 100%; }
    .stats-row { grid-template-columns: repeat(3, 1fr); gap: 8px; }
    .stat-card { padding: 10px; }
    .stat-card .number { font-size: 16px; }
    .stat-card .label { font-size: 10px; }
    .tabs a { padding: 10px 14px; font-size: 12px; }
    .filter-bar form { flex-direction: column; align-items: stretch; }
    .filter-bar input, .filter-bar select, .filter-bar .btn-search, .filter-bar .btn-reset { width: 100%; min-width: 100%; justify-content: center; text-align: center; }
    .chat-box { height: 300px; padding: 10px; }
    .chat-form { flex-direction: column; align-items: stretch; }
    .chat-form textarea, .chat-form .btn-send, .chat-form .file-btn { width: 100%; justify-content: center; }
    .item-card { padding: 14px; }
    .item-card__actions { flex-direction: column; }
    .item-card__actions .btn-approve, .item-card__actions .btn-reject, 
    .item-card__actions .btn-revise, .item-card__actions .btn-answer { width: 100%; }
    .modal-box { padding: 20px; width: 95%; }
    .form-row { flex-direction: column; gap: 0; }
    .tab-content { padding: 14px; }
    .chat-msg { max-width: 90%; }
}
@media (max-width: 480px) {
    .stats-row { grid-template-columns: repeat(2, 1fr); }
    .ws-header__title { font-size: 16px; }
}
</style>
</head>
<body>
<div class="ws-container">

<!-- ===== TOAST CONTAINER ===== -->
<div class="toast-container" id="toastContainer"></div>

<!-- ===== HEADER ===== -->
<header class="ws-header">
    <div class="ws-header__row">
        <div style="flex:1; text-align:right;">
            <h1 class="ws-header__title">🏗️ <?= sanitize($workspace['project_title']) ?></h1>
            <div class="ws-header__subtitle">
                <span>مع: <?= sanitize($otherPartyName) ?></span>
                <span>|</span>
                <span>الحالة:
                    <span class="ws-header__badge">
                        <?= $workspace['status'] === 'active' ? '🟢 نشط' : ($workspace['status'] === 'completed' ? '🔵 مكتمل' : '🟡 معلق') ?>
                    </span>
                </span>
                <span>|</span>
                <span>حالة المشروع: <?= sanitize($workspace['project_status']) ?></span>
            </div>
        </div>
        <div class="ws-header__actions">
            <a href="<?= SITE_URL ?>/my_bids.php"><i class="fas fa-arrow-right"></i> العودة للعطاءات</a>
        </div>
    </div>
</header>

<!-- ===== STATS ===== -->
<div class="stats-row">
    <?php
    $statItems = [
        ['pending_claims',        '💰 مطالبات معلقة'],
        ['pending_deliveries',    '📦 تسليم معلق'],
        ['pending_materials',     '🧱 مواد معلقة'],
        ['pending_clarifications','❓ توضيح معلق'],
        ['pending_reports',       '📊 تقارير معلقة'],
        ['pending_extensions',    '⏰ تمديد معلق'],
        ['pending_tasks',         '📋 مهام معلقة'],
        ['pending_milestones',    '🎯 معالم معلقة'],
        ['pending_changes',       '📝 تغييرات معلقة'],
    ];
    foreach ($statItems as [$key, $label]): ?>
        <div class="stat-card pending">
            <div class="number"><?= $stats[$key] ?? 0 ?></div>
            <div class="label"><?= $label ?></div>
        </div>
    <?php endforeach; ?>
</div>

<!-- ===== TABS ===== -->
<div class="tabs-wrapper">
    <ul class="tabs" id="workspaceTabs">
        <?php
        $tabs = [
            ['chat',           '💬 محادثة',      null],
            ['claims',         '💰 مطالبات',     count($claims)],
            ['deliveries',     '📦 تسليم جزئي',  count($deliveries)],
            ['materials',      '🧱 مواد',        count($materials)],
            ['clarifications', '❓ توضيح',       count($clarifications)],
            ['reports',        '📊 تقارير',      count($reports)],
            ['extensions',     '⏰ تمديد',       count($extensions)],
            ['tasks',          '📋 مهام',        count($tasks)],
            ['milestones',     '🎯 معالم',       count($milestones)],
            ['changes',        '📝 تغييرات',     count($changeOrders)],
        ];
        foreach ($tabs as [$id, $label, $count]): ?>
            <li>
                <a href="#" data-tab="<?= $id ?>" class="<?= $id === 'chat' ? 'active' : '' ?>">
                    <?= $label ?>
                    <?php if ($count !== null): ?>
                        <span class="badge"><?= $count ?></span>
                    <?php endif; ?>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
</div>

<!-- ===== FILTER BAR ===== -->
<div class="filter-bar">
    <form method="GET" action="">
        <input type="hidden" name="id" value="<?= $workspaceId ?>">
        <input type="text" name="search" placeholder="🔍 بحث في جميع الأقسام..." value="<?= sanitize($searchQuery) ?>">
        <select name="filter_status">
            <option value="all" <?= $filterStatus === 'all' ? 'selected' : '' ?>>🔄 الكل</option>
            <option value="pending" <?= $filterStatus === 'pending' ? 'selected' : '' ?>>⏳ معلق</option>
            <option value="approved" <?= $filterStatus === 'approved' ? 'selected' : '' ?>>✅ مقبول</option>
            <option value="rejected" <?= $filterStatus === 'rejected' ? 'selected' : '' ?>>❌ مرفوض</option>
            <option value="answered" <?= $filterStatus === 'answered' ? 'selected' : '' ?>>💬 تم الرد</option>
            <option value="completed" <?= $filterStatus === 'completed' ? 'selected' : '' ?>>✔️ مكتمل</option>
            <option value="revision_required" <?= $filterStatus === 'revision_required' ? 'selected' : '' ?>>🔄 مطلوب مراجعة</option>
        </select>
        <button type="submit" class="btn-search">بحث</button>
        <a href="?id=<?= $workspaceId ?>" class="btn-reset">إعادة ضبط</a>
    </form>
</div>

<!-- ===== TAB: CHAT ===== -->
<div id="tab-chat" class="tab-content active">
    <div class="section-title">💬 المحادثة</div>
    <div class="chat-box" id="chatMessages">
        <?php if (empty($messages)): ?>
            <div class="chat-empty">
                <i class="fas fa-comment-dots"></i>
                <p>لا توجد رسائل بعد</p>
                <p style="font-size:13px; margin-top:4px;">ابدأ المحادثة الآن</p>
            </div>
        <?php else: foreach ($messages as $msg): ?>
            <div class="chat-msg <?= $msg['sender_id'] == $userId ? 'sent' : 'received' ?>">
                <span class="sender">
                    <?= sanitize($msg['sender_name']) ?>
                    <small>(<?= $msg['sender_type'] === 'employer' ? 'صاحب عمل' : 'مقاول' ?>)</small>
                </span>
                <?php if ($msg['message']): ?>
                    <?= nl2br(sanitize($msg['message'])) ?>
                <?php endif; ?>
                <?php if ($msg['file_path']): ?>
                    <div style="margin-top:6px;">
                        <a href="<?= SITE_URL ?>/<?= $msg['file_path'] ?>" target="_blank" style="color:inherit; text-decoration:underline; font-weight:600;">
                            <i class="fas fa-paperclip"></i> <?= sanitize($msg['file_name']) ?>
                        </a>
                    </div>
                <?php endif; ?>
                <span class="time"><?= date('h:i A', strtotime($msg['created_at'])) ?></span>
            </div>
        <?php endforeach; endif; ?>
    </div>
    <form method="POST" action="" enctype="multipart/form-data" class="chat-form" id="chatForm">
        <input type="hidden" name="send_message" value="1">
        <textarea name="message" placeholder="اكتب رسالتك..." rows="2"></textarea>
        <label class="file-btn">
            <i class="fas fa-paperclip"></i> مرفق
            <input type="file" name="message_file" style="display:none;" onchange="document.getElementById('chatForm').submit()">
        </label>
        <button type="submit" class="btn-send"><i class="fas fa-paper-plane"></i> إرسال</button>
    </form>
</div>

<?php
// ============================================================
// 8. RENDER SECTIONS WITH ENHANCED RESPONSE OPTIONS - FIXED
// ============================================================

function renderStandardSectionWithResponses(
    array $items,
    string $sectionId,
    string $sectionTitle,
    string $emptyIcon,
    string $emptyText,
    string $addBtnText,
    string $modalId,
    string $postKey,
    string $actionKeyPrefix,
    int $workspaceId,
    int $userId,
    array $workspace,
    string $sectionType,
    callable $extraMeta = null
): void {
    ?>
    <div id="tab-<?= $sectionId ?>" class="tab-content">
        <div class="section-title"><?= $sectionTitle ?></div>
        <?php if (empty($items)): ?>
            <div class="chat-empty">
                <i class="fas <?= $emptyIcon ?>"></i>
                <p><?= $emptyText ?></p>
            </div>
        <?php else: foreach ($items as $item): ?>
            <div class="item-card">
                <div class="item-card__header">
                    <div class="item-card__body">
                        <div class="item-card__title"><?= sanitize($item['title'] ?? '') ?></div>
                        <div class="item-card__desc"><?= nl2br(sanitize($item['description'] ?? '')) ?></div>
                        <div class="item-card__meta">
                            <span>👤 <?= sanitize($item['creator_name'] ?? $item['claimant_name'] ?? '') ?></span>
                            <?php if ($extraMeta) $extraMeta($item); ?>
                            <span>📅 <?= renderDate($item['created_at']) ?></span>
                        </div>
                        <?php if (!empty($item['file_path'])): ?>
                            <a href="<?= SITE_URL ?>/<?= $item['file_path'] ?>" target="_blank" class="item-card__file">
                                <i class="fas fa-paperclip"></i> <?= sanitize($item['file_name']) ?>
                            </a>
                        <?php endif; ?>
                    </div>
                    <div><?= statusBadge($item['status'] ?? 'pending') ?></div>
                </div>

                <?php if (!empty($item['response_comment'])): ?>
                    <div class="response-comment <?= $item['status'] === 'approved' ? 'approved' : ($item['status'] === 'rejected' ? 'rejected' : ($item['status'] === 'answered' ? 'answered' : ($item['status'] === 'revision_required' ? 'revision' : ''))) ?>">
                        <strong>💬 تعليق:</strong> <?= nl2br(sanitize($item['response_comment'])) ?>
                        <?php if (!empty($item['responded_at'])): ?>
                            <span style="font-size:11px; color:#94a3b8; display:block; margin-top:4px;">
                                <?= renderDate($item['responded_at']) ?>
                            </span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php
                $isPending = ($item['status'] ?? 'pending') === 'pending';
                $isCreator = (int) ($item['created_by'] ?? $item['claimed_by'] ?? 0) === $userId;
                $canRespond = !$isCreator && $isPending;
                
                if ($canRespond):
                ?>
                    <form method="POST" action="" style="margin-top:12px; width:100%;" class="response-form">
                        <input type="hidden" name="<?= $postKey ?>" value="1">
                        <input type="hidden" name="<?= $actionKeyPrefix ?>_id" value="<?= $item['id'] ?>">
                        <div style="margin-bottom:8px;">
                            <textarea name="response_comment" style="width:100%; border:1px solid #d1d5db; border-radius:8px; padding:8px 12px; font-size:13px; resize:vertical; min-height:40px; font-family:inherit;" placeholder="أضف تعليقك..." rows="2"></textarea>
                        </div>
                        <div class="item-card__actions">
                            <?php if ($sectionType === 'clarification'): ?>
                                <button type="submit" name="<?= $actionKeyPrefix ?>_action" value="answer" class="btn btn-answer">💬 رد</button>
                                <button type="submit" name="<?= $actionKeyPrefix ?>_action" value="reject" class="btn btn-reject">❌ رفض</button>
                                <button type="submit" name="<?= $actionKeyPrefix ?>_action" value="revise" class="btn btn-revise">🔄 مراجعة</button>
                            <?php else: ?>
                                <button type="submit" name="<?= $actionKeyPrefix ?>_action" value="approve" class="btn btn-approve">✅ قبول</button>
                                <button type="submit" name="<?= $actionKeyPrefix ?>_action" value="reject" class="btn btn-reject">❌ رفض</button>
                                <button type="submit" name="<?= $actionKeyPrefix ?>_action" value="revise" class="btn btn-revise">🔄 مراجعة</button>
                            <?php endif; ?>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        <?php endforeach; endif; ?>
        <button class="btn-add" onclick="openModal('<?= $modalId ?>')"><i class="fas fa-plus-circle"></i> <?= $addBtnText ?></button>
    </div>
    <?php
}

// ── Render all sections ──
renderStandardSectionWithResponses(
    $claims, 'claims', '💰 المطالبات المالية', 'fa-file-contract', 'لا توجد مطالبات مالية',
    'إضافة مطالبة جديدة', 'claimModal', 'respond_claim', 'claim',
    $workspaceId, $userId, $workspace, 'claim',
    fn($item) => !empty($item['amount']) ? '<span class="amount">💰 ' . number_format((float) $item['amount']) . ' ر.س</span>' : ''
);

renderStandardSectionWithResponses(
    $deliveries, 'deliveries', '📦 طلبات التسليم الجزئي', 'fa-box', 'لا توجد طلبات تسليم جزئي',
    'طلب تسليم جزئي', 'deliveryModal', 'respond_delivery', 'delivery',
    $workspaceId, $userId, $workspace, 'delivery',
    fn($item) => '<span class="amount">📊 ' . ($item['delivery_percentage'] ?? 0) . '% من العمل</span>'
);

renderStandardSectionWithResponses(
    $materials, 'materials', '🧱 طلبات الموافقة على المواد', 'fa-cubes', 'لا توجد طلبات موافقة على مواد',
    'طلب موافقة على مواد', 'materialModal', 'respond_material', 'material',
    $workspaceId, $userId, $workspace, 'material',
    function($item) {
        $out = '<span style="font-size:12px; color:#475569;">🏷️ ' . sanitize($item['material_type'] ?? '') . '</span>';
        if (!empty($item['quantity'])) {
            $out .= '<span style="font-size:12px;">📦 ' . $item['quantity'] . ' ' . sanitize($item['unit'] ?? '') . '</span>';
        }
        return $out;
    }
);

renderStandardSectionWithResponses(
    $clarifications, 'clarifications', '❓ طلبات التوضيح', 'fa-question-circle', 'لا توجد طلبات توضيح',
    'طلب توضيح', 'clarificationModal', 'respond_clarification', 'clarification',
    $workspaceId, $userId, $workspace, 'clarification',
    fn($item) => '<span style="font-size:12px; ' . priorityColor($item['priority'] ?? '') . '">' . priorityLabel($item['priority'] ?? '') . '</span>'
);

renderStandardSectionWithResponses(
    $reports, 'reports', '📊 التقارير المرحلية', 'fa-chart-line', 'لا توجد تقارير مرحلية',
    'رفع تقرير مرحلي', 'reportModal', 'respond_report', 'report',
    $workspaceId, $userId, $workspace, 'report',
    fn($item) => '<span class="amount">📊 ' . ($item['progress_percentage'] ?? 0) . '% إنجاز</span>'
);

renderStandardSectionWithResponses(
    $extensions, 'extensions', '⏰ طلبات تمديد الوقت', 'fa-clock', 'لا توجد طلبات تمديد وقت',
    'طلب تمديد وقت', 'extensionModal', 'respond_extension', 'extension',
    $workspaceId, $userId, $workspace, 'extension',
    fn($item) => '<span class="days">📅 +' . ($item['additional_days'] ?? 0) . ' يوم</span>'
);

renderStandardSectionWithResponses(
    $changeOrders, 'changes', '📝 أوامر التغيير', 'fa-edit', 'لا توجد أوامر تغيير',
    'إضافة أمر تغييري', 'changeModal', 'respond_change', 'change',
    $workspaceId, $userId, $workspace, 'change',
    function($item) {
        $out = '';
        if (!empty($item['additional_cost'])) {
            $out .= '<span class="amount">💰 +' . number_format((float) $item['additional_cost']) . ' ر.س</span>';
        }
        if (!empty($item['additional_days'])) {
            $out .= '<span class="days">📅 +' . $item['additional_days'] . ' يوم</span>';
        }
        return $out;
    }
);
?>

<!-- ===== TAB: TASKS ===== -->
<div id="tab-tasks" class="tab-content">
    <div class="section-title">📋 المهام</div>
    <?php if (empty($tasks)): ?>
        <div class="chat-empty"><i class="fas fa-tasks"></i><p>لا توجد مهام</p></div>
    <?php else: foreach ($tasks as $task): ?>
        <div class="item-card">
            <div class="item-card__header">
                <div class="item-card__body">
                    <div class="item-card__title"><?= sanitize($task['title']) ?></div>
                    <div class="item-card__desc"><?= nl2br(sanitize($task['description'])) ?></div>
                    <div class="item-card__meta">
                        <span>👤 منشئ: <?= sanitize($task['creator_name']) ?></span>
                        <span>📌 مكلف: <?= sanitize($task['assigned_name']) ?></span>
                        <span style="<?= priorityColor($task['priority']) ?>"><?= priorityLabel($task['priority']) ?></span>
                        <?php if ($task['due_date']): ?>
                            <span style="<?= isOverdue($task['due_date'], $task['status']) ? 'color:#dc2626;' : 'color:#94a3b8;' ?>">
                                📅 حتى: <?= renderShortDate($task['due_date']) ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
                <div><?= statusBadge($task['status']) ?></div>
            </div>
            <?php if ($task['assigned_to'] == $userId || $task['created_by'] == $userId): ?>
                <form method="POST" action="" style="margin-top:12px;">
                    <input type="hidden" name="update_task_status" value="1">
                    <input type="hidden" name="task_id" value="<?= $task['id'] ?>">
                    <div style="display:flex; gap:8px; flex-wrap:wrap;">
                        <select name="task_status" style="border:1px solid #d1d5db; border-radius:8px; padding:6px 12px; font-size:13px; flex:1; min-width:100px; font-family:inherit;">
                            <option value="pending" <?= $task['status'] === 'pending' ? 'selected' : '' ?>>⏳ معلقة</option>
                            <option value="in_progress" <?= $task['status'] === 'in_progress' ? 'selected' : '' ?>>🔄 قيد التنفيذ</option>
                            <option value="completed" <?= $task['status'] === 'completed' ? 'selected' : '' ?>>✔️ مكتملة</option>
                        </select>
                        <button type="submit" class="btn-sm btn-sm-info">تحديث</button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    <?php endforeach; endif; ?>
    <button class="btn-add" onclick="openModal('taskModal')"><i class="fas fa-plus-circle"></i> إضافة مهمة جديدة</button>
</div>

<!-- ===== TAB: MILESTONES ===== -->
<div id="tab-milestones" class="tab-content">
    <div class="section-title">🎯 المعالم الرئيسية</div>
    <?php if (empty($milestones)): ?>
        <div class="chat-empty"><i class="fas fa-flag"></i><p>لا توجد معالم رئيسية</p></div>
    <?php else: foreach ($milestones as $milestone): ?>
        <div class="item-card">
            <div class="item-card__header">
                <div class="item-card__body">
                    <div class="item-card__title"><?= sanitize($milestone['title']) ?></div>
                    <div class="item-card__desc"><?= nl2br(sanitize($milestone['description'] ?? '')) ?></div>
                    <div class="item-card__meta">
                        <span>👤 منشئ: <?= sanitize($milestone['creator_name']) ?></span>
                        <?php if ($milestone['amount']): ?>
                            <span class="amount">💰 <?= number_format((float) $milestone['amount']) ?> ر.س</span>
                        <?php endif; ?>
                        <span style="<?= isOverdue($milestone['due_date'], $milestone['status']) ? 'color:#dc2626;' : 'color:#94a3b8;' ?>">
                            📅 حتى: <?= renderShortDate($milestone['due_date']) ?>
                        </span>
                    </div>
                </div>
                <div><?= statusBadge($milestone['status']) ?></div>
            </div>
            <?php if ($milestone['created_by'] == $userId || $isEmployer): ?>
                <form method="POST" action="" style="margin-top:12px;">
                    <input type="hidden" name="update_milestone_status" value="1">
                    <input type="hidden" name="milestone_id" value="<?= $milestone['id'] ?>">
                    <div style="display:flex; gap:8px; flex-wrap:wrap;">
                        <select name="milestone_status" style="border:1px solid #d1d5db; border-radius:8px; padding:6px 12px; font-size:13px; flex:1; min-width:100px; font-family:inherit;">
                            <option value="pending" <?= $milestone['status'] === 'pending' ? 'selected' : '' ?>>⏳ معلق</option>
                            <option value="in_progress" <?= $milestone['status'] === 'in_progress' ? 'selected' : '' ?>>🔄 قيد التنفيذ</option>
                            <option value="completed" <?= $milestone['status'] === 'completed' ? 'selected' : '' ?>>✔️ مكتمل</option>
                        </select>
                        <button type="submit" class="btn-sm btn-sm-info">تحديث</button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    <?php endforeach; endif; ?>
    <button class="btn-add" onclick="openModal('milestoneModal')"><i class="fas fa-plus-circle"></i> إضافة معلم رئيسي</button>
</div>

</div>

<!-- ============================================================ -->
<!-- MODALS (All modals remain the same) -->
<!-- ============================================================ -->

<!-- Claim Modal -->
<div id="claimModal" class="modal-overlay">
    <div class="modal-box">
        <h2>💰 إضافة مطالبة جديدة</h2>
        <form method="POST" action="" enctype="multipart/form-data">
            <input type="hidden" name="submit_claim" value="1">
            <div class="form-group">
                <label>عنوان المطالبة <span class="req">*</span></label>
                <input type="text" name="claim_title" required placeholder="مثال: تأخير في توريد المواد">
            </div>
            <div class="form-group">
                <label>تفاصيل المطالبة <span class="req">*</span></label>
                <textarea name="claim_description" required rows="4" placeholder="اكتب تفاصيل المطالبة..."></textarea>
            </div>
            <div class="form-group">
                <label>المبلغ المطلوب (ر.س) (اختياري)</label>
                <input type="number" name="claim_amount" step="0.01" placeholder="0">
            </div>
            <div class="form-group">
                <label>ملف إضافي (اختياري)</label>
                <input type="file" name="claim_file" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
            </div>
            <button type="submit" class="btn-submit">إرسال المطالبة</button>
            <button type="button" class="btn-close" onclick="closeModal('claimModal')">إلغاء</button>
        </form>
    </div>
</div>

<!-- Delivery Modal -->
<div id="deliveryModal" class="modal-overlay">
    <div class="modal-box">
        <h2>📦 طلب تسليم جزئي</h2>
        <form method="POST" action="" enctype="multipart/form-data">
            <input type="hidden" name="submit_partial_delivery" value="1">
            <div class="form-group">
                <label>عنوان التسليم <span class="req">*</span></label>
                <input type="text" name="delivery_title" required placeholder="مثال: تسليم المرحلة الأولى">
            </div>
            <div class="form-group">
                <label>تفاصيل التسليم <span class="req">*</span></label>
                <textarea name="delivery_description" required rows="4" placeholder="اذكر ما تم إنجازه..."></textarea>
            </div>
            <div class="form-group">
                <label>نسبة الإنجاز (%) <span class="req">*</span></label>
                <input type="number" name="delivery_percentage" required min="1" max="100" placeholder="50">
            </div>
            <div class="form-group">
                <label>ملف مرفق (اختياري)</label>
                <input type="file" name="delivery_file" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.zip,.rar">
            </div>
            <button type="submit" class="btn-submit">إرسال طلب التسليم</button>
            <button type="button" class="btn-close" onclick="closeModal('deliveryModal')">إلغاء</button>
        </form>
    </div>
</div>

<!-- Material Modal -->
<div id="materialModal" class="modal-overlay">
    <div class="modal-box">
        <h2>🧱 طلب موافقة على مواد</h2>
        <form method="POST" action="" enctype="multipart/form-data">
            <input type="hidden" name="submit_material_approval" value="1">
            <div class="form-group">
                <label>عنوان المادة <span class="req">*</span></label>
                <input type="text" name="material_title" required placeholder="مثال: أسمنت مقاوم">
            </div>
            <div class="form-group">
                <label>تفاصيل المادة <span class="req">*</span></label>
                <textarea name="material_description" required rows="4" placeholder="اكتب مواصفات المادة..."></textarea>
            </div>
            <div class="form-group">
                <label>نوع المادة</label>
                <select name="material_type">
                    <option value="مواد بناء">مواد بناء</option>
                    <option value="مواد كهربائية">مواد كهربائية</option>
                    <option value="مواد صحية">مواد صحية</option>
                    <option value="دهانات">دهانات</option>
                    <option value="حديد">حديد</option>
                    <option value="أخرى">أخرى</option>
                </select>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>الكمية</label>
                    <input type="number" name="material_quantity" step="0.01" placeholder="0">
                </div>
                <div class="form-group">
                    <label>الوحدة</label>
                    <input type="text" name="material_unit" placeholder="متر/كجم/قطعة">
                </div>
            </div>
            <div class="form-group">
                <label>ملف مرفق (اختياري)</label>
                <input type="file" name="material_file" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx">
            </div>
            <button type="submit" class="btn-submit">إرسال طلب الموافقة</button>
            <button type="button" class="btn-close" onclick="closeModal('materialModal')">إلغاء</button>
        </form>
    </div>
</div>

<!-- Clarification Modal -->
<div id="clarificationModal" class="modal-overlay">
    <div class="modal-box">
        <h2>❓ طلب توضيح</h2>
        <form method="POST" action="" enctype="multipart/form-data">
            <input type="hidden" name="submit_clarification" value="1">
            <div class="form-group">
                <label>عنوان الاستفسار <span class="req">*</span></label>
                <input type="text" name="clarification_title" required placeholder="مثال: توضيح حول المواصفات">
            </div>
            <div class="form-group">
                <label>تفاصيل الاستفسار <span class="req">*</span></label>
                <textarea name="clarification_description" required rows="4" placeholder="اكتب استفسارك..."></textarea>
            </div>
            <div class="form-group">
                <label>الأولوية</label>
                <select name="clarification_priority">
                    <option value="low">🟢 عادي</option>
                    <option value="medium" selected>🟡 متوسط</option>
                    <option value="high">🔴 عاجل</option>
                </select>
            </div>
            <div class="form-group">
                <label>ملف مرفق (اختياري)</label>
                <input type="file" name="clarification_file" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.dwg,.dxf">
            </div>
            <button type="submit" class="btn-submit">إرسال طلب التوضيح</button>
            <button type="button" class="btn-close" onclick="closeModal('clarificationModal')">إلغاء</button>
        </form>
    </div>
</div>

<!-- Report Modal -->
<div id="reportModal" class="modal-overlay">
    <div class="modal-box">
        <h2>📊 رفع تقرير مرحلي</h2>
        <form method="POST" action="" enctype="multipart/form-data">
            <input type="hidden" name="submit_progress_report" value="1">
            <div class="form-group">
                <label>عنوان التقرير <span class="req">*</span></label>
                <input type="text" name="report_title" required placeholder="مثال: تقرير الأسبوع الأول">
            </div>
            <div class="form-group">
                <label>ملخص التقرير <span class="req">*</span></label>
                <textarea name="report_description" required rows="3" placeholder="ملخص عن سير العمل..."></textarea>
            </div>
            <div class="form-group">
                <label>نسبة الإنجاز الحالية (%)</label>
                <input type="number" name="report_progress" min="0" max="100" placeholder="0">
            </div>
            <div class="form-group">
                <label>ما تم إنجازه</label>
                <textarea name="report_work_done" rows="3" placeholder="اذكر الأعمال التي تم إنجازها..."></textarea>
            </div>
            <div class="form-group">
                <label>الأعمال المخطط لها</label>
                <textarea name="report_work_planned" rows="3" placeholder="اذكر الأعمال المخطط لها..."></textarea>
            </div>
            <div class="form-group">
                <label>التحديات والمعوقات</label>
                <textarea name="report_challenges" rows="3" placeholder="اذكر أي تحديات واجهتك..."></textarea>
            </div>
            <div class="form-group">
                <label>ملف مرفق (اختياري)</label>
                <input type="file" name="report_file" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx">
            </div>
            <button type="submit" class="btn-submit">رفع التقرير</button>
            <button type="button" class="btn-close" onclick="closeModal('reportModal')">إلغاء</button>
        </form>
    </div>
</div>

<!-- Extension Modal -->
<div id="extensionModal" class="modal-overlay">
    <div class="modal-box">
        <h2>⏰ طلب تمديد الوقت</h2>
        <form method="POST" action="" enctype="multipart/form-data">
            <input type="hidden" name="submit_time_extension" value="1">
            <div class="form-group">
                <label>عنوان الطلب <span class="req">*</span></label>
                <input type="text" name="extension_title" required placeholder="مثال: تمديد بسبب تأخر التوريد">
            </div>
            <div class="form-group">
                <label>تفاصيل الطلب <span class="req">*</span></label>
                <textarea name="extension_description" required rows="4" placeholder="اذكر أسباب طلب التمديد..."></textarea>
            </div>
            <div class="form-group">
                <label>عدد الأيام المطلوبة <span class="req">*</span></label>
                <input type="number" name="extension_days" required min="1" placeholder="5">
            </div>
            <div class="form-group">
                <label>سبب التمديد بالتفصيل</label>
                <textarea name="extension_reason" rows="3" placeholder="اذكر الأسباب بالتفصيل..."></textarea>
            </div>
            <div class="form-group">
                <label>ملف مرفق (اختياري)</label>
                <input type="file" name="extension_file" accept=".pdf,.doc,.docx">
            </div>
            <button type="submit" class="btn-submit">إرسال طلب التمديد</button>
            <button type="button" class="btn-close" onclick="closeModal('extensionModal')">إلغاء</button>
        </form>
    </div>
</div>

<!-- Task Modal -->
<div id="taskModal" class="modal-overlay">
    <div class="modal-box">
        <h2>📋 إضافة مهمة جديدة</h2>
        <form method="POST" action="">
            <input type="hidden" name="submit_task" value="1">
            <div class="form-group">
                <label>عنوان المهمة <span class="req">*</span></label>
                <input type="text" name="task_title" required placeholder="مثال: تجهيز الموقع">
            </div>
            <div class="form-group">
                <label>تفاصيل المهمة <span class="req">*</span></label>
                <textarea name="task_description" required rows="4" placeholder="اكتب تفاصيل المهمة..."></textarea>
            </div>
            <div class="form-group">
                <label>تكليف <span class="req">*</span></label>
                <select name="task_assigned_to" required>
                    <option value="">اختر الشخص</option>
                    <?php foreach ($usersForTasks as $u): ?>
                        <option value="<?= $u['id'] ?>"><?= sanitize($u['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>تاريخ الاستحقاق</label>
                    <input type="date" name="task_due_date">
                </div>
                <div class="form-group">
                    <label>الأولوية</label>
                    <select name="task_priority">
                        <option value="low">🟢 عادي</option>
                        <option value="medium" selected>🟡 متوسط</option>
                        <option value="high">🔴 عاجل</option>
                    </select>
                </div>
            </div>
            <button type="submit" class="btn-submit">إضافة المهمة</button>
            <button type="button" class="btn-close" onclick="closeModal('taskModal')">إلغاء</button>
        </form>
    </div>
</div>

<!-- Milestone Modal -->
<div id="milestoneModal" class="modal-overlay">
    <div class="modal-box">
        <h2>🎯 إضافة معلم رئيسي</h2>
        <form method="POST" action="">
            <input type="hidden" name="submit_milestone" value="1">
            <div class="form-group">
                <label>عنوان المعلم <span class="req">*</span></label>
                <input type="text" name="milestone_title" required placeholder="مثال: الانتهاء من الأساسات">
            </div>
            <div class="form-group">
                <label>تفاصيل المعلم</label>
                <textarea name="milestone_description" rows="4" placeholder="اكتب تفاصيل المعلم..."></textarea>
            </div>
            <div class="form-group">
                <label>تاريخ الاستحقاق <span class="req">*</span></label>
                <input type="date" name="milestone_due_date" required>
            </div>
            <div class="form-group">
                <label>المبلغ المدفوع عند الإنجاز (ر.س) (اختياري)</label>
                <input type="number" name="milestone_amount" step="0.01" placeholder="0">
            </div>
            <button type="submit" class="btn-submit">إضافة المعلم</button>
            <button type="button" class="btn-close" onclick="closeModal('milestoneModal')">إلغاء</button>
        </form>
    </div>
</div>

<!-- Change Order Modal -->
<div id="changeModal" class="modal-overlay">
    <div class="modal-box">
        <h2>📝 إضافة أمر تغييري</h2>
        <form method="POST" action="" enctype="multipart/form-data">
            <input type="hidden" name="submit_change_order" value="1">
            <div class="form-group">
                <label>عنوان التغيير <span class="req">*</span></label>
                <input type="text" name="change_title" required placeholder="مثال: تعديل في مخطط الكهرباء">
            </div>
            <div class="form-group">
                <label>تفاصيل التغيير <span class="req">*</span></label>
                <textarea name="change_description" required rows="4" placeholder="اكتب التغييرات المطلوبة..."></textarea>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>تكلفة إضافية (ر.س)</label>
                    <input type="number" name="additional_cost" step="0.01" placeholder="0">
                </div>
                <div class="form-group">
                    <label>أيام إضافية</label>
                    <input type="number" name="additional_days" placeholder="0">
                </div>
            </div>
            <div class="form-group">
                <label>ملف مرفق (اختياري)</label>
                <input type="file" name="change_file" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.dwg,.dxf">
            </div>
            <button type="submit" class="btn-submit">إرسال الأمر التغييري</button>
            <button type="button" class="btn-close" onclick="closeModal('changeModal')">إلغاء</button>
        </form>
    </div>
</div>

<!-- ============================================================ -->
<!-- SCRIPTS -->
<!-- ============================================================ -->
<script>
// Tab switching
const tabs = document.querySelectorAll('#workspaceTabs a');
const contents = document.querySelectorAll('.tab-content');

tabs.forEach(tab => {
    tab.addEventListener('click', function(e) {
        e.preventDefault();
        const target = this.dataset.tab;

        tabs.forEach(t => t.classList.remove('active'));
        this.classList.add('active');

        contents.forEach(c => c.classList.remove('active'));
        document.getElementById('tab-' + target)?.classList.add('active');

        history.replaceState(null, null, '#tab-' + target);
    });
});

// Restore tab from hash
const hash = location.hash;
if (hash.startsWith('#tab-')) {
    const tabId = hash.replace('#tab-', '');
    const link = document.querySelector('#workspaceTabs a[data-tab="' + tabId + '"]');
    if (link) link.click();
}

// Modal helpers
function openModal(id) {
    document.getElementById(id).classList.add('open');
    document.body.style.overflow = 'hidden';
}
function closeModal(id) {
    document.getElementById(id).classList.remove('open');
    document.body.style.overflow = '';
    const form = document.getElementById(id).querySelector('form');
    if (form) form.reset();
}

// Close on backdrop click
document.querySelectorAll('.modal-overlay').forEach(modal => {
    modal.addEventListener('click', e => {
        if (e.target === modal) closeModal(modal.id);
    });
});

// Auto-scroll chat
const chatBox = document.getElementById('chatMessages');
if (chatBox) chatBox.scrollTop = chatBox.scrollHeight;

// Prevent resubmission on refresh
if (history.replaceState) {
    history.replaceState(null, null, location.href);
}

// Double-submit prevention
document.querySelectorAll('form').forEach(form => {
    form.addEventListener('submit', function(e) {
        const btn = this.querySelector('button[type="submit"]');
        if (!btn || btn.disabled) return;
        btn.disabled = true;
        const original = btn.innerHTML;
        btn.innerHTML = 'جاري الإرسال...';
        setTimeout(() => {
            btn.disabled = false;
            btn.innerHTML = original;
        }, 5000);
    });
});

// Keyboard shortcut: Escape closes modals
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-overlay.open').forEach(m => closeModal(m.id));
    }
});

// Toast notification for response results
<?php if (isset($_SESSION['response_result'])): ?>
    (function() {
        const result = <?= json_encode($_SESSION['response_result']) ?>;
        showToast(result.success ? 'success' : 'error', result.message);
        <?php unset($_SESSION['response_result']); ?>
    })();
<?php endif; ?>

function showToast(type, message) {
    const container = document.getElementById('toastContainer');
    const icons = {
        success: '✅',
        error: '❌',
        warning: '⚠️'
    };
    const toast = document.createElement('div');
    toast.className = 'toast ' + type;
    toast.innerHTML = `
        <span class="icon">${icons[type] || 'ℹ️'}</span>
        <span class="msg">${message}</span>
        <button class="close" onclick="this.parentElement.remove()">&times;</button>
    `;
    container.appendChild(toast);
    setTimeout(() => {
        if (toast.parentElement) toast.remove();
    }, 5000);
}

// Response form - remove confirm dialog for better UX
document.querySelectorAll('.response-form').forEach(form => {
    form.addEventListener('submit', function(e) {
        const actionBtn = this.querySelector('button[type="submit"]:focus');
        if (actionBtn) {
            const action = actionBtn.value;
            const messages = {
                'approve': 'هل أنت متأكد من قبول هذا الطلب؟',
                'reject': 'هل أنت متأكد من رفض هذا الطلب؟',
                'revise': 'هل أنت متأكد من طلب المراجعة؟',
                'answer': 'هل أنت متأكد من الرد على هذا الاستفسار؟'
            };
            if (messages[action] && !confirm(messages[action])) {
                e.preventDefault();
            }
        }
    });
});
</script>

</body>
</html>

<?php include 'includes/footer.php'; ?>