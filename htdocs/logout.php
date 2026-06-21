<?php
require_once 'config.php';

// تسجيل خروج
 $_SESSION = [];
session_destroy();

// حذف كوكيز الجلسة
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

redirect('login.php');
?>