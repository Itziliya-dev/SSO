<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__.'/includes/config.php';
require_once __DIR__.'/includes/database.php';

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
} catch (Exception $e) {
    error_log('Could not fetch login notification: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ورود به سیستم | SSO Center</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'dark-primary': '#0f0f23',
                        'dark-secondary': '#1a1a2e',
                        'dark-tertiary': '#16213e',
                        'yellow-primary': '#ffd700',
                        'purple-accent': '#a855f7',
                    }
                }
            }
        }
    </script>
<style>
    @import url('https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;500;600;700&display=swap');
    body { 
        font-family: 'Vazirmatn', sans-serif;
        overflow: hidden;
    }
    /* ... سایر استایل‌های فرم شما ... */
    .form-input {
        background-color: #16213e;
        border: 1px solid rgba(255, 215, 0, 0.2);
        color: #e5e7eb;
        transition: all 0.2s ease-in-out;
    }
    .form-input:focus {
        outline: none;
        border-color: #ffd700;
        box-shadow: 0 0 0 2px rgba(255, 215, 0, 0.3);
    }
    .modal-overlay {
        background-color: rgba(0, 0, 0, 0.7);
        backdrop-filter: blur(5px);
    }

    /* --- شروع تغییرات اصلی --- */

    /* 1. آماده‌سازی باکس اصلی */
    .showcase-bg {
        background-color: #0f0f23; /* رنگ پس‌زمینه اصلی اینجا می‌ماند */
        position: relative; /* برای قرارگیری صحیح لایه نورانی */
        overflow: hidden;   /* برای اینکه نور از کادر بیرون نزند */
        z-index: 1;         /* برای اینکه محتوا روی لایه نور بماند */
        animation: fadeIn 0.8s ease-out forwards; /* انیمیشن ورود اولیه */
    }

    /* 2. ساخت لایه نورانی با ::after */
    .showcase-bg::after {
        content: '';
        position: absolute;
        top: 0; left: 0; right: 0; bottom: 0;
        z-index: -1; /* لایه نور را پشت محتوای اصلی (لوگو و متن) قرار می‌دهد */
        
        /* خود نور در این لایه تعریف می‌شود */
        background-image: radial-gradient(circle at center, rgba(168, 85, 247, 0.25), transparent 55%);
        
        /* انیمیشن تنفس فقط به این لایه اعمال می‌شود */
        animation: breathe 7s ease-in-out infinite;
    }

    /* 3. تعریف انیمیشن تنفس (breathe) */
    @keyframes breathe {
      0%, 100% {
        transform: scale(1);
        opacity: 0.8;
      }
      50% {
        transform: scale(1.25); /* بزرگ شدن (عمل دم) */
        opacity: 1;            /* کمی روشن‌تر شدن */
      }
    }

    /* انیمیشن ورود اولیه (بدون تغییر) */
    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }
    /* --- پایان تغییرات اصلی --- */

</style>
<!-- HTML Meta Tags -->

<meta name="description" content="سیستم کنترل اطلاعات مجموعه نهران کانتیمنت">

<!-- Facebook Meta Tags -->
<meta property="og:url" content="https://sso.itziliya-dev.ir">
<meta property="og:type" content="website">
<meta property="og:title" content="ورود به سیستم | SSO Center">
<meta property="og:description" content="سیستم کنترل اطلاعات مجموعه نهران کانتیمنت">
<meta property="og:image" content="https://sso.itziliya-dev.ir/assets/images/meta.png">

<!-- Twitter Meta Tags -->
<meta name="twitter:card" content="summary_large_image">
<meta property="twitter:domain" content="sso.itziliya-dev.ir">
<meta property="twitter:url" content="https://sso.itziliya-dev.ir">
<meta name="twitter:title" content="ورود به سیستم | SSO Center">
<meta name="twitter:description" content="سیستم کنترل اطلاعات مجموعه نهران کانتیمنت">
<meta name="twitter:image" content="">

<!-- Meta Tags Generated via https://www.opengraph.xyz -->
</head>
<body class="text-gray-200">

    <div class="flex flex-col md:flex-row min-h-screen">
        
        <div class="w-full md:w-1/2 lg:w-5/12 showcase-bg flex flex-col items-center justify-center p-12 text-center">
