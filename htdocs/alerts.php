<?php
/**
 * ============================================================
 * ALERTS.PHP - صفحة عرض جميع الإشعارات
 * ============================================================
 */

require_once 'config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$user_id = $_SESSION['user_id'];

// تعليم الإشعارات كمقروءة عند فتح الصفحة
$pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?")->execute([$user_id]);

// جلب جميع الإشعارات
$stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$user_id]);
$notifications = $stmt->fetchAll();

$page_title = 'الإشعارات';
include 'includes/header.php';
?>

<div class="py-8">
    <div class="max-w-4xl mx-auto px-4">
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
                <i class="fas fa-bell text-blue-500"></i> الإشعارات
                <span class="bg-gray-200 text-gray-600 text-xs px-2 py-1 rounded-full"><?= count($notifications) ?></span>
            </h1>
            <?php if (!empty($notifications)): ?>
            <a href="<?= SITE_URL ?>/api/mark_all_read.php" class="text-blue-600 hover:text-blue-700 text-sm font-medium">
                <i class="fas fa-check-double ml-1"></i> تعليم الكل كمقروء
            </a>
            <?php endif; ?>
        </div>
        
        <?php if (empty($notifications)): ?>
        <div class="bg-white rounded-2xl p-12 text-center border border-gray-100 shadow-sm">
            <i class="fas fa-bell-slash text-6xl text-gray-200 mb-4"></i>
            <p class="text-gray-400">لا توجد إشعارات حتى الآن</p>
            <p class="text-gray-300 text-sm mt-2">ستظهر هنا جميع الإشعارات المتعلقة بمشاريعك وعطاءاتك</p>
        </div>
        <?php else: ?>
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
            <div class="divide-y divide-gray-50">
                <?php foreach ($notifications as $notif): ?>
                <div class="p-4 flex items-start gap-3 hover:bg-gray-50 transition-colors <?= $notif['is_read'] ? '' : 'bg-blue-50/30 border-r-4 border-blue-500' ?>">
                    <div class="flex-shrink-0 mt-1">
                        <?php
                        $icon = [
                            'info' => 'fa-info-circle text-blue-500',
                            'success' => 'fa-check-circle text-green-500',
                            'warning' => 'fa-exclamation-triangle text-amber-500',
                            'error' => 'fa-times-circle text-red-500'
                        ];
                        $icon_class = $icon[$notif['type']] ?? 'fa-bell text-gray-400';
                        ?>
                        <i class="fas <?= $icon_class ?> text-xl"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                        <h4 class="font-bold text-gray-800 text-sm <?= $notif['is_read'] ? '' : 'text-blue-700' ?>">
                            <?= clean($notif['title']) ?>
                        </h4>
                        <p class="text-gray-600 text-sm mt-1"><?= nl2br(clean($notif['message'])) ?></p>
                        <div class="flex items-center gap-3 mt-2 text-xs text-gray-400">
                            <span><i class="far fa-clock ml-1"></i> <?= timeAgo($notif['created_at']) ?></span>
                            <?php if ($notif['link']): ?>
                            <a href="<?= $notif['link'] ?>" class="text-blue-600 hover:text-blue-700 font-medium">عرض التفاصيل <i class="fas fa-arrow-left mr-1"></i></a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php if (!$notif['is_read']): ?>
                    <span class="flex-shrink-0 w-2 h-2 bg-blue-500 rounded-full mt-2"></span>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>