<?php
require_once __DIR__.'/includes/config.php';
session_start();

$error = isset($_GET['error']) ? $_GET['error'] : '';
$success = isset($_GET['success']) ? $_GET['success'] : '';
$tracking_code = isset($_GET['tracking_code']) ? $_GET['tracking_code'] : '';
?>
<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ثبت نام | SSO Center</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;600&display=swap" rel="stylesheet">
</head>
<body>
    <div class="background-image"></div>
    
    <div class="login-container">
        <div class="login-box">
            <div class="logo-wrapper">
                <div class="logo-circle">
                    <img src="assets/images/logo.png" alt="Logo" class="logo">
                </div>
                <h1>ثبت نام در سیستم</h1>
            </div>

            <?php if ($error): ?>
            <div class="alert">
                <i class="fas fa-exclamation-circle"></i>
                <?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>

            <?php if ($success): ?>
            <div class="success-message">
                <i class="fas fa-check-circle"></i>
                <?= htmlspecialchars($success) ?>
                <?php if ($tracking_code): ?>
                <div class="tracking-code">
                    کد پیگیری: <strong><?= htmlspecialchars($tracking_code) ?></strong>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <form action="process_register.php" method="POST" class="login-form">
                <div class="input-group">
                    <label for="fullname">
                        <i class="fas fa-user-tag"></i>
                        نام و نام خانوادگی
                    </label>
                    <input 
                        type="text" 
                        id="fullname" 
                        name="fullname" 
                        placeholder="نام کامل خود را وارد کنید" 
                        required
                    >
                </div>

                <div class="input-group">
                    <label for="username">
                        <i class="fas fa-user"></i>
                        نام کاربری
                    </label>
                    <input 
                        type="text" 
                        id="username" 
                        name="username" 
                        placeholder="نام کاربری دلخواه" 
                        required
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
                        placeholder="رمز عبور قوی انتخاب کنید" 
                        required
                    >
                </div>

                <div class="input-group">
                    <label for="email">
                        <i class="fas fa-envelope"></i>
                        آدرس ایمیل
                    </label>
                    <input 
                        type="email" 
                        id="email" 
                        name="email" 
                        placeholder="example@domain.com" 
                        required
                    >
                </div>

                <div class="input-group">
                    <label for="phone">
                        <i class="fas fa-phone"></i>
                        شماره تلفن
                    </label>
                    <input 
                        type="tel" 
                        id="phone" 
                        name="phone" 
                        placeholder="09xxxxxxxxx" 
                        required
                    >
                </div>

                <div class="input-group">
                    <label for="age">
                        <i class="fas fa-birthday-cake"></i>
                        سن
                    </label>
                    <input 
                        type="number" 
                        id="age" 
                        name="age" 
                        min="13" 
                        max="100" 
                        required
                    >
                </div>

                <div class="input-group">
                    <label for="discord_id">
                        <i class="fab fa-discord"></i>
                        آیدی دیسکورد
                    </label>
                    <input 
                        type="text" 
                        id="discord_id" 
                        name="discord_id" 
                        placeholder="Username#1234" 
                        required
                    >
                </div>

                <div class="input-group">
                    <label for="steam_id">
                        <i class="fab fa-steam"></i>
                        آیدی استیم
                    </label>
                    <input 
                        type="text" 
                        id="steam_id" 
                        name="steam_id" 
                        placeholder="STEAM_0:0:12345678" 
                        required
                    >
                </div>

                <button type="submit" class="login-btn">
                    <span>ثبت درخواست</span>
                    <i class="fas fa-arrow-left"></i>
                </button>
            </form>

            <div class="register-links">
                <a href="login.php">ورود به حساب کاربری</a>
                <a href="track_request.php">استعلام کد پیگیری</a>
            </div>
        </div>
    </div>
    <script>
  document.addEventListener("DOMContentLoaded", function() {
    // Option 1: Add a class that sets opacity to 1.
    const loginContainer = document.querySelector('.login-container');
    if (loginContainer) {
      loginContainer.classList.add('show');
    }

    // Option 2: Alternatively, directly change the style property.
    // loginContainer.style.opacity = '1';
  });
</script>

</body>
</html>