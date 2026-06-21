<?php
require_once 'config.php';

if (!isLoggedIn() || getUserType() !== 'admin') {
    redirect('login.php');
}

$page_title = 'لوحة التحكم';
include 'includes/header.php';
?>

<div class="py-8">
    <div class="max-w-7xl mx-auto px-4">
        
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
            ✅ تم تسجيل الدخول كأدمن بنجاح!
        </div>
        
        <h1 class="text-3xl font-bold text-gray-800 mb-6">لوحة التحكم</h1>
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <a href="<?= SITE_URL ?>/admin_documents.php" class="bg-white p-6 rounded-xl shadow-md border border-gray-200 text-center hover:shadow-lg transition">
                <i class="fas fa-file-alt text-4xl text-primary-600 mb-3"></i>
                <h3 class="font-bold text-lg">المستندات</h3>
                <p class="text-sm text-gray-500">مراجعة مستندات المقاولين</p>
            </a>
            
            <a href="<?= SITE_URL ?>/users_list.php" class="bg-white p-6 rounded-xl shadow-md border border-gray-200 text-center hover:shadow-lg transition">
                <i class="fas fa-users text-4xl text-blue-600 mb-3"></i>
                <h3 class="font-bold text-lg">المستخدمين</h3>
                <p class="text-sm text-gray-500">إدارة المستخدمين</p>
            </a>
            
            <a href="<?= SITE_URL ?>/projects_list.php" class="bg-white p-6 rounded-xl shadow-md border border-gray-200 text-center hover:shadow-lg transition">
                <i class="fas fa-project-diagram text-4xl text-green-600 mb-3"></i>
                <h3 class="font-bold text-lg">المشاريع</h3>
                <p class="text-sm text-gray-500">إدارة المشاريع</p>
            </a>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>