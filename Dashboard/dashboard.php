<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__.'/../includes/config.php';
require_once __DIR__.'/../includes/database.php';
require_once __DIR__.'/../includes/header.php';
session_start();

// تابع کمکی برای دریافت آواتار از بات دیسکورد
function get_discord_avatar_url_from_bot($discord_id) {
    if (empty($discord_id)) return null;
    // مقادیر زیر باید با تنظیمات بات شما هماهنگ باشد
    $webhook_url = 'http://83.149.95.39:1030/get-avatar-url?discord_id=' . urlencode($discord_id);
    $secret_token = '4a97dd86-4388-4cc0-a54f-65ebbf51649d';

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $webhook_url,
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_TIMEOUT => 5,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $secret_token]
    ]);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code == 200) {
        $data = json_decode($response, true);
        return $data['avatar_url'] ?? null;
    }
    return null;
}

// بررسی لاگین بودن کاربر
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php'); // اصلاح مسیر برای خروج از پوشه داشبورد
    exit();
}

// واکشی اطلاعات اصلی از سشن
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'کاربر';
$user_type = $_SESSION['user_type'] ?? 'user';
$is_owner = $_SESSION['is_owner'] ?? false;
$is_staff = ($user_type === 'staff');

// مقادیر پیش‌فرض
$final_avatar_src = '../assets/images/logo.png'; // آواتار پیش‌فرض با مسیر صحیح
$staff_permissions = 'فاقد مقام';
$staff_is_verify = 0;
$staff_last_login_formatted = 'نامشخص';
$discord_conn_status_key = 'unknown';
$has_services_to_show = false;


if ($is_staff) {
    $staff_permissions = $_SESSION['permissions'] ?? 'فاقد مقام';
    $staff_is_verify = $_SESSION['is_verify'] ?? 0;
    
    if (!empty($_SESSION['last_login_staff'])) {
        try {
            $staff_last_login_formatted = (new DateTime($_SESSION['last_login_staff']))->format('Y/m/d ساعت H:i');
        } catch (Exception $e) {
            $staff_last_login_formatted = 'تاریخ نامعتبر';
        }
    }

    try {
        $conn = getDbConnection();
        $stmt = $conn->prepare("SELECT discord_conn, discord_id2 FROM `staff-manage` WHERE user_id = ? LIMIT 1");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($staff_info = $result->fetch_assoc()) {
            $discord_conn_status_key = ($staff_info['discord_conn'] == 1) ? 'connected' : 'not_connected';
            
            if (!empty($staff_info['discord_id2'])) {
                $discord_avatar_url = get_discord_avatar_url_from_bot($staff_info['discord_id2']);
                if ($discord_avatar_url) {
                    // مسیر صحیح پراکسی با توجه به ساختار پوشه شما
                    $final_avatar_src = '../admin/image_proxy.php?url=' . urlencode($discord_avatar_url);
                }
            }
        }
        $stmt->close();
    } catch (Exception $e) {
        error_log("DB Error fetching staff data: " . $e->getMessage());
    }
}
?>
    <title>داشبورد | SSO Center</title>
    <link rel="stylesheet" href="../assets/css/dashboard_premium.css">
    <link rel="stylesheet" href="/assets/fonts/Vazirmatn-font-face.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
<?php

