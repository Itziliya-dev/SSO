<?php
define('APP_START_TIME', microtime(true));
// ----------- بخش منطق PHP (نسخه کامل و جدید) -----------
require_once __DIR__.'/../includes/config.php';
require_once __DIR__.'/../includes/auth_functions.php';
require_once __DIR__.'/../includes/database.php';
require_once __DIR__.'/../includes/header.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (empty($_SESSION['permissions']['is_owner']) || empty($_SESSION['permissions']['can_manage_settings'])) {
    header('Location: /Dashboard/dashboard.php');
    exit();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

function handleImageUpload($file_input_name, $target_base_name, $upload_dir, $target_format = 'jpeg') {
    if (isset($_FILES[$file_input_name]) && $_FILES[$file_input_name]['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES[$file_input_name];

        // --- اعتبارسنجی اولیه ---
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $file_info = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($file_info, $file['tmp_name']);
        finfo_close($file_info);

        if (!in_array($mime_type, $allowed_types)) {
            return "خطا: فرمت فایل '$file_input_name' مجاز نیست.";
        }
        
        // --- بارگذاری تصویر در حافظه بر اساس نوع آن ---
        switch ($mime_type) {
            case 'image/jpeg':
                $source_image = imagecreatefromjpeg($file['tmp_name']);
                break;
            case 'image/png':
                $source_image = imagecreatefrompng($file['tmp_name']);
                break;
            case 'image/gif':
                $source_image = imagecreatefromgif($file['tmp_name']);
                break;
            case 'image/webp':
                $source_image = imagecreatefromwebp($file['tmp_name']);
                break;
            default:
                return "خطا در پردازش تصویر.";
        }

        if (!$source_image) {
            return "خطا: فایل تصویر قابل خواندن نیست.";
        }

        // --- پاک کردن فایل‌های قدیمی و تعیین مسیر جدید ---
        $old_files = glob($upload_dir . '/' . $target_base_name . '.*');
        foreach ($old_files as $old_file) {
            unlink($old_file);
        }
        
        $new_file_path = $upload_dir . '/' . $target_base_name . '.' . $target_format;

        // --- ذخیره فایل با فرمت جدید ---
        $success = false;
        if ($target_format === 'jpeg' || $target_format === 'jpg') {
            $success = imagejpeg($source_image, $new_file_path, 90); // 90 کیفیت تصویر است
        } elseif ($target_format === 'png') {
            imagealphablending($source_image, false); // برای حفظ شفافیت
            imagesavealpha($source_image, true);
            $success = imagepng($source_image, $new_file_path);
        }

        imagedestroy($source_image); // پاک کردن تصویر از حافظه

        if ($success) {
            return "فایل '$target_base_name' با موفقیت آپلود و تبدیل شد.";
        } else {
            return "خطا در ذخیره فایل '$target_base_name'.";
        }
    }
    return null;
}

$conn = getDbConnection();
$message = '';
$message_type = '';
$active_tab = 'notifications'; // تب پیش‌فرض

// پردازش فرم در صورت ارسال
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $message = "خطای امنیتی: درخواست نامعتبر است.";
        $message_type = 'error';
    } else {
        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("UPDATE `settings` SET `setting_value` = ? WHERE `setting_key` = ?");

            // شناسایی فرم ارسالی و تعیین کلیدهای مربوط به آن
            if (isset($_POST['form_type']) && $_POST['form_type'] === 'notifications') {
                $active_tab = 'notifications';

                $upload_dir = dirname(__DIR__) . '/assets/images';
                if (!is_dir($upload_dir) || !is_writable($upload_dir)) {
                    $message = "خطا: دایرکتوری آپلود وجود ندارد یا قابل نوشتن نیست.";
                    $message_type = 'error';
                } else {
                    // برای پس‌زمینه، به JPG تبدیل می‌کنیم
                    $bg_msg = handleImageUpload('background_image', 'background', $upload_dir, 'jpg');
                    if ($bg_msg) $upload_messages[] = $bg_msg;

                    // برای لوگو، به PNG تبدیل می‌کنیم تا شفافیت حفظ شود
                    $logo_msg = handleImageUpload('logo_image', 'logo', $upload_dir, 'png');
                    if ($logo_msg) $upload_messages[] = $logo_msg;
                    
                    if (!empty($upload_messages)) {
                        $message .= ' ' . implode(' ', $upload_messages);
                    }
                }
                $settings_to_update = [
                    'login_notice_text'    => $_POST['login_notice_text'] ?? '',
                    'login_notice_enabled' => isset($_POST['login_notice_enabled']) ? '1' : '0',
                    'login_notice_expiry'  => !empty($_POST['login_notice_expiry']) ? $_POST['login_notice_expiry'] : null
                ];
            } elseif (isset($_POST['form_type']) && $_POST['form_type'] === 'config') {
                $active_tab = 'config';
                $settings_to_update = [
                    'app_base_url' => $_POST['app_base_url'] ?? '',
                    'app_panel_url' => $_POST['app_panel_url'] ?? '',
                    'app_admin_panel_url' => $_POST['app_admin_panel_url'] ?? '',
                    'app_token_dir' => $_POST['app_token_dir'] ?? '',
                    'pterodactyl_url' => $_POST['pterodactyl_url'] ?? '',
                    'pterodactyl_api_key_client' => $_POST['pterodactyl_api_key_client'] ?? '',
                    'pterodactyl_api_key_application' => $_POST['pterodactyl_api_key_application'] ?? '',
                    'pterodactyl_server_id' => $_POST['pterodactyl_server_id'] ?? ''
                ];
            }

            // اجرای آپدیت برای کلیدهای مشخص شده
            foreach ($settings_to_update as $key => $value) {
                $stmt->bind_param('ss', $value, $key);
                $stmt->execute();
            }
            
            $stmt->close();
            $conn->commit();
            $message = "تنظیمات با موفقیت ذخیره شد.";
            $message_type = 'success';
        } catch (Exception $e) {
            $conn->rollback();
            $message = "خطا در ذخیره تنظیمات: " . $e->getMessage();
            $message_type = 'error';
        }
    }
}


