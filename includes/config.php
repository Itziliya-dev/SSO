<?php
// فایل: config.php (نسخه جدید و دینامیک)

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/database.php'; // برای دسترسی به تابع getDbConnection

// بخش اول: بارگذاری متغیرهای محیطی برای اتصال به دیتابیس
try {
    $dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
    $dotenv->load();
} catch (\Dotenv\Exception\InvalidPathException $e) {
    die("Error: .env file not found. Please create one based on .env.example.");
}

// بخش دوم: خواندن تنظیمات از دیتابیس
try {
    $conn = getDbConnection(); // این تابع خودش در صورت خطا، کاربر را هدایت می‌کند
    $result = $conn->query("SELECT setting_key, setting_value FROM settings");
    if ($result === false) {
        // اگر کوئری با خطا مواجه شد (مثلاً جدول وجود نداشت)
        throw new Exception("Could not query settings table.");
    }
    $settings = [];
    while ($row = $result->fetch_assoc()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (Exception $e) {
    error_log("Config Read Error: " . $e->getMessage());
    // ✅ هدایت کاربر به صفحه خطای جدید
    header('Location: /database-error');
    exit();
}

// بخش سوم: تعریف ثابت‌ها بر اساس مقادیر خوانده شده از دیتابیس
// در صورت عدم وجود کلید در دیتابیس، یک مقدار پیش‌فرض استفاده می‌شود
define('BASE_URL', $settings['app_base_url'] ?? 'https://default.url');
define('PANEL_URL', $settings['app_panel_url'] ?? 'https://default.panel.url');
define('ADMIN_PANEL_URL', $settings['app_admin_panel_url'] ?? 'https://default.admin.url');

$default_token_dir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'sso_tokens';

define('PTERODACTYL_URL', $settings['pterodactyl_url'] ?? '');
define('PTERODACTYL_API_KEY_CLIENT', $settings['pterodactyl_api_key_client'] ?? '');
define('PTERODACTYL_API_KEY_APPLICATION', $settings['pterodactyl_api_key_application'] ?? '');
define('PTERODACTYL_SERVER_ID', $settings['pterodactyl_server_id'] ?? '');

// بخش چهارم: تنظیمات مربوط به سشن
// بخش چهارم: تنظیمات مربوط به سشن
if (session_status() === PHP_SESSION_NONE) {
    // ✅ کد جدید برای حل مشکل کوکی در محیط لوکال
    if (in_array($_SERVER['SERVER_NAME'], ['localhost', 'sso.local'])) {
        session_set_cookie_params([
            'lifetime' => 86400, // 24 hours
            'path' => '/',
            'domain' => '', // برای کار کردن روی هر دو دامنه محلی
            'secure' => false, // اجازه استفاده روی HTTP
            'httponly' => true,
            'samesite' => 'Lax' // استفاده از Lax به جای None برای سازگاری بیشتر
        ]);
    } else {
        // تنظیمات برای سرور اصلی (پروداکشن)
        ini_set('session.cookie_httponly', 1);
        ini_set('session.cookie_secure', 1); 
        ini_set('session.use_strict_mode', 1);
        ini_set('session.cookie_samesite', 'Lax'); // یا 'Strict' برای امنیت بیشتر
    }

    // و در نهایت، سشن را شروع کن
}