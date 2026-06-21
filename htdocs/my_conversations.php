<?php
require_once 'config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$user_id = $_SESSION['user_id'];
$user_type = getUserType();

// جلب جميع محادثات المستخدم
if ($user_type === 'employer') {
    $stmt = $pdo->prepare("
        SELECT c.*, p.title as project_title, u.name as other_party_name,
               c.contractor_unread as unread_count
        FROM conversations c
        JOIN projects p ON c.project_id = p.id
        JOIN users u ON c.contractor_id = u.id
        WHERE c.employer_id = ?
        ORDER BY c.updated_at DESC
    ");
} else {
    $stmt = $pdo->prepare("
        SELECT c.*, p.title as project_title, u.name as other_party_name,
               c.employer_unread as unread_count
        FROM conversations c
        JOIN projects p ON c.project_id = p.id
        JOIN users u ON c.employer_id = u.id
        WHERE c.contractor_id = ?
        ORDER BY c.updated_at DESC
    ");
}
$stmt->execute([$user_id]);
$conversations = $stmt->fetchAll();

$page_title = 'محادثاتي';
include 'includes/header.php';
?>

<section class="py-12 bg-gray-50 min-h-screen">
    <div class="max-w-4xl mx-auto px-4">
        <div class="bg-white rounded-2xl shadow-md border border-gray-100 overflow-hidden">
            <div class="bg-gradient-to-r from-blue-600 to-indigo-600 p-5 text-white">
                <div class="flex items-center gap-3">
                    <i class="fas fa-comments text-2xl"></i>
                    <h1 class="text-2xl font-bold">محادثاتي</h1>
                </div>
                <p class="text-blue-100 text-sm mt-1">تواصل مع الأطراف الأخرى لمناقشة التفاصيل</p>
            </div>
            
            <div class="divide-y divide-gray-100">
                <?php if (empty($conversations)): ?>
                    <div class="text-center py-16 text-gray-400">
                        <i class="fas fa-comment-dots text-6xl mb-4 opacity-50"></i>
                        <p>لا توجد محادثات بعد</p>
                        <p class="text-sm mt-2">ابدأ محادثة من صفحة تفاصيل المشروع</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($conversations as $conv): ?>
                        <a href="<?= SITE_URL ?>/chat.php?id=<?= $conv['id'] ?>" class="block p-5 hover:bg-gray-50 transition-colors <?= $conv['unread_count'] > 0 ? 'bg-blue-50' : '' ?>">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-4">
                                    <div class="w-12 h-12 bg-gradient-to-r from-blue-500 to-indigo-500 rounded-xl flex items-center justify-center text-white">
                                        <i class="fas fa-comment-dots text-xl"></i>
                                    </div>
                                    <div>
                                        <h3 class="font-bold text-gray-800"><?= clean($conv['project_title']) ?></h3>
                                        <p class="text-sm text-gray-500">مع: <?= clean($conv['other_party_name']) ?></p>
                                        <?php if ($conv['unread_count'] > 0): ?>
                                            <span class="inline-block bg-blue-500 text-white text-xs px-2 py-0.5 rounded-full mt-1">
                                                <?= $conv['unread_count'] ?> رسائل جديدة
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="text-gray-400">
                                    <i class="fas fa-chevron-left"></i>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<?php include 'includes/footer.php'; ?>