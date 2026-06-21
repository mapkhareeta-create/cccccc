<?php
require_once 'config.php';

// التأكد من أن المستخدم أدمن
if (!isLoggedIn() || getUserType() !== 'admin') {
    redirect('login.php');
}

// معالجة قبول أو رفض المستند
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $document_id = (int)($_POST['document_id'] ?? 0);
    $action = clean($_POST['action']);
    $reject_reason = clean($_POST['reject_reason'] ?? '');
    
    if ($action === 'approve') {
        // قبول المستند
        $stmt = $pdo->prepare("UPDATE contractor_documents SET status = 'approved' WHERE id = ?");
        $stmt->execute([$document_id]);
        
        // جلب معلومات المستند
        $stmt = $pdo->prepare("
            SELECT cd.*, u.name as contractor_name, u.id as contractor_id 
            FROM contractor_documents cd
            JOIN users u ON cd.contractor_id = u.id
            WHERE cd.id = ?
        ");
        $stmt->execute([$document_id]);
        $doc = $stmt->fetch();
        
        if ($doc) {
            // إشعار للمقاول
            createNotification(
                $doc['contractor_id'],
                'تم قبول مستندك',
                'تم قبول المستند: ' . $doc['document_name'],
                'success',
                SITE_URL . '/profile.php?id=' . $doc['contractor_id']
            );
            
            // التحقق من جميع مستندات المقاول
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as total, 
                       SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved
                FROM contractor_documents 
                WHERE contractor_id = ?
            ");
            $stmt->execute([$doc['contractor_id']]);
            $stats = $stmt->fetch();
            
            // إذا كان عنده 3 مستندات معتمدة على الأقل، وثق الحساب تلقائياً
            if ($stats['approved'] >= 3 && $stats['total'] >= 3) {
                $stmt = $pdo->prepare("
                    UPDATE users 
                    SET verification_status = 'approved', 
                        verified_at = NOW() 
                    WHERE id = ?
                ");
                $stmt->execute([$doc['contractor_id']]);
                
                createNotification(
                    $doc['contractor_id'],
                    'تهانينا! حسابك موثق الآن',
                    'تم توثيق حسابك بناءً على المستندات المعتمدة. أصبحت الآن مقاولاً موثوقاً!',
                    'success',
                    SITE_URL . '/profile.php?id=' . $doc['contractor_id']
                );
            }
        }
        
        $_SESSION['success'] = 'تم قبول المستند بنجاح';
        
    } elseif ($action === 'reject') {
        // رفض المستند
        $stmt = $pdo->prepare("UPDATE contractor_documents SET status = 'rejected' WHERE id = ?");
        $stmt->execute([$document_id]);
        
        // جلب معلومات المستند
        $stmt = $pdo->prepare("
            SELECT cd.*, u.name as contractor_name, u.id as contractor_id 
            FROM contractor_documents cd
            JOIN users u ON cd.contractor_id = u.id
            WHERE cd.id = ?
        ");
        $stmt->execute([$document_id]);
        $doc = $stmt->fetch();
        
        if ($doc) {
            createNotification(
                $doc['contractor_id'],
                'تم رفض مستندك',
                'تم رفض المستند: ' . $doc['document_name'] . ($reject_reason ? ' - السبب: ' . $reject_reason : ''),
                'error',
                SITE_URL . '/profile.php?id=' . $doc['contractor_id']
            );
        }
        
        $_SESSION['success'] = 'تم رفض المستند';
    }
    
    redirect('admin_documents.php');
}

// تحديث حالة توثيق المقاول يدوياً
if (isset($_GET['verify_contractor'])) {
    $contractor_id = (int)($_GET['verify_contractor'] ?? 0);
    $status = clean($_GET['status'] ?? '');
    
    if (in_array($status, ['approved', 'rejected'])) {
        $stmt = $pdo->prepare("UPDATE users SET verification_status = ?, verified_at = NOW() WHERE id = ?");
        $stmt->execute([$status, $contractor_id]);
        
        createNotification(
            $contractor_id,
            $status === 'approved' ? 'تم توثيق حسابك' : 'تم رفض توثيق حسابك',
            $status === 'approved' ? 'تم اعتماد حسابك كمقاول موثق' : 'لم تتم الموافقة على طلب التوثيق الخاص بك',
            $status === 'approved' ? 'success' : 'error',
            SITE_URL . '/profile.php?id=' . $contractor_id
        );
        
        $_SESSION['success'] = 'تم تحديث حالة التوثيق';
        redirect('admin_documents.php');
    }
}

// تحديث حالة المميز
if (isset($_GET['featured_contractor'])) {
    $contractor_id = (int)($_GET['featured_contractor'] ?? 0);
    $featured = (int)($_GET['featured'] ?? 0);
    $featured_until = $_GET['featured_until'] ?? null;
    
    $stmt = $pdo->prepare("UPDATE users SET is_featured = ?, featured_until = ? WHERE id = ?");
    $stmt->execute([$featured, $featured_until, $contractor_id]);
    
    if ($featured) {
        createNotification(
            $contractor_id,
            'تهانينا! أصبحت مقاولاً مميزاً',
            'تم اختيارك كمقاول مميز على المنصة. سيظهر مشروعك في أعلى قائمة العطاءات',
            'success',
            SITE_URL . '/profile.php?id=' . $contractor_id
        );
    }
    
    $_SESSION['success'] = 'تم تحديث حالة المميز';
    redirect('admin_documents.php');
}

// جلب قائمة المستندات قيد المراجعة
$stmt = $pdo->prepare("
    SELECT cd.*, u.name as contractor_name, u.email as contractor_email,
           u.verification_status, u.contractor_class, u.is_featured,
           (SELECT COUNT(*) FROM contractor_documents WHERE contractor_id = u.id AND status = 'approved') as approved_docs_count
    FROM contractor_documents cd
    JOIN users u ON cd.contractor_id = u.id
    WHERE cd.status = 'pending'
    ORDER BY cd.uploaded_at ASC
");
$stmt->execute();
$pending_documents = $stmt->fetchAll();

// جلب قائمة المستندات المقبولة والمرفوضة
$stmt = $pdo->prepare("
    SELECT cd.*, u.name as contractor_name, u.email as contractor_email,
           u.verification_status, u.contractor_class, u.is_featured
    FROM contractor_documents cd
    JOIN users u ON cd.contractor_id = u.id
    WHERE cd.status != 'pending'
    ORDER BY cd.uploaded_at DESC
    LIMIT 50
");
$stmt->execute();
$reviewed_documents = $stmt->fetchAll();

// جلب قائمة المقاولين غير الموثقين
$stmt = $pdo->prepare("
    SELECT u.*, 
           (SELECT COUNT(*) FROM contractor_documents WHERE contractor_id = u.id) as docs_count,
           (SELECT COUNT(*) FROM contractor_documents WHERE contractor_id = u.id AND status = 'approved') as approved_docs
    FROM users u
    WHERE u.user_type = 'contractor' 
    ORDER BY 
        CASE u.verification_status
            WHEN 'pending' THEN 1
            WHEN 'approved' THEN 2
            WHEN 'rejected' THEN 3
        END,
        u.created_at DESC
");
$stmt->execute();
$contractors = $stmt->fetchAll();

$page_title = 'لوحة تحكم المستندات';
include 'includes/header.php';
?>

<div class="py-8">
    <div class="max-w-7xl mx-auto px-4">
        
        <!-- Page Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-800 flex items-center gap-3">
                <i class="fas fa-file-alt text-primary-600"></i> 
                إدارة مستندات المقاولين
            </h1>
            <p class="text-gray-500 mt-2">مراجعة وقبول مستندات المقاولين وتوثيق حساباتهم</p>
        </div>
        
        <!-- Success/Error Messages -->
        <?php if (isset($_SESSION['success'])): ?>
        <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-xl mb-6 flex items-center gap-2">
            <i class="fas fa-check-circle"></i>
            <span><?= $_SESSION['success'] ?></span>
        </div>
        <?php unset($_SESSION['success']); endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
        <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl mb-6 flex items-center gap-2">
            <i class="fas fa-exclamation-circle"></i>
            <span><?= $_SESSION['error'] ?></span>
        </div>
        <?php unset($_SESSION['error']); endif; ?>
        
        <!-- Pending Documents Section -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden mb-8">
            <div class="bg-yellow-50 p-5 border-b border-yellow-100">
                <h2 class="font-bold text-lg flex items-center gap-2">
                    <i class="fas fa-clock text-yellow-600"></i> 
                    مستندات قيد المراجعة
                    <span class="bg-yellow-200 text-yellow-800 px-2 py-0.5 rounded-full text-xs"><?= count($pending_documents) ?></span>
                </h2>
            </div>
            
            <?php if (empty($pending_documents)): ?>
            <div class="p-8 text-center">
                <i class="fas fa-check-circle text-5xl text-green-300 mb-3"></i>
                <p class="text-gray-400">لا توجد مستندات قيد المراجعة حالياً</p>
            </div>
            <?php else: ?>
            <div class="divide-y divide-gray-100">
                <?php foreach ($pending_documents as $doc): ?>
                <div class="p-5 hover:bg-gray-50 transition-colors">
                    <div class="flex items-start justify-between">
                        <div class="flex-1">
                            <div class="flex items-center gap-3 flex-wrap mb-2">
                                <h3 class="font-bold text-gray-800"><?= clean($doc['contractor_name']) ?></h3>
                                <span class="text-xs text-gray-400"><?= clean($doc['contractor_email']) ?></span>
                                <span class="bg-gray-100 text-gray-600 text-xs px-2 py-0.5 rounded-full">
                                    <?php
                                    $doc_types = [
                                        'commercial_register' => 'سجل تجاري',
                                        'tax_card' => 'بطاقة ضريبية',
                                        'professional_license' => 'رخصة مهنية',
                                        'bank_account' => 'حساب بنكي',
                                        'previous_work' => 'أعمال سابقة',
                                        'other' => 'أخرى'
                                    ];
                                    echo $doc_types[$doc['document_type']] ?? $doc['document_type'];
                                    ?>
                                </span>
                            </div>
                            <p class="text-gray-600 text-sm mb-2"><strong>اسم المستند:</strong> <?= clean($doc['document_name']) ?></p>
                            <div class="text-xs text-gray-400 mb-3">
                                <i class="fas fa-calendar-alt ml-1"></i> تم الرفع: <?= date('Y/m/d - H:i', strtotime($doc['uploaded_at'])) ?>
                            </div>
                            
                            <div class="flex gap-3">
                                <a href="<?= SITE_URL ?>/<?= $doc['document_path'] ?>" target="_blank" class="text-blue-600 hover:text-blue-700 text-sm flex items-center gap-1">
                                    <i class="fas fa-eye"></i> معاينة المستند
                                </a>
                                <a href="<?= SITE_URL ?>/profile.php?id=<?= $doc['contractor_id'] ?>" target="_blank" class="text-gray-600 hover:text-gray-700 text-sm flex items-center gap-1">
                                    <i class="fas fa-user"></i> عرض ملف المقاول
                                </a>
                            </div>
                        </div>
                        
                        <form method="POST" class="flex gap-2 mr-4">
                            <input type="hidden" name="document_id" value="<?= $doc['id'] ?>">
                            
                            <button type="submit" name="action" value="approve" 
                                    class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors"
                                    onclick="return confirm('هل أنت متأكد من قبول هذا المستند؟')">
                                <i class="fas fa-check ml-1"></i> قبول
                            </button>
                            
                            <button type="button" 
                                    onclick="showRejectModal(<?= $doc['id'] ?>, '<?= addslashes($doc['document_name']) ?>')"
                                    class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                                <i class="fas fa-times ml-1"></i> رفض
                            </button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Contractors Management Section -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden mb-8">
            <div class="bg-purple-50 p-5 border-b border-purple-100">
                <h2 class="font-bold text-lg flex items-center gap-2">
                    <i class="fas fa-users text-purple-600"></i> 
                    إدارة المقاولين
                </h2>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50 border-b border-gray-100">
                        <tr>
                            <th class="text-right p-4 font-bold text-gray-600">المقاول</th>
                            <th class="text-right p-4 font-bold text-gray-600">المستندات</th>
                            <th class="text-right p-4 font-bold text-gray-600">حالة التوثيق</th>
                            <th class="text-right p-4 font-bold text-gray-600">التصنيف</th>
                            <th class="text-right p-4 font-bold text-gray-600">مميز</th>
                            <th class="text-right p-4 font-bold text-gray-600">إجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($contractors as $contractor): ?>
                        <tr class="border-b border-gray-100 hover:bg-gray-50">
                            <td class="p-4">
                                <div>
                                    <div class="font-bold text-gray-800"><?= clean($contractor['name']) ?></div>
                                    <div class="text-xs text-gray-400"><?= clean($contractor['email']) ?></div>
                                </div>
                            </td>
                            <td class="p-4">
                                <div class="text-sm">
                                    <span class="text-green-600">✔️ معتمد: <?= $contractor['approved_docs'] ?? 0 ?></span>
                                    <span class="text-gray-400 mr-2">/ <?= $contractor['docs_count'] ?? 0 ?> مستند</span>
                                </div>
                            </td>
                            <td class="p-4">
                                <?php if ($contractor['verification_status'] === 'approved'): ?>
                                <span class="bg-green-100 text-green-700 px-2 py-1 rounded-full text-xs">موثق ✅</span>
                                <?php elseif ($contractor['verification_status'] === 'rejected'): ?>
                                <span class="bg-red-100 text-red-700 px-2 py-1 rounded-full text-xs">مرفوض ❌</span>
                                <?php else: ?>
                                <span class="bg-yellow-100 text-yellow-700 px-2 py-1 rounded-full text-xs">قيد المراجعة ⏳</span>
                                <?php endif; ?>
                            </td>
                            <td class="p-4">
                                <select onchange="updateContractorClass(<?= $contractor['id'] ?>, this.value)" 
                                        class="border border-gray-200 rounded-lg px-2 py-1 text-sm">
                                    <option value="pending" <?= $contractor['contractor_class'] == 'pending' ? 'selected' : '' ?>>قيد التصنيف</option>
                                    <option value="A" <?= $contractor['contractor_class'] == 'A' ? 'selected' : '' ?>>تصنيف أ</option>
                                    <option value="B" <?= $contractor['contractor_class'] == 'B' ? 'selected' : '' ?>>تصنيف ب</option>
                                    <option value="C" <?= $contractor['contractor_class'] == 'C' ? 'selected' : '' ?>>تصنيف ج</option>
                                    <option value="D" <?= $contractor['contractor_class'] == 'D' ? 'selected' : '' ?>>تصنيف د</option>
                                </select>
                            </td>
                            <td class="p-4">
                                <div class="flex items-center gap-2">
                                    <button onclick="toggleFeatured(<?= $contractor['id'] ?>, <?= $contractor['is_featured'] ? 0 : 1 ?>)" 
                                            class="<?= $contractor['is_featured'] ? 'bg-amber-500' : 'bg-gray-200' ?> hover:opacity-80 text-white px-3 py-1 rounded-lg text-sm transition-colors">
                                        <i class="fas fa-crown"></i> <?= $contractor['is_featured'] ? 'مميز' : 'جعل مميز' ?>
                                    </button>
                                </div>
                            </td>
                            <td class="p-4">
                                <div class="flex gap-2">
                                    <a href="<?= SITE_URL ?>/profile.php?id=<?= $contractor['id'] ?>" target="_blank" 
                                       class="text-blue-600 hover:text-blue-700 text-sm">
                                        <i class="fas fa-eye"></i> عرض
                                    </a>
                                    <?php if ($contractor['verification_status'] !== 'approved'): ?>
                                    <a href="?verify_contractor=<?= $contractor['id'] ?>&status=approved" 
                                       onclick="return confirm('هل تريد توثيق هذا المقاول؟')"
                                       class="text-green-600 hover:text-green-700 text-sm">
                                        توثيق
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Reviewed Documents Section -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="bg-gray-50 p-5 border-b border-gray-100">
                <h2 class="font-bold text-lg flex items-center gap-2">
                    <i class="fas fa-history text-gray-600"></i> 
                    المستندات السابقة (مقبولة ومرفوضة)
                </h2>
            </div>
            
            <?php if (empty($reviewed_documents)): ?>
            <div class="p-8 text-center">
                <p class="text-gray-400">لا توجد مستندات سابقة</p>
            </div>
            <?php else: ?>
            <div class="divide-y divide-gray-100">
                <?php foreach ($reviewed_documents as $doc): ?>
                <div class="p-4 hover:bg-gray-50 transition-colors">
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="font-medium text-gray-800"><?= clean($doc['contractor_name']) ?></span>
                                <span class="text-xs text-gray-400"><?= clean($doc['document_name']) ?></span>
                                <?php if ($doc['status'] === 'approved'): ?>
                                <span class="bg-green-100 text-green-700 text-xs px-2 py-0.5 rounded-full">مقبول</span>
                                <?php else: ?>
                                <span class="bg-red-100 text-red-700 text-xs px-2 py-0.5 rounded-full">مرفوض</span>
                                <?php endif; ?>
                            </div>
                            <div class="text-xs text-gray-400 mt-1">رفع: <?= date('Y/m/d', strtotime($doc['uploaded_at'])) ?></div>
                        </div>
                        <a href="<?= SITE_URL ?>/<?= $doc['document_path'] ?>" target="_blank" class="text-blue-600 text-sm">
                            <i class="fas fa-download"></i> تحميل
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal for Rejection -->
<div id="rejectModal" class="hidden fixed inset-0 z-50 modal-backdrop flex items-center justify-center p-4" onclick="if(event.target===this) closeRejectModal()">
    <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="font-bold text-lg text-gray-800">رفض المستند</h3>
            <button onclick="closeRejectModal()" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        
        <form method="POST" id="rejectForm">
            <input type="hidden" name="document_id" id="reject_document_id">
            <input type="hidden" name="action" value="reject">
            
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">سبب الرفض (اختياري)</label>
                <textarea name="reject_reason" rows="3" class="w-full border border-gray-300 rounded-xl px-4 py-2 focus:ring-red-500 focus:border-red-500" 
                          placeholder="اكتب سبب رفض المستند..."></textarea>
            </div>
            
            <div class="flex gap-3">
                <button type="button" onclick="closeRejectModal()" class="flex-1 bg-gray-200 text-gray-700 py-2 rounded-lg">إلغاء</button>
                <button type="submit" class="flex-1 bg-red-600 text-white py-2 rounded-lg hover:bg-red-700">تأكيد الرفض</button>
            </div>
        </form>
    </div>
</div>

<script>
function showRejectModal(documentId, documentName) {
    document.getElementById('reject_document_id').value = documentId;
    document.getElementById('rejectModal').classList.remove('hidden');
}

function closeRejectModal() {
    document.getElementById('rejectModal').classList.add('hidden');
}

function updateContractorClass(contractorId, classValue) {
    fetch('api.php?action=update_contractor_class', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'contractor_id=' + contractorId + '&class=' + classValue
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            showToast('تم تحديث تصنيف المقاول بنجاح', 'success');
        } else {
            showToast('حدث خطأ', 'error');
        }
    });
}

function toggleFeatured(contractorId, featured) {
    let featuredUntil = '';
    if (featured == 1) {
        featuredUntil = prompt('كم يوم تريد أن يكون المقاول مميزاً؟ (اتركه فارغاً بدون تاريخ انتهاء)', '30');
        if (featuredUntil) {
            let days = parseInt(featuredUntil);
            if (!isNaN(days)) {
                let date = new Date();
                date.setDate(date.getDate() + days);
                featuredUntil = date.toISOString().split('T')[0];
            }
        }
    }
    
    let url = `admin_documents.php?featured_contractor=${contractorId}&featured=${featured}`;
    if (featuredUntil) {
        url += `&featured_until=${featuredUntil}`;
    }
    window.location.href = url;
}

function showToast(message, type) {
    // يمكنك استخدام دالة showToast الموجودة في header.php
    if (typeof window.showToast === 'function') {
        window.showToast(message, type);
    } else {
        alert(message);
    }
}
</script>

<?php include 'includes/footer.php'; ?>