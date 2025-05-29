<?php
require_once __DIR__.'/includes/config.php';
require_once __DIR__.'/includes/database.php';
require_once __DIR__.'/includes/auth_functions.php';

session_start();

// بررسی احراز هویت کاربر
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// دریافت اطلاعات کاربر از دیتابیس
$user = getUserById($_SESSION['user_id']);
if (!$user) {
    session_destroy();
    header('Location: login.php');
    exit();
}

// تعیین عنوان صفحه بر اساس نوع کاربر
$page_title = $user['is_owner'] ? 'پنل مدیریت' : 'پنل کاربری';
?>
<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> | SSO Center</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/user_panel.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;600&display=swap" rel="stylesheet">
</head>
<body>
    <div class="background-image"></div>
    
    <div class="user-panel-container">
        <!-- هدر پنل کاربری -->
        <header class="user-panel-header">
            <div class="user-info">
                <div class="user-avatar" onclick="window.location.href='profile.php'">
                    <i class="fas fa-user-circle"></i>
                </div>
                <div class="user-details">
                    <span class="username"><?= htmlspecialchars($user['username']) ?></span>
                    <span class="user-role"><?= $user['is_owner'] ? 'مدیر سیستم' : 'کاربر عادی' ?></span>
                </div>
            </div>
            <a href="logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i>
                خروج
            </a>
        </header>
        
        <!-- محتوای اصلی پنل -->
        <main class="user-panel-content">
            <h2 class="section-title">لیست خدماتی که برای شما فعال است</h2>
            
            <div class="services-grid">
                <!-- پنل کاربری اصلی -->
                <div class="service-card">
                    <div class="service-icon">
                        <i class="fas fa-tachometer-alt"></i>
                    </div>
                    <h3 class="service-title">پنل کاربری</h3>
                    <p class="service-description">مدیریت حساب کاربری و تنظیمات شخصی</p>
                    <a href="<?= PANEL_URL ?>" class="service-link">ورود به پنل</a>
                </div>
                
                <!-- پنل مدیریت (فقط برای مدیران) -->
                <?php if ($user['is_owner']): ?>
                <div class="service-card">
                    <div class="service-icon">
                        <i class="fas fa-user-shield"></i>
                    </div>
                    <h3 class="service-title">پنل مدیریت SSO</h3>
                    <p class="service-description">مدیریت کاربران و سیستم احراز هویت</p>
                    <a href="admin_panel.php" class="service-link">ورود به پنل</a>
                </div>
                <?php endif; ?>
                
                <!-- سایر سرویس‌ها -->
                <div class="service-card">
                    <div class="service-icon">
                        <i class="fas fa-user-edit"></i>
                    </div>
                    <h3 class="service-title">پروفایل کاربری</h3>
                    <p class="service-description">مشاهده و ویرایش اطلاعات شخصی</p>
                    <a href="profile.php" class="service-link">مشاهده پروفایل</a>
                </div>
                
                <div class="service-card">
                    <div class="service-icon">
                        <i class="fas fa-key"></i>
                    </div>
                    <h3 class="service-title">تغییر رمز عبور</h3>
                    <p class="service-description">به‌روزرسانی رمز عبور حساب کاربری</p>
                    <a href="reset_password.php" class="service-link">تغییر رمز</a>
                </div>
            </div>
        </main>
    </div>
    
    <script src="assets/js/user_panel.js"></script>
</body>
</html>