?>

    <header class="dashboard-header">
        <a href="dashboard.php" class="header-logo">
            <img src="/../assets/images/logo.png" alt="Logo">
            <span>SSO Center</span>
        </a>
        <div class="user-menu">
            <button class="user-menu-button">
                <img src="<?= htmlspecialchars($final_avatar_src) ?>" alt="Avatar">
                <span><?= htmlspecialchars($username) ?></span>
                <i class="fas fa-chevron-down"></i>
            </button>
            <div class="user-menu-dropdown">
                <a href="profile.php"><i class="fas fa-user-edit"></i> ویرایش پروفایل</a>
                <?php if ($is_owner): ?>
                    <a href="../admin/admin_panel.php"><i class="fas fa-user-shield"></i> پنل مدیریت</a>
                <?php endif; ?>
                <a href="logout.php"><i class="fas fa-sign-out-alt"></i> خروج از حساب</a>
            </div>
        </div>
    </header>

    <div class="dashboard-container">
        <aside class="user-status-card">
            <img src="<?= htmlspecialchars($final_avatar_src) ?>" alt="User Avatar" class="user-avatar">
            <h2><?= htmlspecialchars($username) ?></h2>
            <p><?= $is_staff ? 'حساب کاربری استف' : 'حساب کاربری' ?></p>
            
            <div class="status-grid">
                <?php if ($is_staff): ?>
                    <div class="status-item">
                        <span class="label"><i class="fas fa-user-tag"></i> مقام</span>
                        <span class="value"><?= htmlspecialchars($staff_permissions) ?></span>
                    </div>
                    <div class="status-item">
                        <span class="label"><i class="fas fa-shield-alt"></i> وضعیت</span>
                        <span class="value <?= $staff_is_verify ? 'status-verified' : 'status-not-verified' ?>">
                            <?= $staff_is_verify ? 'تایید شده' : 'در انتظار تایید' ?>
                        </span>
                    </div>
                     <div class="status-item">
                        <span class="label"><i class="fas fa-history"></i> آخرین ورود</span>
                        <span class="value"><?= htmlspecialchars($staff_last_login_formatted) ?></span>
                    </div>
                     <div class="status-item">
                        <span class="label"><i class="fab fa-discord"></i> دیسکورد</span>
                        <span class="value <?= $discord_conn_status_key === 'connected' ? 'status-verified' : 'status-not-verified' ?>">
                            <?= $discord_conn_status_key === 'connected' ? 'متصل' : 'متصل نشده' ?>
                        </span>
                    </div>
                <?php else: ?>
                     <div class="status-item">
                        <span class="label"><i class="fas fa-check-circle"></i> وضعیت</span>
                        <span class="value status-verified">فعال</span>
                    </div>
                <?php endif; ?>
            </div>
        </aside>

        <main class="main-content-area">
            
            <div class="action-box <?= ($is_staff && !$staff_is_verify) ? 'unverified' : 'verified' ?>">
                <h1>خوش آمدید، <?= htmlspecialchars($username) ?>!</h1>
                <?php if ($is_staff && !$staff_is_verify): ?>
                    <p>حساب کاربری استف شما هنوز توسط مدیریت تایید نشده است. تا زمان تایید، دسترسی شما به خدمات ویژه استف محدود خواهد بود. لطفاً منتظر بمانید.</p>
                <?php elseif ($is_staff && $discord_conn_status_key !== 'connected'): ?>
                     <p>برای استفاده از تمامی امکانات و ربات‌های مجموعه، لازم است حساب دیسکورد خود را متصل کنید.</p>
                     <span class="discord-link">در سرور دیسکورد از دستور <code>/connect</code> استفاده کنید.</span>
                <?php else: ?>
                    <p>از این داشبورد می‌توانید به خدمات در دسترس خود دسترسی پیدا کنید. در صورت نیاز به راهنمایی با پشتیبانی تماس بگیرید.</p>
                <?php endif; ?>
            </div>

            <div class="services-container">
                <h2><i class="fas fa-th-large"></i> خدمات شما</h2>
                <div class="service-cards-grid">
                    <?php if ($user_type === 'user'): $has_services_to_show = true; ?>
                        <a href="<?= htmlspecialchars(PANEL_URL) ?>" class="service-card user-panel-card" target="_blank">
                            <i class="fas fa-gamepad icon"></i>
                            <h3>پنل کاربری</h3>
                            <p>دسترسی به پنل مرکزی کنترل سرور ها.</p>
                            <span class="enter-service">ورود به پنل <i class="fas fa-arrow-left"></i></span>
                        </a>    
                    <?php endif; ?>
                        <a href="passkey_management.php" class="service-card user-panel-card">
                            <i class="fas fa-key icon"></i>
                            <h3> (استفاده نکنید فعلا) مدیریت Passkey</h3>
                            <p>ورود بدون رمز عبور با استفاده از اثر انگشت یا تشخیص چهره.</p>
                            <span class="enter-service">مدیریت کلیدها <i class="fas fa-arrow-left"></i></span>
                        </a>
                    <?php if ($is_owner): $has_services_to_show = true; ?>
                         <a href="../admin/admin_panel.php" class="service-card admin-panel-card">
                            <i class="fas fa-user-shield icon"></i>
                            <h3>پنل مدیریت SSO</h3>
                            <p>مدیریت کامل کاربران، دسترسی‌ها و تنظیمات کلی سیستم.</p>
                            <span class="enter-service">ورود به مدیریت <i class="fas fa-arrow-left"></i></span>
                        </a>
                    <?php endif; ?>
                    
                    <?php if ($is_staff && $staff_is_verify && strpos(strtolower($staff_permissions), 'dev') !== false): $has_services_to_show = true; ?>
                        <a href="/server_management/server_control.php" class="service-card staff-specific-btn">
                            <i class="fas fa-server icon"></i>
                            <h3>کنترل سرور</h3>
                            <p>دسترسی به ابزارهای مدیریتی و کنترل سرور (مخصوص DEV).</p>
                             <span class="enter-service">ورود به بخش <i class="fas fa-arrow-left"></i></span>
                        </a>
                    <?php endif; ?>

                    <?php if (!$has_services_to_show): ?>
                        <div class="no-services-message">
                            <i class="fas fa-box-open"></i>
                            <p>در حال حاضر سرویس فعالی برای شما وجود ندارد.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</body>
</html>