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
    $conn = getDbConnection();
    $result = $conn->query("SELECT setting_key, setting_value FROM settings");
    $settings = [];
    while ($row = $result->fetch_assoc()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    // $conn->close();
} catch (Exception $e) {
    // اگر اتصال به دیتابیس برای خواندن تنظیمات شکست بخورد، برنامه متوقف می‌شود
    die("Database Connection Error: Could not fetch application settings. " . $e->getMessage());
}

// بخش سوم: تعریف ثابت‌ها بر اساس مقادیر خوانده شده از دیتابیس
// در صورت عدم وجود کلید در دیتابیس، یک مقدار پیش‌فرض استفاده می‌شود
define('BASE_URL', $settings['app_base_url'] ?? 'https://default.url');
define('PANEL_URL', $settings['app_panel_url'] ?? 'https://default.panel.url');
define('ADMIN_PANEL_URL', $settings['app_admin_panel_url'] ?? 'https://default.admin.url');
define('TOKEN_DIR', $settings['app_token_dir'] ?? '/tmp/sso_tokens');

define('PTERODACTYL_URL', $settings['pterodactyl_url'] ?? '');
define('PTERODACTYL_API_KEY_CLIENT', $settings['pterodactyl_api_key_client'] ?? '');
define('PTERODACTYL_API_KEY_APPLICATION', $settings['pterodactyl_api_key_application'] ?? '');
define('PTERODACTYL_SERVER_ID', $settings['pterodactyl_server_id'] ?? '');

// بخش چهارم: تنظیمات مربوط به سشن
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', 1); // در محیط لوکال این خط را false کنید
    ini_set('session.use_strict_mode', 1);
    ini_set('session.cookie_samesite', 'None');
}