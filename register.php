<?php
require_once __DIR__.'/includes/config.php';
require_once __DIR__.'/includes/header.php';

session_start();

$error = isset($_GET['error']) ? $_GET['error'] : '';
$success = isset($_GET['success']) ? $_GET['success'] : '';
$tracking_code = isset($_GET['tracking_code']) ? $_GET['tracking_code'] : '';
?>
    <title>ثبت نام | SSO Center</title>
    <link rel="stylesheet" href="assets/css/login_modern.css">
    <link rel="stylesheet" href="/assets/fonts/Vazirmatn-font-face.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<?php
?>
    <div class="login-wrapper">
        <div class="login-showcase">
            <div class="showcase-overlay"></div>
        <div class="showcase-content">
            <div class="logo-container">
                <img src="assets/images/logo.png" alt="Logo" class="logo">
            </div>
            <h1>به مجموعه ما بپیوندید</h1>
            <p>با پر کردن فیلدهای روبه‌رو، اطلاعات خود را جهت احراز هویت وارد نمایید.</p>
        </div>
        </div>

        <div class="login-form-container">
            <h2>ایجاد حساب کاربری جدید</h2>

            <?php if ($error): ?>
            <div class="alert-message">
                <?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>

            <?php if ($success): ?>
            <div class="success-message">
                <?= htmlspecialchars($success) ?>
                <?php if ($tracking_code): ?>
                <div class="tracking-code">
                    کد پیگیری: <strong><?= htmlspecialchars($tracking_code) ?></strong>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <form action="process_register.php" method="POST">
                <div class="form-grid">
                    <div class="form-group">
                        <input type="text" id="fullname" name="fullname" class="form-input" placeholder=" " required>
                        <label for="fullname" class="form-label">نام و نام خانوادگی</label>
                    </div>
                    <div class="form-group">
                        <input type="text" id="username" name="username" class="form-input" placeholder=" " required>
                        <label for="username" class="form-label">نام کاربری</label>
                    </div>
                </div>

                <div class="form-group">
                    <input type="password" id="password" name="password" class="form-input" placeholder=" " required>
                    <label for="password" class="form-label">رمز عبور</label>
                </div>

                <div class="form-group">
                    <input type="email" id="email" name="email" class="form-input" placeholder=" " required>
                    <label for="email" class="form-label">آدرس ایمیل</label>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <input type="tel" id="phone" name="phone" class="form-input" placeholder=" " required>
                        <label for="phone" class="form-label">شماره تلفن</label>
                    </div>
                    <div class="form-group">
                        <input type="number" id="age" name="age" class="form-input" min="13" max="100" placeholder=" " required>
                        <label for="age" class="form-label">سن</label>
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <input type="text" id="discord_id" name="discord_id" class="form-input" placeholder=" " required>
                        <label for="discord_id" class="form-label">آیدی دیسکورد</label>
                    </div>
                    <div class="form-group">
                        <input type="text" id="steam_id" name="steam_id" class="form-input" placeholder=" " required>
                        <label for="steam_id" class="form-label">آیدی استیم</label>
                    </div>
                </div>
                
                <button type="submit" class="login-button">ارسال درخواست ثبت نام</button>
            </form>
            <div class="register-link">
                قبلاً ثبت نام کرده‌اید؟ <a href="login.php">وارد شوید</a>
            </div>
        </div>
    </div>
</body>
</html>