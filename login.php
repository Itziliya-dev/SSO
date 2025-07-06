<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
// --- بخش PHP بدون تغییر باقی می‌ماند ---
require_once __DIR__.'/includes/config.php';
require_once __DIR__.'/includes/database.php';
require_once __DIR__.'/includes/header.php';
session_start();
$error = $_SESSION['login_error'] ?? '';
unset($_SESSION['login_error']);
$notification = null;
try {
    $db = getDbConnection();
    $result = $db->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('login_notice_text', 'login_notice_enabled', 'login_notice_expiry')");
    $settings = [];
    while ($row = $result->fetch_assoc()) { $settings[$row['setting_key']] = $row['setting_value']; }
    $is_active = $settings['login_notice_enabled'] ?? '0';
    $expires_at = $settings['login_notice_expiry'] ?? null;
    $message = $settings['login_notice_text'] ?? '';
    if ($is_active === '1' && !empty($message) && ($expires_at === null || new DateTime() < new DateTime($expires_at))) {
        $safe_message = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
        $notification = preg_replace('/\\*\\*(.*?)\\*\\*/s', '<strong>$1</strong>', $safe_message);
        $notification = preg_replace('/\\*(.*?)\\*/s', '<em>$1</em>', $notification);
        $notification = preg_replace('/__(.*?)__/s', '<u>$1</u>', $notification);
        $notification = preg_replace('/\\~\\~(.*?)\\~\\~/s', '<s>$1</s>', $notification);
    }
} catch (Exception $e) { error_log('Could not fetch login notification: ' . $e->getMessage()); }
?>
<title>ورود به سیستم | SSO Center</title>
<link rel="stylesheet" href="assets/css/login_modern.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="/assets/fonts/Vazirmatn-font-face.css">
<link rel="preload" href="assets/images/logo.png" as="image">
<link rel="preload" href="assets/images/background.jpg" as="image">


<?php
// تگ <head> در فایل header.php بسته شده و تگ <body> باز شده است.
// از اینجا به بعد، بقیه محتوای فایل شما (که داخل <body> قرار داشت) بدون هیچ تغییری می‌آید.
?>
    <div class="login-wrapper">
        <div class="login-showcase">
            <div class="showcase-overlay"></div>
            <div class="showcase-content">
                <div class="logo-container">
                    <img src="assets/images/logo.png" alt="Logo" class="logo">
                </div>
                <h1>سیستم متمرکز کنترل اطلاعات</h1>
                <p>Tehran Containment</p>
            </div>
        </div>

        <div class="login-form-container">
            <h2>ورود به حساب کاربری</h2>

            <?php if ($error): ?>
            <div class="alert-message">
                <?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>
            
            <?php if ($notification): ?>
            <div class="notification-message">
                <span><?= $notification ?></span>
            </div>
            <?php endif; ?>

            <form action="auth.php" method="POST">
                <div class="form-group">
                    <input type="text" id="username" name="username" class="form-input" placeholder=" " required autocomplete="username">
                    <label for="username" class="form-label">نام کاربری</label>
                </div>
                <div class="form-group">
                    <input type="password" id="password" name="password" class="form-input" placeholder=" " required autocomplete="current-password">
                    <label for="password" class="form-label">رمز عبور</label>
                </div>
                <button type="submit" class="login-button">ورود</button>
            </form>
            <button id="help-btn" class="help-button">
                <i class="fas fa-question"></i>
            </button>
            <div class="register-link">
                حساب کاربری ندارید؟ <a href="register.php">ثبت نام کنید</a>
            </div>
        </div>
    </div>
 
<div id="help-modal-overlay" class="modal-overlay">
    
    <div class="modal-content">
        <span id="close-modal-btn" class="close-modal">&times;</span>
        <h3>راهنمای عیب‌یابی و خطاهای رایج</h3>
        
        <h4><i class="fas fa-sign-in-alt"></i> مشکلات ورود</h4>
        <ul>
            <li><strong>نام کاربری یا رمز عبور اشتباه:</strong> لطفاً از صحت اطلاعات وارد شده، زبان کیبورد و خاموش بودن کلید Caps Lock اطمینان حاصل کنید.</li>
            <li><strong>تلاش بیش از حد برای ورود:</strong> سیستم ما پس از 3 بار ورود ناموفق، دسترسی شما را به مدت 15 دقیقه مسدود می‌کند. لطفاً پس از این مدت دوباره تلاش کنید.</li>
            <li><strong>فراموشی رمز عبور:</strong> در صورتی که رمز عبور خود را فراموش کرده‌اید، لطفاً به سرور دیسکورد مراجعه کرده و یک تیکت پشتیبانی ایجاد نمایید.</li>
            <li><strong>حساب کاربری فعال نیست:</strong> ممکن است حساب شما توسط مدیر سیستم معلق شده باشد. لطفاً به سرور دیسکورد مراجعه کرده و یک تیکت پشتیبانی ایجاد نمایید.</li>
        </ul>

        <h4><i class="fas fa-user-plus"></i> مشکلات ثبت نام</h4>
        <ul>
            <li>بعد از ثبت نام، حساب شما باید توسط مدیر سیستم تأیید شود. تا قبل از تأیید، امکان ورود به پنل وجود نخواهد داشت.</li>
        </ul>
    </div>
    
</div> 
<script>
    // انتخاب عناصر از DOM
    const helpBtn = document.getElementById('help-btn');
    const modalOverlay = document.getElementById('help-modal-overlay');
    const closeModalBtn = document.getElementById('close-modal-btn');

    // تابع برای باز کردن مودال
    function openModal() {
        if(modalOverlay) modalOverlay.style.display = 'flex';
    }

    // تابع برای بستن مودال
    function closeModal() {
        if(modalOverlay) modalOverlay.style.display = 'none';
    }

    // اختصاص رویدادها
    if(helpBtn) helpBtn.addEventListener('click', openModal);
    if(closeModalBtn) closeModalBtn.addEventListener('click', closeModal);

    // بستن مودال با کلیک روی پس‌زمینه تیره
    if(modalOverlay) {
        modalOverlay.addEventListener('click', function(event) {
            if (event.target === modalOverlay) {
                closeModal();
            }
        });
    }
</script>
</body>
</html>