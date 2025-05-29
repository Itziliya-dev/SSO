<?php
require_once __DIR__.'/includes/config.php'; // اطمینان از وجود فایل و مسیر صحیح
session_start();

// بررسی اینکه کاربر وارد شده است یا خیر
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// خواندن متغیرهای عمومی از سشن
$username = $_SESSION['username'] ?? 'کاربر';
$user_type = $_SESSION['user_type'] ?? 'user'; // 'user' یا 'staff'

// متغیرهای پیش‌فرض و خواندن از سشن برای کاربر عادی
$is_owner = false;
$has_user_panel = ($user_type === 'user'); // پیش‌فرض برای کاربر عادی

// متغیرهای پیش‌فرض و خواندن از سشن برای استف
$is_staff = false;
$staff_permissions = 'فاقد مقام';
$staff_is_verify = 0; // 0: تایید نشده, 1: تایید شده
$staff_last_login_formatted = 'نامشخص';

// تنظیم متغیرها بر اساس نوع کاربر از سشن
if ($user_type === 'user') {
    $is_owner = $_SESSION['is_owner'] ?? false;
    $has_user_panel = $_SESSION['has_user_panel'] ?? true;
    $is_staff = $_SESSION['is_staff'] ?? 0; // اگر کاربر عادی، بتواند استف هم باشد
    // اگر کاربر عادی is_staff بود و نیاز به نمایش اطلاعات بیشتر داشت،
    // باید آن اطلاعات از دیتابیس خوانده یا در auth.php در سشن گذاشته شود.
} elseif ($user_type === 'staff') {
    $is_staff = true;
    $staff_permissions = $_SESSION['permissions'] ?? 'فاقد مقام';
    $staff_is_verify = $_SESSION['is_verify'] ?? 0;
    $staff_last_login_raw = $_SESSION['last_login_staff'] ?? null;

    if ($staff_last_login_raw) {
        try {
            // اطمینان از اینکه فرمت تاریخ از دیتابیس صحیح است
            $date = new DateTime($staff_last_login_raw);
            $staff_last_login_formatted = $date->format('Y/m/d ساعت H:i');
        } catch (Exception $e) {
            // اگر فرمت تاریخ مشکل داشت، همان مقدار خام را نمایش بده یا یک پیام خطا
            $staff_last_login_formatted = 'تاریخ نامعتبر';
            error_log("Error formatting staff last login date: " . $e->getMessage());
        }
    } else {
        $staff_last_login_formatted = 'اولین ورود یا نامشخص';
    }
}

// تعریف ثابت‌های URL (مطمئن شوید در config.php تعریف شده‌اند)
// اگر در config.php نیستند، اینجا به عنوان مثال تعریف می‌کنیم:
if (!defined('PANEL_URL')) {
    define('PANEL_URL', 'https://dev-panel.itziliya-dev.ir'); // آدرس پنل کاربری
}