$dev_info = [];
$dev_info['os_info'] = php_uname('a');
$dev_info['php_version'] = phpversion();
$dev_info['server_software'] = $_SERVER['SERVER_SOFTWARE'] ?? 'N/A';
$dev_info['project_root'] = dirname(__DIR__);
$server_software_lower = strtolower($dev_info['server_software']);
if (strpos($server_software_lower, 'apache') !== false) { $dev_info['web_server_type'] = 'Apache'; }
elseif (strpos($server_software_lower, 'nginx') !== false) { $dev_info['web_server_type'] = 'Nginx'; }
elseif (strpos($server_software_lower, 'litespeed') !== false) { $dev_info['web_server_type'] = 'LiteSpeed'; }
else { $dev_info['web_server_type'] = 'ناشناخته'; }
$dev_info['config_hints'] = [];
if (strpos(strtolower(php_uname('s')), 'linux') !== false) {
    if ($dev_info['web_server_type'] === 'Apache') {
        $dev_info['config_hints'][] = '/etc/apache2/sites-available/';
        $dev_info['config_hints'][] = '/etc/httpd/conf.d/';
    } elseif ($dev_info['web_server_type'] === 'Nginx') {
        $dev_info['config_hints'][] = '/etc/nginx/sites-available/';
    }
}
$dev_info['db_server_version'] = $conn->server_info;
$dev_info['db_connection_charset'] = $conn->character_set_name();

// ==========================================================
//               بخش جدید: جمع‌آوری اطلاعات سلامت سایت
// ==========================================================
function get_server_load() {
    if (function_exists('sys_getloadavg')) {
        $load = sys_getloadavg();
        return round($load[0], 2);
    }
    return 'N/A';
}

function get_disk_usage() {
    $total_space = disk_total_space(__DIR__);
    $free_space = disk_free_space(__DIR__);
    $used_space = $total_space - $free_space;
    $used_percent = round(($used_space / $total_space) * 100, 2);
    return [
        'total' => round($total_space / (1024**3), 2) . ' GB',
        'used_percent' => $used_percent,
    ];
}

$site_health = [];

// ۱. بررسی اتصال به دیتابیس
try {
    getDbConnection();
    $site_health['database'] = ['status' => 'good', 'message' => 'اتصال برقرار است'];
} catch (Exception $e) {
    $site_health['database'] = ['status' => 'error', 'message' => 'اتصال برقرار نیست!'];
}

// ۲. بررسی نسخه PHP
if (version_compare(phpversion(), '8.3', '>=')) {
    $site_health['php_version'] = ['status' => 'good', 'message' => 'نسخه ' . phpversion()];
} else {
    $site_health['php_version'] = ['status' => 'warning', 'message' => 'نسخه PHP پایین است (' . phpversion() . ')'];
}

