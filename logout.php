<?php
session_start();

// پاک کردن تمام متغیرهای session
$_SESSION = array();

// پاک کردن کوکی session
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// نابود کردن session
session_destroy();

// پاک کردن کوکی sso_token
setcookie('sso_token', '', time() - 3600, '/', '.itziliya-dev.ir', true, true); // دامنه را بررسی کنید

// هدایت به صفحه ورود
header('Location: login.php');
exit();
?>