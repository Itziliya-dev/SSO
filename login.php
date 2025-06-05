<?php
require_once __DIR__.'/includes/config.php';
session_start();

$error = '';
if (isset($_SESSION['login_error'])) {
    $error = $_SESSION['login_error'];
    // پاک کردن پیام از سشن تا دوباره نمایش داده نشود
    unset($_SESSION['login_error']);
}?>
<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ورود به سیستم | SSO Center</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="preload" href="assets/images/background.jpg" as="image">
    <link rel="preload" href="assets/images/logo.png" as="image">
</head>
<body>
    <div class="background-image"></div>
    
    <div class="loading-overlay">
        <div class="loading-content">
            <div class="logo-circle">
                <img src="assets/images/logo.png" alt="Logo" class="logo">
            </div>
            <h1>سیستم متمرکز کنترل اطلاعات</h1>
            <p class="tehran-text">Tehran Containment</p>
            <p class="sso-text">sso-center</p>
        </div>
    </div>
    
    <div class="login-container">
        <div class="login-box">
            <div class="logo-wrapper">
                <div class="logo-circle">
                    <img src="assets/images/logo.png" alt="Logo" class="logo">
                </div>
                <h1>سیستم متمرکز کنترل اطلاعات</h1>
                <p class="tehran-text">Tehran Containment</p>
                <p class="sso-text">sso-center</p>
            </div>

            <?php if ($error): ?>
            <div class="alert">
                <i class="fas fa-exclamation-circle"></i>
                <?= $error ?>
            </div>
            <?php endif; ?>
            <div class="notification-box">
    <i class="fas fa-exclamation-circle"></i>
    <span>تمامی استف ها موظف هستند تا تاریخ 15 خرداد در سامانه <a href="register.php">ثبت نام</a> کنند</span>
</div>


            <form action="auth.php" method="POST" class="login-form">
                <div class="input-group">
                    <label for="username">
                        <i class="fas fa-user"></i>
                        نام کاربری
                    </label>
                    <input 
                        type="text" 
                        id="username" 
                        name="username" 
                        placeholder="نام کاربری خود را وارد کنید" 
                        required
                        autocomplete="username"
                    >
                </div>

                <div class="input-group">
                    <label for="password">
                        <i class="fas fa-lock"></i>
                        رمز عبور
                    </label>
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        placeholder="رمز عبور خود را وارد کنید" 
                        required
                        autocomplete="current-password"
                    >
                </div>

                <div class="auth-links">
    <a href="register.php" class="auth-link">
        <i class="fas fa-user-plus"></i> ثبت نام جدید
    </a>

</div>
<?php if (isset($_SESSION['login_success'])): ?>
<div class="login-success">
    <?php if ($_SESSION['is_owner']): ?>
    <div class="owner-buttons">
        <a href="<?= PANEL_URL ?>" class="btn-panel">
            <i class="fas fa-tachometer-alt"></i> ورود به پنل
        </a>
        <a href="admin_panel.php" class="btn-admin">
            <i class="fas fa-user-cog"></i> پنل مدیریت
        </a>
    </div>
    <?php else: ?>
    <a href="<?= PANEL_URL ?>" class="btn-panel">ورود به پنل</a>
    <?php endif; ?>
</div>
<?php unset($_SESSION['login_success']); endif; ?>

                <button type="submit" class="login-btn">
                    <span>ورود به سیستم</span>
                    <i class="fas fa-arrow-left"></i>
                </button>
            </form>
        </div>
    </div>

    <script src="assets/js/login.js"></script>
</body>
</html>