// ۳. بررسی لود سرور (فقط برای لینوکس)
$load = get_server_load();
if ($load !== 'N/A') {
    if ($load > 2) {
        $site_health['server_load'] = ['status' => 'warning', 'message' => "لود بالا است ({$load})"];
    } else {
        $site_health['server_load'] = ['status' => 'good', 'message' => "لود نرمال است ({$load})"];
    }
}

// ۴. بررسی فضای دیسک
$disk = get_disk_usage();
if ($disk['used_percent'] > 85) {
    $site_health['disk_usage'] = ['status' => 'error', 'message' => "فضا رو به اتمام است ({$disk['used_percent']}%)"];
} else {
    $site_health['disk_usage'] = ['status' => 'good', 'message' => "{$disk['used_percent']}% از {$disk['total']} استفاده شده"];
}

// ۵. بررسی فعال بودن حالت دیباگ (برای امنیت)
// شما باید ثابت خود را برای دیباگ در اینجا قرار دهید
if (defined('APP_DEBUG') && APP_DEBUG) { 
    $site_health['debug_mode'] = ['status' => 'warning', 'message' => 'حالت دیباگ فعال است'];
} else {
    $site_health['debug_mode'] = ['status' => 'good', 'message' => 'حالت دیباگ غیرفعال است'];
}
// ... (کدهای قبلی برای بررسی فضای دیسک و حالت دیباگ) ...

// ۶. محاسبه سرعت اتصال به دیتابیس
$db_start_time = microtime(true);
getDbConnection(); // فقط برای تست اتصال
$db_connection_time = round((microtime(true) - $db_start_time) * 1000); // به میلی‌ثانیه

if ($db_connection_time > 200) { // اگر بیشتر از 200 میلی‌ثانیه طول بکشد
    $site_health['db_speed'] = ['status' => 'warning', 'message' => "کند است ({$db_connection_time}ms)"];
} else {
    $site_health['db_speed'] = ['status' => 'good', 'message' => "سریع است ({$db_connection_time}ms)"];
}

// ۷. محاسبه سرعت لود صفحه (Server-Side)
$page_load_time = round((microtime(true) - APP_START_TIME) * 1000); // به میلی‌ثانیه

if ($page_load_time > 1000) { // اگر بیشتر از 1 ثانیه طول بکشد
    $site_health['page_load'] = ['status' => 'warning', 'message' => "کند است ({$page_load_time}ms)"];
} else {
    $site_health['page_load'] = ['status' => 'good', 'message' => "سریع است ({$page_load_time}ms)"];
}
$dev_info['composer_packages'] = [];
$installed_json_path = dirname(__DIR__) . '/vendor/composer/installed.json';

if (file_exists($installed_json_path)) {
    $installed_data = json_decode(file_get_contents($installed_json_path), true);
    // در نسخه‌های جدید Composer، پکیج‌ها داخل کلید 'packages' هستند
    $packages = $installed_data['packages'] ?? $installed_data;
    
    foreach ($packages as $package) {
        $dev_info['composer_packages'][] = [
            'name'        => $package['name'] ?? 'N/A',
            'version'     => $package['version'] ?? 'N/A',
            'description' => $package['description'] ?? 'No description provided.'
        ];
    }
}

// تشخیص نوع دیتابیس (MySQL یا MariaDB)
if (strpos(strtolower($dev_info['db_server_version']), 'mariadb') !== false) {
    $dev_info['db_type'] = 'MariaDB';
} else {
    $dev_info['db_type'] = 'MySQL';
}

// خواندن تمام تنظیمات از دیتابیس
$result = $conn->query("SELECT * FROM `settings`");
$settings = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
}

$currentPage = 'settings';
$pending_requests_count = $conn->query("SELECT COUNT(id) as count FROM `registration_requests` WHERE status = 'pending'")->fetch_assoc()['count'];
?>
    <title>تنظیمات | پنل مدیریت</title>
    
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="../assets/css/admin_dashboard_redesign.css">
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/assets/fonts/Vazirmatn-font-face.css">
<?php
?>

