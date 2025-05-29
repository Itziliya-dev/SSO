<?php
// این خطوط برای نمایش خطا در محیط تست مفید هستند
// در محیط پروداکشن، اینها را کامنت یا حذف کنید و خطاها را در لاگ سرور بررسی کنید.
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// مسیر config.php را با ساختار پروژه خود تطبیق دهید
// اگر auth.php در ریشه است و includes هم در ریشه است:
require_once __DIR__.'/includes/config.php'; // config.php باید قبل از session_start() باشد اگر تنظیمات ini_set سشن در آن است
require_once __DIR__.'/includes/auth_functions.php';

// شروع session (اگر در config.php با ini_set تنظیمات انجام شده، اینجا فقط session_start کافیست)
if (session_status() === PHP_SESSION_NONE) {
    // اگر تنظیمات امنیتی سشن در config.php نیستند، می‌توانید آنها را اینجا هم بگذارید:
    // ini_set('session.cookie_httponly', 1);
    // ini_set('session.cookie_secure', 1); // برای HTTPS
    // ini_set('session.use_strict_mode', 1);
    session_start([
        'cookie_lifetime' => 86400, // 1 روز
        'cookie_secure' => true,    // فقط روی HTTPS ارسال شود (در لوکال هاست بدون HTTPS، false کنید)
        'cookie_httponly' => true,  // فقط توسط HTTP قابل دسترسی باشد، نه جاوااسکریپت
        'cookie_samesite' => 'Lax'  // یا 'Strict'
    ]);
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';

        if (empty($username) || empty($password)) {
            throw new Exception('نام کاربری و رمز عبور الزامی است');
        }

        // استفاده از تابع احراز هویت جدید
        $authResult = authenticateUserOrStaff($username, $password);

        if ($authResult['status'] === 'error') {
            throw new Exception($authResult['message']);
        }

        $user_info = $authResult['data']; // اطلاعات کاربر یا استف

        // پاک کردن سشن قبلی (اگر وجود داشت) برای جلوگیری از تداخل داده‌ها
        session_regenerate_id(true); // بازسازی ID سشن برای امنیت بیشتر
        $_SESSION = []; // خالی کردن آرایه سشن

        // ذخیره اطلاعات کاربر در session
        $_SESSION['user_id'] = $user_info['id']; // این برای user از users.id و برای staff از staff_manage.user_id است (طبق آخرین auth_functions)
        $_SESSION['username'] = $user_info['username'];
        $_SESSION['user_type'] = $user_info['type']; // 'user' یا 'staff'
        $_SESSION['is_staff'] = $user_info['is_staff'] ?? 0; // اگر 'user' باشد، is_staff خودش را دارد

        if ($user_info['type'] === 'user') {
            $_SESSION['is_owner'] = $user_info['is_owner'] ?? 0;
            $_SESSION['has_user_panel'] = $user_info['has_user_panel'] ?? 1; // پیش‌فرض دسترسی به پنل کاربری
            // اگر کاربر عادی همزمان استف هم باشد (is_staff = 1 در جدول users)
            // و بخواهیم دسترسی کنترل سرور برایش فعال شود، باید permissions مربوط به رکورد staff-manage او چک شود.
            // این سناریو پیچیده‌تر است و فعلا فرض می‌کنیم کاربر یا "user" است یا "staff".
            $_SESSION['can_access_server_control'] = false; // کاربران عادی به طور پیش‌فرض دسترسی ندارند

        } elseif ($user_info['type'] === 'staff') {
            $_SESSION['is_owner'] = 0; // استف‌ها مدیر اصلی سیستم SSO نیستند
            $_SESSION['has_user_panel'] = $user_info['has_user_panel'] ?? 0; // استف‌ها به طور پیش‌فرض به پنل کاربری عادی دسترسی ندارند
            $_SESSION['staff_record_id'] = $user_info['staff_record_id']; // ID رکورد در staff-manage
            $_SESSION['is_verify'] = $user_info['is_verify'] ?? 0;
            $_SESSION['permissions'] = $user_info['permissions'] ?? ''; // از دیتابیس می‌آید
            $_SESSION['last_login_staff'] = $user_info['last_login'] ?? 'نامشخص'; // از auth_functions فرمت شده می‌آید

            // ***** بررسی دسترسی به کنترل سرور برای استف *****
            $staff_actual_permissions = strtolower(trim($user_info['permissions'] ?? ''));
            $_SESSION['can_access_server_control'] = false; // پیش‌فرض عدم دسترسی

            if ($staff_actual_permissions === 'dev') { // یا هر مقدار دیگری که برای این دسترسی تعریف کرده‌اید
                 $_SESSION['can_access_server_control'] = true;
                 // برای دیباگ می‌توانید اینجا لاگ بزنید:
                 // error_log("User '{$username}' with permissions '{$staff_actual_permissions}' GRANTED server_control_access.");
            } else {
                 // برای دیباگ:
                 // error_log("User '{$username}' with permissions '{$staff_actual_permissions}' DENIED server_control_access.");
            }
        }

        // ایجاد توکن دسترسی (بخش کد شما)
        $tokenData = [
            'user_id' => $_SESSION['user_id'], // استفاده از user_id که همیشه به users.id اشاره دارد (اگر auth_functions مطابق آخرین اصلاحات باشد)
            'username' => $_SESSION['username'],
            'is_owner' => $_SESSION['is_owner'] ?? 0,
            'is_staff' => $_SESSION['is_staff'] ?? 0,
            'user_type' => $_SESSION['user_type'],
            'created_at' => time(),
            'expires_at' => time() + 3600, // اعتبار برای 1 ساعت
            'ip' => $_SERVER['REMOTE_ADDR']
        ];
        if ($user_info['type'] === 'staff') { // اضافه کردن اطلاعات خاص استف به توکن اگر لازم است
            $tokenData['staff_record_id'] = $_SESSION['staff_record_id'];
            $tokenData['can_access_server_control'] = $_SESSION['can_access_server_control'];
        }

        $token = generateSsoToken($tokenData); // تابع از auth_functions.php

        // تنظیم کوکی
        setcookie('sso_token', $token, [
            'expires' => $tokenData['expires_at'],
            'path' => '/',
            'domain' => '.itziliya-dev.ir', // مطمئن شوید با دامنه شما یکی است
            'secure' => true, // در لوکال هاست بدون HTTPS، false کنید
            'httponly' => true,
            'samesite' => 'Lax'
        ]);

        // هدایت به داشبورد
        header('Location: dashboard.php');
        exit();
    }

    // اگر درخواست POST نبود (مثلاً کاربر مستقیم به auth.php رفته)
    header('Location: login.php');
    exit();

} catch (Exception $e) {
    error_log('SSO Auth Error: ' . $e->getMessage() . ' - User: ' . ($_POST['username'] ?? 'N/A'));
    // به کاربر یک پیام خطای عمومی نشان دهید و جزئیات را لاگ کنید
    header('Location: login.php?error=' . urlencode($e->getMessage()));
    exit();
}
?>