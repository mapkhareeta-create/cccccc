<?php
/**
 * ملف تذييل الموقع
 * يجب تضمينه في نهاية جميع الصفحات
 */
?>

<!-- Footer -->
<footer class="bg-gray-900 text-gray-300 mt-20">
    <div class="max-w-7xl mx-auto px-3 md:px-6 py-12">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
            <!-- عن الموقع -->
            <div>
                <h3 class="text-white font-bold mb-4">
                    <i class="fas fa-building ml-2"></i><?php echo SITE_NAME; ?>
                </h3>
                <p class="text-sm text-gray-400">منصة عربية متخصصة في ربط أصحاب المشاريع بالمقاولين الموثوقين</p>
            </div>
            
            <!-- الروابط السريعة -->
            <div>
                <h4 class="text-white font-bold mb-4">روابط سريعة</h4>
                <ul class="space-y-2 text-sm">
                    <li><a href="<?php echo SITE_URL; ?>/" class="hover:text-blue-400">الرئيسية</a></li>
                    <li><a href="<?php echo SITE_URL; ?>/projects_list.php" class="hover:text-blue-400">المشاريع</a></li>
                    <li><a href="<?php echo SITE_URL; ?>/contractors_list.php" class="hover:text-blue-400">المقاولين</a></li>
                    <li><a href="<?php echo SITE_URL; ?>/shops_list.php" class="hover:text-blue-400">المحلات</a></li>
                </ul>
            </div>
            
            <!-- معلومات إضافية -->
            <div>
                <h4 class="text-white font-bold mb-4">معلومات</h4>
                <ul class="space-y-2 text-sm">
                    <li><a href="#" class="hover:text-blue-400">عن الموقع</a></li>
                    <li><a href="#" class="hover:text-blue-400">سياسة الخصوصية</a></li>
                    <li><a href="#" class="hover:text-blue-400">شروط الاستخدام</a></li>
                    <li><a href="#" class="hover:text-blue-400">تواصل معنا</a></li>
                </ul>
            </div>
            
            <!-- التواصل -->
            <div>
                <h4 class="text-white font-bold mb-4">التواصل</h4>
                <ul class="space-y-2 text-sm">
                    <li><i class="fas fa-envelope ml-2"></i>info@omranhub.com</li>
                    <li><i class="fas fa-phone ml-2"></i>+966-XX-XXX-XXXX</li>
                    <li class="flex gap-3 mt-4">
                        <a href="#" class="text-blue-400 hover:text-blue-300"><i class="fab fa-facebook"></i></a>
                        <a href="#" class="text-blue-400 hover:text-blue-300"><i class="fab fa-twitter"></i></a>
                        <a href="#" class="text-blue-400 hover:text-blue-300"><i class="fab fa-linkedin"></i></a>
                    </li>
                </ul>
            </div>
        </div>
        
        <!-- حقوق النشر -->
        <div class="border-t border-gray-800 mt-8 pt-8 text-center text-sm">
            <p>&copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?> - جميع الحقوق محفوظة</p>
        </div>
    </div>
</footer>

</body>
</html>