<div class="admin-layout">

    <?php include __DIR__.'/../includes/_sidebar.php'; ?>

    <main class="main-content">
        <header class="main-header">
            <h1 class="header-title"><i class="fas fa-cogs"></i> تنظیمات پنل</h1>
        </header>

        <div class="admin-card">
            <div class="tabs-nav">
                <button class="tab-link <?= $active_tab === 'notifications' ? 'active' : '' ?>" onclick="openTab(event, 'notifications')"><i class="fas fa-palette"></i> تنظیمات صفحات پنل</button>
                <button class="tab-link <?= $active_tab === 'config' ? 'active' : '' ?>" onclick="openTab(event, 'config')"><i class="fas fa-sliders-h"></i> پیکربندی اصلی</button>
                <button class="tab-link <?= $active_tab === 'developer' ? 'active' : '' ?>" onclick="openTab(event, 'developer')"><i class="fas fa-code"></i> راهنمای توسعه‌دهنده</button>
            </div>

            <?php if ($message): ?>
            <div class="notification <?= $message_type == 'success' ? 'success' : 'error' ?>" style="opacity:1; transform:none; margin-bottom:20px;">
                <span><?= htmlspecialchars($message) ?></span>
            </div>
            <?php endif; ?>

            <div id="notifications" class="tab-content <?= $active_tab === 'notifications' ? 'active' : '' ?>">
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                    <input type="hidden" name="form_type" value="notifications">
                    
                    <h3>تنظیمات alert صفحه لاگین</h3>
                    <div class="form-group">
                        <label for="login_notice_text">متن alert:</label>
                        <textarea id="login_notice_text" name="login_notice_text" class="form-control" rows="4"><?= htmlspecialchars($settings['login_notice_text'] ?? '') ?></textarea>
                    </div>
                    <div class="form-group">
                        <label for="login_notice_expiry">تاریخ انقضای alert:</label>
                        <input type="date" id="login_notice_expiry" name="login_notice_expiry" class="form-control" value="<?= htmlspecialchars($settings['login_notice_expiry'] ?? '') ?>">
                    </div>
                    <div class="checkbox-group">
                        <label>
                            <input type="checkbox" name="login_notice_enabled" value="1" <?= ($settings['login_notice_enabled'] ?? '0') == '1' ? 'checked' : '' ?>>
                            فعال باشد
                        </label>
                    </div>

<hr style="margin: 30px 0;">

<h3><i class="fas fa-image"></i> آپلود تصاویر</h3>
<div class="upload-section">
    <div class="file-upload-wrapper">
        <label>عکس پس‌زمینه صفحه لاگین</label>
        <div class="image-preview" style="background-image: url('../assets/images/background.jpg?v=<?= time() ?>');"></div>
        <div class="file-input-container">
            <input type="file" id="background_image" name="background_image" class="custom-file-input" accept="image/*">
            <label for="background_image" class="file-input-label">
                <i class="fas fa-upload"></i>
                <span>انتخاب فایل جدید...</span>
            </label>
            <span class="file-name-display">هیچ فایلی انتخاب نشده</span>
        </div>
        <small class="form-hint">فایل جدید با نام `background.jpg` ذخیره می‌شود.</small>
    </div>

    <div class="file-upload-wrapper">
        <label>لوگوی صفحه لاگین</label>
        <div class="image-preview" style="background-image: url('../assets/images/logo.png?v=<?= time() ?>'); background-size: contain; background-repeat: no-repeat; background-position: center;"></div>
        <div class="file-input-container">
            <input type="file" id="logo_image" name="logo_image" class="custom-file-input" accept="image/*">
            <label for="logo_image" class="file-input-label">
                <i class="fas fa-upload"></i>
                <span>انتخاب فایل جدید...</span>
            </label>
            <span class="file-name-display">هیچ فایلی انتخاب نشده</span>
        </div>
        <small class="form-hint">فایل جدید با نام `logo.png` ذخیره می‌شود.</small>
    </div>
</div>

<div class="progress-container" style="display: none;">
    <div class="progress-bar"></div>
</div>
<button type="submit" class="submit-btn"><i class="fas fa-save"></i> ذخیره تنظیمات این تب</button>
                </form>
            </div>