?>
<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>داشبورد | SSO Center</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="preload" href="assets/images/background.jpg" as="image">
    <link rel="preload" href="assets/images/logo.png" as="image">

    <style>
        :root {
            --text-color: #e5e7eb; 
            --text-muted-color: #9ca3af; 
            --card-bg: rgba(31, 41, 55, 0.7); 
            --card-border: rgba(75, 85, 99, 0.5); 
            --primary-color: #3b82f6; 
            --primary-hover: #2563eb;
            --admin-color: #10b981; 
            --admin-hover: #059669;
            --verified-color: #34d399; 
            --not-verified-color: #f87171; 
            --font-family: 'Vazirmatn', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            --glass-bg: rgba(55, 65, 81, 0.5); 
            --glass-border: rgba(107, 114, 128, 0.3);
            --glass-shadow: 0 8px 32px rgba(0, 0, 0, 0.37);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: var(--font-family);
            color: var(--text-color);
            min-height: 100vh;
            overflow-x: hidden;
            position: relative;
            direction: rtl;
            background-color: #0a0a1a; 
            padding-top: 60px; 
        }

        .background-image {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: url('assets/images/background.jpg'); 
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            z-index: -1;
            filter: brightness(0.6) contrast(1.1); 
        }

        
        .dashboard-header {
            background: var(--glass-bg);
            backdrop-filter: blur(12px);
            padding: 0 25px; 
            height: 60px; 
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--glass-border);
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            z-index: 1000;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
        }

        .header-logo {
            display: flex;
            align-items: center;
            color: var(--text-color);
            text-decoration: none;
            transition: opacity 0.3s;
        }
        .header-logo:hover { opacity: 0.8; }
        .header-logo img {
            width: 38px; 
            height: 38px;
            margin-left: 12px; 
            border-radius: 50%;
            border: 1px solid rgba(255,255,255,0.2);
        }
        .header-logo span { font-size: 18px; font-weight: 600; }

        .user-menu { position: relative; }
        .user-menu-button {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: var(--text-color);
            padding: 8px 15px; 
            border-radius: 8px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: background-color 0.3s, box-shadow 0.3s;
        }
        .user-menu-button:hover { background: rgba(255, 255, 255, 0.1); box-shadow: 0 0 10px rgba(var(--primary-color-rgb), 0.3); }
        .user-menu-button .fa-chevron-down { font-size: 0.8em; transition: transform 0.3s; }
        .user-menu:hover .user-menu-button .fa-chevron-down { transform: rotate(180deg); }
        .user-menu-dropdown {
            display: none;
            position: absolute;
            left: 0; 
            top: 100%; 
            margin-top: 1px;
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-radius: 10px;
            width: 220px; 
            box-shadow: var(--glass-shadow);
            backdrop-filter: blur(12px);
            overflow: hidden;
            z-index: 1001;
        }
        .user-menu:hover .user-menu-dropdown { display: block; }
        .user-menu-dropdown a {
            display: flex; 
            align-items: center;
            padding: 12px 18px;
            color: var(--text-color);
            text-decoration: none;
            transition: background-color 0.3s, color 0.3s;
            font-size: 14px;
            text-align: right;
        }
        .user-menu-dropdown a i { margin-left: 10px; width: 16px;  }
        .user-menu-dropdown a:hover { background-color: var(--primary-color); color: #fff; }
        .user-menu-dropdown a:first-child { border-top-left-radius: 10px; border-top-right-radius: 10px; }
        .user-menu-dropdown a:last-child { border-bottom-left-radius: 10px; border-bottom-right-radius: 10px; }


        
        .staff-status-bar {
            background-color: rgba(17, 24, 39, 0.85); 
            backdrop-filter: blur(10px);
            color: #d1d5db;
            padding: 0 20px; 
            height: 48px; 
            font-size: 13.5px; 
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            position: fixed;
            top: 60px; 
            left: 0;
            width: 100%;
            z-index: 999;
            display: flex;
            justify-content: space-around;
            align-items: center;
            flex-wrap: wrap;
            box-shadow: 0 3px 8px rgba(0,0,0,0.25);
        }
        .staff-status-bar span { margin: 5px 12px; display: inline-flex; align-items: center;}
        .staff-status-bar .status-label { font-weight: 500; color: var(--text-muted-color); margin-left: 5px; }
        .staff-status-bar .status-value { font-weight: 600; color: var(--text-color); }
        .staff-status-bar .status-value .fas { margin-right: 6px;  font-size: 0.95em; }
        .staff-status-bar .status-verified { color: var(--verified-color); }
        .staff-status-bar .status-not-verified { color: var(--not-verified-color); }


        
        .dashboard-container {
            padding-top: calc(60px + 48px + 30px); 
            display: flex;
            justify-content: center;
            align-items: flex-start;
            min-height: 100vh;
            padding-bottom: 40px;
            padding-left: 20px; 
            padding-right: 20px; 
        }

        .dashboard-content {
            background: var(--card-bg);
            border-radius: 16px; 
            padding: 35px 30px; 
            box-shadow: 0 12px 35px rgba(0, 0, 0, 0.35);
            backdrop-filter: blur(15px);
            border: 1px solid var(--card-border);
            width: 100%; 
            max-width: 800px; 
            text-align: center;
            color: var(--text-color);
            animation: fadeInUp 0.7s ease-out forwards;
        }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(25px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .dashboard-content .logo-wrapper { margin-bottom: 25px; }
        .dashboard-content .logo-circle {
            width: 100px; height: 100px;
            margin: 0 auto 20px;
            background: rgba(255, 255, 255, 0.03);
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            border: 1px solid rgba(255,255,255,0.08);
        }
        .dashboard-content .logo { width: 50px; height: 50px; }

        .dashboard-content h1 { font-size: 26px; margin-bottom: 12px; color: #fff; font-weight: 600; }
        .dashboard-content p.welcome-text { font-size: 16px; margin-bottom: 35px; color: var(--text-muted-color); line-height: 1.7; }

        .services-section {
            border-top: 1px solid var(--card-border);
            padding-top: 30px;
            margin-top: 30px;
        }
        .services-section h2 {
            font-size: 20px; 
            font-weight: 600;
            margin-bottom: 25px;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        .services-section h2 .fas { color: var(--primary-color); }

        .service-buttons {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); 
            gap: 20px;
        }
        .service-btn {
            padding: 16px 20px; 
            border-radius: 10px; 
            color: #fff;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            font-size: 16px; 
            font-weight: 500;
            transition: transform 0.2s ease-out, background-color 0.3s, box-shadow 0.3s;
            border: 1px solid transparent; 
        }
        .service-btn .fas { font-size: 20px; margin-right: 5px; }

        .service-btn.user-panel-btn { 
            background: var(--primary-color);
            border-color: var(--primary-hover);
        }
        .service-btn.user-panel-btn:hover {
            background: var(--primary-hover);
            transform: translateY(-3px) scale(1.02);
            box-shadow: 0 8px 15px rgba(59, 130, 246, 0.3);
        }
        .service-btn.admin-panel-btn { 
            background: var(--admin-color);
            border-color: var(--admin-hover);
        }
        .service-btn.admin-panel-btn:hover {
            background: var(--admin-hover);
            transform: translateY(-3px) scale(1.02);
            box-shadow: 0 8px 15px rgba(16, 185, 129, 0.3);
        }
        .service-btn.staff-specific-btn { 
            background-color: #6366f1; 
            border-color: #4f46e5;
        }
         .service-btn.staff-specific-btn:hover {
            background-color: #4f46e5;
            transform: translateY(-3px) scale(1.02);
            box-shadow: 0 8px 15px rgba(99, 102, 241, 0.3);
        }


        .no-service {
            background: rgba(255, 193, 7, 0.08);
            color: #facc15; 
            padding: 25px;
            border-radius: 12px;
            border: 1px solid rgba(255, 193, 7, 0.15);
            margin-top: 20px;
            display: flex;
            flex-direction: column; 
            align-items: center;
            gap: 10px; 
        }
        .no-service .fas { font-size: 28px;  opacity: 0.7; }
        .no-service p { margin-bottom: 5px; font-size: 15px; line-height: 1.6; }


        
        @media (max-width: 768px) {
            body { padding-top: 50px; }
            .dashboard-header { height: 50px; padding: 0 15px;}
            .header-logo img { width: 30px; height: 30px; margin-left: 8px; }
            .header-logo span { font-size: 16px; }
            .user-menu-button { padding: 6px 10px; font-size: 13px; }

            .staff-status-bar {
                top: 50px; 
                height: auto; 
                padding: 8px 10px;
                font-size: 12px;
                justify-content: center; 
            }
             .staff-status-bar span { margin: 3px 8px;}


            .dashboard-container {
                padding-top: calc(50px + 60px + 20px); 
                padding-left: 10px;
                padding-right: 10px;
            }
            .dashboard-content { padding: 25px 20px; }
            .dashboard-content h1 { font-size: 22px; }
            .dashboard-content p.welcome-text { font-size: 14px; }
            .services-section h2 { font-size: 18px; }
            .service-buttons { grid-template-columns: 1fr;  }
            .service-btn { font-size: 15px; padding: 14px; }
        }
    </style>
</head>
<body>
    <div class="background-image"></div>

    <header class="dashboard-header">
        <a href="dashboard.php" class="header-logo">
            <img src="assets/images/logo.png" alt="Logo">
            <span>SSO Center</span>
        </a>
        <div class="user-menu">
            <button class="user-menu-button">
                <i class="fas fa-user-circle"></i>
                <span><?= htmlspecialchars($username) ?></span>
                <i class="fas fa-chevron-down"></i>
            </button>
            <div class="user-menu-dropdown">
                <a href="profile.php"><i class="fas fa-user-edit"></i> ویرایش پروفایل</a>
                <a href="logout.php"><i class="fas fa-sign-out-alt"></i> خروج از حساب</a>
            </div>
        </div>
    </header>

    <?php if ($is_staff): ?>
        <div class="staff-status-bar">
            <span><span class="status-label"><i class="fas fa-user-tag"></i> مقام:</span> <span class="status-value"><?= htmlspecialchars($staff_permissions) ?></span></span>
            <span>
                <span class="status-label"><i class="fas fa-shield-alt"></i> وضعیت احراز:</span>
                <span class="status-value <?= $staff_is_verify ? 'status-verified' : 'status-not-verified' ?>">
                    <?= $staff_is_verify ? '<i class="fas fa-check-circle"></i> تایید شده (سطح 2)' : '<i class="fas fa-hourglass-half"></i> در انتظار تایید (سطح 1)' ?>
                </span>
            </span>
            <span><span class="status-label"><i class="fas fa-history"></i> آخرین ورود:</span> <span class="status-value"><?= htmlspecialchars($staff_last_login_formatted) ?></span></span>
        </div>
    <?php endif; ?>

    <div class="dashboard-container">
        <div class="dashboard-content">
            <div class="logo-wrapper">
                <div class="logo-circle">
                    <img src="assets/images/logo.png" alt="Logo" class="logo">
                </div>
            </div>
            <h1>خوش آمدید، <?= htmlspecialchars($username) ?>!</h1>

            <?php if ($user_type === 'user'): ?>
                <p class="welcome-text">از این پنل می‌توانید به خدمات خود دسترسی پیدا کنید.</p>
            <?php elseif ($is_staff && !$staff_is_verify) : ?>
                 <p class="welcome-text" style="color: var(--not-verified-color);">حساب کاربری استف شما هنوز توسط منیجر ها  تایید نشده است. لطفاً منتظر بمانید.</p>
            <?php elseif ($is_staff && $staff_is_verify) : ?>
                 <p class="welcome-text" style="color: var(--verified-color);">حساب کاربری استف شما فعال و تایید شده است.</p>
            <?php endif; ?>


            <div class="services-section">
                <h2><i class="fas fa-concierge-bell"></i> خدمات در دسترس شما</h2>
                <div class="service-buttons">
                    <?php
                        $has_services_to_show = false; 
                    ?>

                    <?php if ($has_user_panel):  ?>
                        <a href="<?= htmlspecialchars(PANEL_URL) ?>" class="service-btn user-panel-btn" target="_blank">
                            <i class="fas fa-tachometer-alt"></i>
                            <span>ورود به پنل کاربری</span>
                        </a>
                        <?php $has_services_to_show = true; ?>
                    <?php endif; ?>

                    <?php if ($is_owner):  ?>
                        <a href="admin_panel.php" class="service-btn admin-panel-btn">
                            <i class="fas fa-user-shield"></i>
                            <span>پنل مدیریت SSO</span>
                        </a>
                        <?php $has_services_to_show = true; ?>
                    <?php endif; ?>

                    <?php
                        if ($is_staff && $staff_is_verify) {
                            if (strpos(strtolower($staff_permissions ?? ''), 'dev') !== false) {
                                echo '<a href="/server_management/server_control.php" class="service-btn staff-specific-btn"><i class="fas fa-headset"></i><span>پنل کنترل سرور استف</span></a>';
                                $has_services_to_show = true;
                            }
                        }
                    ?>

                    <?php if (!$has_services_to_show): ?>
                         <div class="no-service">
                             <i class="fas fa-info-circle"></i>
                             <p>در حال حاضر سرویس خاصی برای شما تعریف نشده است.</p>
                             <?php if ($is_staff && !$staff_is_verify): ?>
                                <p>پس از تایید هویت توسط منیجر، دسترسی‌های شما (در صورت وجود) در اینجا نمایش داده خواهند شد.</p>
                             <?php endif; ?>
                         </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>