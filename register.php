<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__.'/includes/config.php';

session_start();

$error = $_GET['error'] ?? '';
$success = $_GET['success'] ?? '';
$tracking_code = $_GET['tracking_code'] ?? '';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ثبت نام | SSO Center</title>
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
                        'purple-accent': '#a855f7', // رنگ بنفش
                    }
                }
            }
        }
    </script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;500;600;700&display=swap');
        body { 
            font-family: 'Vazirmatn', sans-serif;
            background: linear-gradient(135deg, #0f0f23 0%, #1a1a2e 50%, #16213e 100%);
            overflow-x: hidden;
        }
        .glow-effect-purple { box-shadow: 0 0 40px rgba(168, 85, 247, 0.25); }
        .form-input {
            background-color: #16213e;
            border: 1px solid rgba(168, 85, 247, 0.2);
            color: #e5e7eb;
            transition: all 0.2s ease-in-out;
        }
        .form-input:focus {
            outline: none;
            border-color: #a855f7;
            box-shadow: 0 0 0 2px rgba(168, 85, 247, 0.3);
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in { animation: fadeIn 0.6s ease-out forwards; }
    </style>
    <!-- HTML Meta Tags -->
<meta name="description" content="استف جدید هستید ؟ به خانواده ما خوش امدید">

<!-- Facebook Meta Tags -->
<meta property="og:url" content="https://sso.itziliya-dev.ir/register.php">
<meta property="og:type" content="website">
<meta property="og:title" content="ثبت نام | SSO Center">
<meta property="og:description" content="استف جدید هستید ؟ به خانواده ما خوش امدید">
<meta property="og:image" content="https://sso.itziliya-dev.ir/assets/images/meta.png">

<!-- Twitter Meta Tags -->
<meta name="twitter:card" content="summary_large_image">
<meta property="twitter:domain" content="sso.itziliya-dev.ir">
<meta property="twitter:url" content="https://sso.itziliya-dev.ir/register.php">
<meta name="twitter:title" content="ثبت نام | SSO Center">
<meta name="twitter:description" content="استف جدید هستید ؟ به خانواده ما خوش امدید">
<meta name="twitter:image" content="">

<!-- Meta Tags Generated via https://www.opengraph.xyz -->
</head>
<body class="text-gray-200">

    <div class="min-h-screen flex flex-col items-center justify-center p-4 my-8">
        
        <div class="w-full max-w-2xl bg-dark-secondary border border-purple-accent/20 rounded-2xl shadow-2xl p-8 animate-fade-in glow-effect-purple">
            
            <div class="text-center mb-8">
                <img src="assets/images/logo.png" alt="Logo" class="w-16 h-16 mx-auto mb-4">
                <h1 class="text-2xl font-bold text-yellow-primary">ایجاد حساب کاربری جدید</h1>
                <p class="text-gray-400 text-sm mt-1">با پر کردن فیلدهای زیر به مجموعه ما بپیوندید.</p>
            </div>

            <?php if ($error): ?>
            <div class="mb-6 p-3 rounded-lg text-sm text-center font-semibold bg-red-500/20 text-red-300 border border-red-400/30">
                <?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>

            <?php if ($success): ?>
            <div class="mb-6 p-4 rounded-lg text-center font-semibold bg-purple-accent/10 text-purple-300 border border-purple-accent/20">
                <p class="text-base"><?= htmlspecialchars($success) ?></p>
                <?php if ($tracking_code): ?>
                <div class="mt-3 text-sm">
                    <span>کد پیگیری شما:</span>
                    <strong class="text-yellow-primary text-base tracking-widest bg-dark-primary px-2 py-1 rounded-md"><?= htmlspecialchars($tracking_code) ?></strong>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <form action="process_register.php" method="POST" class="space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="fullname" class="block text-sm font-medium text-gray-400 mb-2">نام و نام خانوادگی</label>
                        <input type="text" id="fullname" name="fullname" class="form-input w-full p-3 rounded-lg" required>
                    </div>
                    <div>
                        <label for="username" class="block text-sm font-medium text-gray-400 mb-2">نام کاربری</label>
                        <input type="text" id="username" name="username" class="form-input w-full p-3 rounded-lg" required>
                    </div>
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-gray-400 mb-2">رمز عبور</label>
                    <input type="password" id="password" name="password" class="form-input w-full p-3 rounded-lg" required>
                </div>

                <div>
                    <label for="email" class="block text-sm font-medium text-gray-400 mb-2">آدرس ایمیل</label>
                    <input type="email" id="email" name="email" class="form-input w-full p-3 rounded-lg" required>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="phone" class="block text-sm font-medium text-gray-400 mb-2">شماره تلفن</label>
                        <input type="tel" id="phone" name="phone" class="form-input w-full p-3 rounded-lg" required>
                    </div>
                    <div>
                        <label for="age" class="block text-sm font-medium text-gray-400 mb-2">سن</label>
                        <input type="number" id="age" name="age" class="form-input w-full p-3 rounded-lg" min="13" max="100" required>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="discord_id" class="block text-sm font-medium text-gray-400 mb-2">آیدی دیسکورد</label>
                        <input type="text" id="discord_id" name="discord_id" class="form-input w-full p-3 rounded-lg" required>
                    </div>
                    <div>
                        <label for="steam_id" class="block text-sm font-medium text-gray-400 mb-2">آیدی استیم</label>
                        <input type="text" id="steam_id" name="steam_id" class="form-input w-full p-3 rounded-lg" required>
                    </div>
                </div>
                
                <button type="submit" class="w-full px-6 py-3 text-sm font-bold text-dark-primary bg-gradient-to-r from-yellow-primary to-yellow-dark hover:shadow-lg hover:shadow-yellow-primary/20 rounded-lg transition-all">
                    ارسال درخواست ثبت نام
                </button>
            </form>

            <div class="text-center mt-6">
                <p class="text-sm text-gray-400">
                    قبلاً ثبت نام کرده‌اید؟
                    <a href="login.php" class="font-medium text-yellow-primary hover:underline">وارد شوید</a>
                </p>
            </div>
        </div>
    </div>

</body>
</html>