<div id="config" class="tab-content <?= $active_tab === 'config' ? 'active' : '' ?>">
    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
        <input type="hidden" name="form_type" value="config">
        
        <div class="dev-info-grid">
            <div class="info-block full-width">
                <h4 class="info-block-title"><i class="fas fa-link"></i> آدرس‌های برنامه (URLs)</h4>
                
                <div class="form-group">
                    <label for="app_base_url">آدرس پایه (BASE_URL):</label>
                    <input type="text" id="app_base_url" name="app_base_url" class="form-control" value="<?= htmlspecialchars($settings['app_base_url'] ?? '') ?>">
                    <small class="form-hint">آدرس اصلی که پنل روی آن نصب شده است.</small>
                </div>
                
                <div class="form-group">
                    <label for="app_panel_url">آدرس پنل کاربری (PANEL_URL):</label>
                    <input type="text" id="app_panel_url" name="app_panel_url" class="form-control" value="<?= htmlspecialchars($settings['app_panel_url'] ?? '') ?>">
                    <small class="form-hint">آدرس کامل صفحه داشبورد کاربران.</small>
                </div>

                <div class="form-group">
                    <label for="app_admin_panel_url">آدرس پنل ادمین (ADMIN_PANEL_URL):</label>
                    <input type="text" id="app_admin_panel_url" name="app_admin_panel_url" class="form-control" value="<?= htmlspecialchars($settings['app_admin_panel_url'] ?? '') ?>">
                </div>
            </div>

            <div class="info-block full-width">
                <h4 class="info-block-title"><i class="fas fa-server"></i> اتصال به Pterodactyl</h4>
                
                <div class="form-group">
                    <label for="pterodactyl_url">آدرس پنل Pterodactyl:</label>
                    <input type="text" id="pterodactyl_url" name="pterodactyl_url" class="form-control" value="<?= htmlspecialchars($settings['pterodactyl_url'] ?? '') ?>" placeholder="e.g., https://panel.yourdomain.com">
                </div>
                
                <div class="form-group">
                    <label for="pterodactyl_api_key_client">کلید API کلاینت:</label>
                    <input type="password" id="pterodactyl_api_key_client" name="pterodactyl_api_key_client" class="form-control" value="<?= htmlspecialchars($settings['pterodactyl_api_key_client'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label for="pterodactyl_api_key_application">کلید API اپلیکیشن:</label>
                    <input type="password" id="pterodactyl_api_key_application" name="pterodactyl_api_key_application" class="form-control" value="<?= htmlspecialchars($settings['pterodactyl_api_key_application'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label for="pterodactyl_server_id">آیدی سرور:</label>
                    <input type="text" id="pterodactyl_server_id" name="pterodactyl_server_id" class="form-control" value="<?= htmlspecialchars($settings['pterodactyl_server_id'] ?? '') ?>">
                </div>
            </div>
        </div>

        <button type="submit" class="submit-btn" style="margin-top: 25px;"><i class="fas fa-save"></i> ذخیره پیکربندی</button>
    </form>