<img src="assets/images/logo.png" alt="Logo" class="w-32 h-32 mb-6 rounded-full ring-2 ring-purple-accent">            <h1 class="text-3xl font-bold text-yellow-primary">سیستم متمرکز کنترل اطلاعات</h1>
            <p class="text-gray-400 mt-2 text-lg">Tehran Containment</p>
        </div>

        <div class="w-full md:w-1/2 lg:w-7/12 bg-dark-secondary flex flex-col items-center justify-center p-8 md:p-12 relative">
            <div class="w-full max-w-md">
                <h2 class="text-2xl font-bold text-gray-200 mb-6 text-center">ورود به حساب کاربری</h2>

                <?php if ($error): ?>
                <div class="mb-4 p-3 rounded-lg text-sm text-center font-semibold bg-red-500/20 text-red-300 border border-red-400/30">
                    <?= htmlspecialchars($error) ?>
                </div>
                <?php endif; ?>
                
                <?php if ($notification): ?>
                <div class="mb-4 p-3 rounded-lg text-sm text-center bg-blue-500/20 text-blue-300 border border-blue-400/30">
                    <span><?= $notification ?></span>
                </div>
                <?php endif; ?>

                <form action="auth.php" method="POST" class="space-y-6">
                    <div>
                        <label for="username" class="block text-sm font-medium text-gray-400 mb-2">نام کاربری</label>
                        <input type="text" id="username" name="username" class="form-input w-full p-3 rounded-lg" required autocomplete="username">
                    </div>
                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-400 mb-2">رمز عبور</label>
                        <input type="password" id="password" name="password" class="form-input w-full p-3 rounded-lg" required autocomplete="current-password">
                    </div>
                    <button type="submit" class="w-full px-6 py-3 text-sm font-bold text-dark-primary bg-gradient-to-r from-yellow-primary to-yellow-dark hover:shadow-lg hover:shadow-yellow-primary/20 rounded-lg transition-all">
                        ورود
                    </button>
                </form>

                <div class="text-center mt-6">
                    <p class="text-sm text-gray-400">
                        حساب کاربری ندارید؟
                        <a href="register.php" class="font-medium text-yellow-primary hover:underline">ثبت نام کنید</a>
                    </p>
                </div>
            </div>
            <button id="help-btn" class="absolute top-6 left-6 w-8 h-8 flex items-center justify-center bg-dark-tertiary text-gray-400 hover:text-yellow-primary rounded-full transition-colors">
                <i class="fas fa-question text-xs"></i>
            </button>
        </div>
    </div>

    <div id="help-modal-overlay" class="fixed inset-0 z-50 items-center justify-center p-4 hidden modal-overlay">
        <div class="w-full max-w-2xl bg-dark-secondary rounded-2xl border border-yellow-primary/20 shadow-2xl p-8 animate-fade-in relative">
            <span id="close-modal-btn" class="absolute top-4 left-4 text-gray-400 hover:text-yellow-primary cursor-pointer text-2xl">&times;</span>
            <h3 class="text-xl font-bold text-yellow-primary mb-6">راهنمای عیب‌یابی و خطاهای رایج</h3>
            <div class="space-y-6 text-sm text-gray-300">
                <div>
                    <h4 class="font-bold text-gray-200 mb-2"><i class="fas fa-sign-in-alt text-yellow-primary/80 ml-2"></i>مشکلات ورود</h4>
                    <ul class="list-disc list-inside space-y-2 pr-4">
                        <li><strong>نام کاربری یا رمز عبور اشتباه:</strong> از صحت اطلاعات، زبان کیبورد و خاموش بودن Caps Lock مطمئن شوید.</li>
                        <li><strong>تلاش بیش از حد برای ورود:</strong> پس از 3 بار ورود ناموفق، دسترسی شما 15 دقیقه مسدود می‌شود.</li>
                        <li><strong>فراموشی رمز عبور:</strong> به سرور دیسکورد مراجعه کرده و تیکت پشتیبانی ایجاد نمایید.</li>
                        <li><strong>حساب کاربری غیرفعال:</strong> ممکن است حساب شما توسط مدیر معلق شده باشد. برای پیگیری تیکت بزنید.</li>
                    </ul>
                </div>
                <div>
                    <h4 class="font-bold text-gray-200 mb-2"><i class="fas fa-user-plus text-yellow-primary/80 ml-2"></i>مشکلات ثبت نام</h4>
                    <ul class="list-disc list-inside space-y-2 pr-4">
                        <li>بعد از ثبت نام، حساب شما باید توسط مدیر سیستم تأیید شود. تا قبل از تأیید، امکان ورود وجود نخواهد داشت.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div> 

    <script>
        const helpBtn = document.getElementById('help-btn');
        const modalOverlay = document.getElementById('help-modal-overlay');
        const closeModalBtn = document.getElementById('close-modal-btn');
        function openModal() { if(modalOverlay) modalOverlay.classList.remove('hidden'); modalOverlay.classList.add('flex'); }
        function closeModal() { if(modalOverlay) modalOverlay.classList.add('hidden'); modalOverlay.classList.remove('flex'); }
        if(helpBtn) helpBtn.addEventListener('click', openModal);
        if(closeModalBtn) closeModalBtn.addEventListener('click', closeModal);
        if(modalOverlay) {
            modalOverlay.addEventListener('click', function(event) {
                if (event.target === modalOverlay) closeModal();
            });
        }
    </script>
</body>
</html>