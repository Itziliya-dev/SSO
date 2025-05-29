<?php
// ===== تنظیمات اصلی =====
define('BASE_URL', 'https://sso.itziliya-dev.ir');
define('PANEL_URL', 'https://dev-panel.itziliya-dev.ir'); // این همان آدرس پنل Pterodactyl شماست؟ اگر بله، در PTERODACTYL_URL هم استفاده می‌شود.
define('ADMIN_PANEL_URL', 'https://sso.itziliya-dev.ir/admin');
define('TOKEN_DIR', '/tmp/sso_tokens');

// ===== تنظیمات Pterodactyl =====
// آدرس پنل Pterodactyl شما
// اگر PANEL_URL همان آدرس Pterodactyl است، می‌توانید از آن استفاده کنید:
// define('PTERODACTYL_URL', PANEL_URL);
// یا اگر متفاوت است، آدرس دقیق را وارد کنید:
if (!defined('PTERODACTYL_URL')) {
    define('PTERODACTYL_URL', 'https://dev-panel.itziliya-dev.ir');
}

// کلید API کلاینت Pterodactyl (با دسترسی لازم برای خواندن وضعیت و ارسال دستورات پاور)
if (!defined('PTERODACTYL_API_KEY_CLIENT')) {
    define('PTERODACTYL_API_KEY_CLIENT', 'ptlc_ee0ttDuN3xpzlUH6tcrpJNwwlxhgIrvgDAWf6HhxKCu');
}

// کلید API اپلیکیشن Pterodactyl (معمولاً برای عملیات مدیریتی گسترده‌تر استفاده می‌شود، ممکن است برای اینکار لازم نباشد)
if (!defined('PTERODACTYL_API_KEY_APPLICATION')) {
    define('PTERODACTYL_API_KEY_APPLICATION', 'ptla_1qYikLRI1WZKNjK2TnMe7fbl7tQkFt469PHeov3TbVm');
}

// شناسه سروری که می‌خواهید کنترل کنید
if (!defined('PTERODACTYL_SERVER_ID')) {
    define('PTERODACTYL_SERVER_ID', '5ba7039f-89ff-4475-a2cc-67ebf0b0ba1a');
}


// ===== تنظیمات امنیتی جلسات =====
// این تنظیمات بهتر است قبل از session_start() اعمال شوند.
// اگر session_start() در فایل دیگری قبل از include کردن این فایل فراخوانی می‌شود،
// این تنظیمات ممکن است اعمال نشوند. بهترین جا برای اینها در ابتدای اسکریپتی است که اولین بار سشن را شروع می‌کند.
if (session_status() === PHP_SESSION_NONE) { // فقط اگر سشن هنوز شروع نشده
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', 1);    // در محیط توسعه (localhost) اگر HTTPS ندارید، این را موقتا 0 کنید یا کامنت کنید
    ini_set('session.use_strict_mode', 1);
    // ini_set('session.cookie_samesite', 'Lax'); // یا 'Strict' برای امنیت بیشتر
}

// ===== 

// ===== فعال کردن نمایش خطاها (فقط برای محیط توسعه) =====
// در محیط پروداکشن (سرور اصلی) این بخش را کامنت یا حذف کنید و خطاها را در لاگ سرور بررسی کنید.
/*
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
*/
?>