</div>

            <div id="developer" class="tab-content <?= $active_tab === 'developer' ? 'active' : '' ?>">
                <div class="dev-info-grid">
                    <div class="info-block">
                        <h4 class="info-block-title"><i class="fas fa-server"></i> Server Environment</h4>
                        <div class="info-row">
                            <label>Operating System:</label>
                            <div class="info-value code-font"><?= htmlspecialchars($dev_info['os_info']) ?></div>
                        </div>
                        <div class="info-row">
                            <label>Web Server:</label>
                            <div class="info-value code-font"><?= htmlspecialchars($dev_info['server_software']) ?></div>
                        </div>
                        <div class="info-row">
                            <label>PHP Version:</label>
                            <div class="info-value code-font"><?= htmlspecialchars($dev_info['php_version']) ?></div>
                        </div>
                    </div>

                    <div class="info-block">
                        <h4 class="info-block-title"><i class="fas fa-database"></i> Database</h4>
                        <div class="info-row">
                            <label>DB Type:</label>
                            <div class="info-value"><?= htmlspecialchars($dev_info['db_type']) ?></div>
                        </div>
                        <div class="info-row">
                            <label>DB Version:</label>
                            <div class="info-value code-font"><?= htmlspecialchars($dev_info['db_server_version']) ?></div>
                        </div>
                        <div class="info-row">
                            <label>Connection Charset:</label>
                            <div class="info-value code-font"><?= htmlspecialchars($dev_info['db_connection_charset']) ?></div>
                        </div>
                    </div>
                    <div class="info-block full-width">
    <h4 class="info-block-title"><i class="fas fa-heartbeat"></i> وضعیت سلامت پنل</h4>
    <div class="health-status-grid">
        
        <div class="health-item">
            <div class="health-item-header">
                <i class="fas fa-database status-icon-<?= $site_health['database']['status'] ?>"></i>
                <span>اتصال دیتابیس</span>
            </div>
            <span class="health-item-message"><?= $site_health['database']['message'] ?></span>
        </div>

        <div class="health-item">
            <div class="health-item-header">
                <i class="fab fa-php status-icon-<?= $site_health['php_version']['status'] ?>"></i>
                <span>نسخه PHP</span>
            </div>
            <span class="health-item-message"><?= $site_health['php_version']['message'] ?></span>
        </div>

        <?php if (isset($site_health['server_load'])): ?>
        <div class="health-item">
            <div class="health-item-header">
                <i class="fas fa-tachometer-alt status-icon-<?= $site_health['server_load']['status'] ?>"></i>
                <span>لود سرور</span>
            </div>
            <span class="health-item-message"><?= $site_health['server_load']['message'] ?></span>
        </div>
        <?php endif; ?>

        <div class="health-item">
            <div class="health-item-header">
                <i class="fas fa-hdd status-icon-<?= $site_health['disk_usage']['status'] ?>"></i>
                <span>فضای دیسک</span>
            </div>
            <span class="health-item-message"><?= $site_health['disk_usage']['message'] ?></span>
        </div>

        <div class="health-item">
            <div class="health-item-header">
                <i class="fas fa-bug status-icon-<?= $site_health['debug_mode']['status'] ?>"></i>
                <span>حالت دیباگ</span>
            </div>
            <span class="health-item-message"><?= $site_health['debug_mode']['message'] ?></span>
        </div>
        <div class="health-item">
            <div class="health-item-header">
                <i class="fas fa-bolt status-icon-<?= $site_health['db_speed']['status'] ?>"></i>
                <span>سرعت دیتابیس</span>
            </div>
            <span class="health-item-message"><?= $site_health['db_speed']['message'] ?></span>
        </div>

        <div class="health-item">
            <div class="health-item-header">
                <i class="fas fa-rocket status-icon-<?= $site_health['page_load']['status'] ?>"></i>
                <span>سرعت لود پنل</span>
            </div>
            <span class="health-item-message"><?= $site_health['page_load']['message'] ?></span>
        </div>

    </div>
</div>
<div class="info-block full-width">
            <h4 class="info-block-title"><i class="fab fa-php"></i> Composer Packages</h4>
            <div class="table-container" style="max-height: 400px;">
                <table class="user-table">
                    <thead>
                        <tr>
                            <th style="text-align: left;">Package Name</th>
                            <th style="text-align: center;">Version</th>
                            <th style="text-align: left;">Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($dev_info['composer_packages'])): ?>
                            <?php foreach ($dev_info['composer_packages'] as $package): ?>
                                <tr>
                                    <td class="code-font" style="text-align: left;"><?= htmlspecialchars($package['name']) ?></td>
                                    <td style="text-align: center;"><span class="permission-badge owner-access"><?= htmlspecialchars($package['version']) ?></span></td>
                                    <td style="text-align: left; white-space: normal; line-height: 1.4;"><?= htmlspecialchars($package['description']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="3" style="text-align: center;">اطلاعات پکیج‌های Composer یافت نشد.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
                    <div class="info-block full-width">
                        <h4 class="info-block-title"><i class="fas fa-folder"></i> Project & Config Paths</h4>
                        <div class="info-row">
                            <label>Project Root:</label>
                            <div class="info-value code-font"><?= htmlspecialchars($dev_info['project_root']) ?></div>
                        </div>
                        <div class="info-row">
                            <label>Standard Web Server Config Dirs:</label>
                            <div class="info-value">
                                <ul>
                                    <?php if (!empty($dev_info['config_hints'])): ?>
                                        <?php foreach ($dev_info['config_hints'] as $hint): ?>
                                            <li class="code-font"><?= htmlspecialchars($hint) ?></li>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <li>Not detected for this OS.</li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                        </div>
                    </div>
                    
                    <div class="info-block full-width">
                        <h4 class="info-block-title"><i class="fas fa-info-circle"></i> Developer Info</h4>
                        <div class="info-row">
                            <label>Developer:</label>
                            <div class="info-value">Itz_iliya32</div>
                        </div>
                        <div class="info-row">
                            <label>Telegram ID:</label>
                            <div class="info-value"><a href="https://t.me/Itz_iliya32" target="_blank">@Itz_iliya32</a></div>
                        </div>
                        <div class="info-row">
                            <label>GitHub Repository:</label>
                            <div class="info-value code-font"><a href="https://github.com/ItzEliya234/SSO" target="_blank">https://github.com/ItzEliya234/SSO</a></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<script>
function openTab(evt, tabName) {
    var i, tabcontent, tablinks;
    tabcontent = document.getElementsByClassName("tab-content");
    for (i = 0; i < tabcontent.length; i++) {
        tabcontent[i].style.display = "none";
    }
    tablinks = document.getElementsByClassName("tab-link");
    for (i = 0; i < tablinks.length; i++) {
        tablinks[i].className = tablinks[i].className.replace(" active", "");
    }
    document.getElementById(tabName).style.display = "block";
    evt.currentTarget.className += " active";
}

document.addEventListener("DOMContentLoaded", function() {
    const activeTabButton = document.querySelector('.tab-link.active');
    if (activeTabButton) {
        // اگر تبی از سمت سرور فعال شده بود (بعد از ارسال فرم)، آن را باز کن
        activeTabButton.click();
    } else {
        // در غیر این صورت، اولین تب را به عنوان پیش‌فرض باز کن
        document.querySelector('.tab-link').click();
    }
    const fileInputs = document.querySelectorAll('.custom-file-input');
    fileInputs.forEach(function(input) {
        input.addEventListener('change', function(e) {
            const fileName = e.target.files.length > 0 ? e.target.files[0].name : 'هیچ فایلی انتخاب نشده';
            // پیدا کردن نزدیک‌ترین والد که شامل span نمایش نام است
            const container = this.closest('.file-input-container');
            if (container) {
                const displaySpan = container.querySelector('.file-name-display');
                if (displaySpan) {
                    displaySpan.textContent = fileName;
                }
            }
        });
    });
});
</script>
<script>
// این اسکریپت فقط برای فرمی که فایل آپلود می‌کند، اجرا می‌شود
const uploadForm = document.querySelector('#notifications form');

if (uploadForm) {
    uploadForm.addEventListener('submit', function(e) {
        e.preventDefault(); // جلوگیری از ارسال عادی فرم

        // --- بخش جدید: بررسی وجود فایل برای آپلود ---
        const backgroundInput = document.getElementById('background_image');
        const logoInput = document.getElementById('logo_image');
        // اگر در هر کدام از این دو input فایلی انتخاب شده باشد، این متغیر true می‌شود
        const imageWasUploaded = (backgroundInput.files.length > 0 || logoInput.files.length > 0);
        // ---------------------------------------------

        const formData = new FormData(this);
        const xhr = new XMLHttpRequest();
        
        const progressContainer = this.querySelector('.progress-container');
        const progressBar = this.querySelector('.progress-bar');
        
        // فقط اگر فایلی برای آپلود وجود دارد، نوار پیشرفت را نمایش بده
        if (imageWasUploaded) {
            progressContainer.style.display = 'block';
            progressBar.style.width = '0%';
            progressBar.textContent = '0%';
        }

        // رویداد برای پیگیری پیشرفت آپلود
        xhr.upload.addEventListener('progress', function(event) {
            if (event.lengthComputable && imageWasUploaded) {
                const percentComplete = Math.round((event.loaded / event.total) * 100);
                progressBar.style.width = percentComplete + '%';
                progressBar.textContent = percentComplete + '%';
            }
        });

        // رویداد برای پایان موفقیت‌آمیز آپلود
        xhr.addEventListener('load', function() {
            if (imageWasUploaded) {
                progressBar.style.width = '100%';
                progressBar.textContent = 'پردازش...';
            }
            
            // --- بخش کلیدی: تصمیم‌گیری برای نوع رفرش ---
            if (imageWasUploaded) {
                // اگر عکسی آپلود شده بود، کش را پاک کن (Hard Reload)
                location.reload(true); 
            } else {
                // در غیر این صورت، فقط یک رفرش عادی انجام بده
                location.reload(); 
            }
            // ------------------------------------------
        });
        
        // رویداد برای خطا
        xhr.addEventListener('error', function() {
             alert('خطایی در آپلود رخ داد. لطفا اتصال خود را بررسی کنید.');
             if(progressContainer) progressContainer.style.display = 'none';
        });

        xhr.open('POST', 'settings_page.php');
        xhr.send(formData);
    });
}
</script>
</body>
</html>