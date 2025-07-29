<?php
// error_reporting(E_ALL);
// ini_set('display_errors', 1);

require_once __DIR__.'/../includes/config.php';
require_once __DIR__.'/../includes/database.php';

session_start();

// ۱. بررسی اولیه لاگین
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

// ۲. واکشی اطلاعات پایه از سشن
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'کاربر';
$user_type = $_SESSION['user_type'] ?? 'user';
$is_staff = ($user_type === 'staff');

// ۳. واکشی دسترسی‌ها از دیتابیس
$conn = getDbConnection();
$permissions = [];
if ($is_staff) {
    $stmt = $conn->prepare("SELECT * FROM staff_permissions WHERE staff_id = ?");
} else {
    $stmt = $conn->prepare("SELECT * FROM user_permissions WHERE user_id = ?");
}
$stmt->bind_param("i", $user_id);
$stmt->execute();
$permissions = $stmt->get_result()->fetch_assoc() ?: []; // Ensure it's an array
$stmt->close();

// ۴. تنظیم متغیرهای دسترسی
$is_owner = !empty($permissions['is_owner']);
$has_user_panel = !empty($permissions['has_user_panel']);
$has_developer_access = !empty($permissions['has_developer_access']);

// تابع کمکی برای دریافت آواتار
function get_discord_avatar_url_from_bot($discord_id) {
    if (empty($discord_id)) return null;
    $webhook_url = 'http://83.149.95.39:1030/get-avatar-url?discord_id=' . urlencode($discord_id);
    $secret_token = '4a97dd86-4388-4cc0-a54f-65ebbf51649d'; // توکن شما
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

// ۵. آماده‌سازی متغیرهای نمایشی
$final_avatar_src = '../assets/images/logo.png'; // آواتار پیش‌فرض
$staff_display_permissions = 'فاقد مقام';
$staff_is_verify = 0;
$staff_last_login_formatted = 'نامشخص';
$discord_conn_status_key = 'unknown';
$has_services_to_show = false;
$welcome_message = 'از این داشبورد می‌توانید به خدمات در دسترس خود دسترسی پیدا کنید. در صورت نیاز به راهنمایی با پشتیبانی تماس بگیرید.';
$user_role_display = $is_staff ? 'حساب کاربری استف' : 'حساب کاربری';

if ($is_staff) {
    $stmt = $conn->prepare("SELECT is_verify, last_login, discord_conn, discord_id2 FROM `staff-manage` WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $staff_info = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($staff_info) {
        $staff_is_verify = $staff_info['is_verify'] ?? 0;
        $discord_conn_status_key = ($staff_info['discord_conn'] == 1) ? 'connected' : 'not_connected';

        if (!empty($staff_info['last_login'])) {
            try {
                // تبدیل تاریخ میلادی به شمسی نیاز به کتابخانه دارد، در اینجا فرمت ساده نمایش داده می‌شود
                $date = new DateTime($staff_info['last_login']);
                $staff_last_login_formatted = $date->format('Y/m/d ساعت H:i');
            } catch (Exception $e) {
                $staff_last_login_formatted = 'تاریخ نامعتبر';
            }
        }
        
        if (!empty($staff_info['discord_id2'])) {
            $discord_avatar_url = get_discord_avatar_url_from_bot($staff_info['discord_id2']);
            if ($discord_avatar_url) {
                // برای امنیت بهتر، استفاده از image_proxy پیشنهاد می‌شود
                $final_avatar_src = '../admin/image_proxy.php?url=' . urlencode($discord_avatar_url);
            }
        }

        // تنظیم مقام نمایشی
        if ($has_developer_access) {
            $staff_display_permissions = 'توسعه‌دهنده (Dev)';
        } elseif ($is_owner) {
            $staff_display_permissions = 'مالک';
        } else {
             $staff_display_permissions = 'استف'; // یک مقام پیش‌فرض
        }
        
        // تنظیم پیام خوش‌آمدگویی برای استف
        if (!$staff_is_verify) {
            $welcome_message = 'حساب کاربری استف شما هنوز تایید نشده است. تا زمان تایید، دسترسی شما محدود خواهد بود.';
        } elseif ($discord_conn_status_key !== 'connected') {
            $welcome_message = 'برای استفاده از تمامی امکانات، حساب دیسکورد خود را متصل کنید. (دستور /connect در سرور)';
        }
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl" class="">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>داشبورد | SSO Center</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="/assets/fonts/Vazirmatn-font-face.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @font-face {
            font-family: 'Vazirmatn';
            src: url('/assets/fonts/Vazirmatn-Regular.woff2') format('woff2'); /* مسیر فونت خود را تنظیم کنید */
            font-weight: normal;
            font-style: normal;
        }
        
        body {
            font-family: 'Vazirmatn', sans-serif;
        }
        
        /* استایل‌های سفارشی برای حالت دارک و لایت که با Tailwind سخت‌تر است */
        .sidebar-item.active {
            background-color: rgba(59, 130, 246, 0.1);
            border-right: 3px solid #3b82f6;
            color: #3b82f6;
        }
        .sidebar-item.active .sidebar-icon { color: #3b82f6; }
        
        html.dark .sidebar-item.active {
            background-color: rgba(96, 165, 250, 0.2); /* رنگ آبی روشن‌تر برای حالت دارک */
             border-right-color: #60a5fa;
            color: #93c5fd;
        }
        html.dark .sidebar-item.active .sidebar-icon { color: #93c5fd; }

        .service-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.05);
        }
        html.dark .service-card:hover {
             box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
        }

        /* انیمیشن‌ها */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in { animation: fadeIn 0.4s ease-out forwards; }
        .delay-1 { animation-delay: 0.1s; }
        .delay-2 { animation-delay: 0.2s; }
        .delay-3 { animation-delay: 0.3s; }

    </style>
    <script>
        // اسکریپت تم برای جلوگیری از فلش زدن صفحه (FOUC)
        if (localStorage.getItem('theme') === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    </script>
</head>
<body class="bg-gray-100 dark:bg-gray-900 text-gray-800 dark:text-gray-200 transition-colors duration-300">
    <div class="flex h-screen overflow-hidden">
        <aside class="hidden md:flex flex-col w-64 bg-white dark:bg-gray-800 border-l dark:border-gray-700 transition-colors duration-300">
            <div class="flex items-center justify-center h-16 border-b dark:border-gray-700">
                <img src="/assets/images/logo.png" alt="Logo" class="h-8 w-8 ml-2">
                <span class="text-gray-800 dark:text-white font-bold text-lg">SSO Center</span>
            </div>
            <nav class="flex-grow p-4 space-y-2">
                <a href="#" class="flex items-center px-4 py-2.5 text-sm font-medium rounded-lg sidebar-item active">
                    <i class="fas fa-home sidebar-icon w-6 text-center ml-3"></i>
                    داشبورد
                </a>
                <a href="profile.php" class="flex items-center px-4 py-2.5 text-sm font-medium text-gray-600 dark:text-gray-400 rounded-lg sidebar-item hover:bg-gray-100 dark:hover:bg-gray-700">
                    <i class="fas fa-user-edit sidebar-icon w-6 text-center ml-3"></i>
                    ویرایش پروفایل
                </a>
                <a href="passkey_management.php" class="flex items-center px-4 py-2.5 text-sm font-medium text-gray-600 dark:text-gray-400 rounded-lg sidebar-item hover:bg-gray-100 dark:hover:bg-gray-700">
                    <i class="fas fa-key sidebar-icon w-6 text-center ml-3"></i>
                    مدیریت Passkey
                </a>
            </nav>
            <div class="p-4 border-t dark:border-gray-700">
                <div class="p-4 bg-gray-100 dark:bg-gray-700/50 rounded-lg">
                    <div class="flex items-center">
                        <img src="<?= htmlspecialchars($final_avatar_src) ?>" alt="User Avatar" class="w-10 h-10 rounded-full">
                        <div class="mr-3">
                            <p class="text-sm font-medium text-gray-800 dark:text-gray-100"><?= htmlspecialchars($username) ?></p>
                            <p class="text-xs text-gray-500 dark:text-gray-400"><?= htmlspecialchars($user_role_display) ?></p>
                        </div>
                    </div>
                    <a href="logout.php" class="block mt-4 text-center text-sm text-white bg-blue-600 hover:bg-blue-700 py-2 rounded-lg transition-colors">
                        خروج از حساب
                    </a>
                </div>
            </div>
        </aside>

        <div class="flex flex-col flex-1 overflow-hidden">
            <header class="flex items-center justify-between h-16 px-4 sm:px-6 bg-white dark:bg-gray-800 border-b dark:border-gray-700 transition-colors duration-300">
                <div class="flex items-center">
                    <button class="md:hidden text-gray-500 dark:text-gray-400 focus:outline-none" onclick="toggleMobileSidebar()">
                        <i class="fas fa-bars text-xl"></i>
                    </button>
                    <h1 class="text-lg font-medium mr-4 hidden sm:block">داشبورد</h1>
                </div>
                
                <div class="flex items-center space-x-4">
                    <button id="theme-toggle" class="relative text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 focus:outline-none">
                        <i class="fas fa-sun text-xl" id="theme-toggle-light-icon"></i>
                        <i class="fas fa-moon text-xl hidden" id="theme-toggle-dark-icon"></i>
                    </button>

                    <button class="relative text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 focus:outline-none">
                        <i class="fas fa-bell text-xl"></i>
                        </button>
                    
                    <div class="relative">
                        <button class="flex items-center focus:outline-none" onclick="toggleUserDropdown()">
                            <img src="<?= htmlspecialchars($final_avatar_src) ?>" alt="User" class="w-8 h-8 rounded-full">
                            <span class="mr-2 text-sm font-medium hidden sm:inline"><?= htmlspecialchars($username) ?></span>
                            <i class="fas fa-chevron-down text-xs mr-1 hidden sm:inline"></i>
                        </button>
                        
                        <div id="user-dropdown" class="hidden absolute left-0 mt-2 w-48 bg-white dark:bg-gray-800 rounded-md shadow-lg py-1 z-50 border dark:border-gray-700">
                            <a href="profile.php" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">
                                <i class="fas fa-user-edit ml-2"></i> ویرایش پروفایل
                            </a>
                            <a href="passkey_management.php" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">
                                <i class="fas fa-key ml-2"></i> مدیریت Passkey
                            </a>
                            <div class="border-t border-gray-200 dark:border-gray-700 my-1"></div>
                            <a href="logout.php" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">
                                <i class="fas fa-sign-out-alt ml-2"></i> خروج از حساب
                            </a>
                        </div>
                    </div>
                </div>
            </header>
            
            <main class="flex-1 overflow-y-auto p-4 md:p-6">
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6 mb-6 animate-fade-in">
                    <h2 class="text-xl font-bold text-gray-800 dark:text-white">خوش آمدید، <?= htmlspecialchars($username) ?>!</h2>
                    <p class="text-gray-600 dark:text-gray-400 mt-2"><?= htmlspecialchars($welcome_message) ?></p>
                </div>
                
                <?php if ($is_staff): ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4 border-r-4 border-blue-500 animate-fade-in delay-1">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-blue-100 dark:bg-blue-900/50 text-blue-600 dark:text-blue-400 ml-4">
                                <i class="fas fa-user-tag"></i>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500 dark:text-gray-400">مقام</p>
                                <p class="font-medium"><?= htmlspecialchars($staff_display_permissions) ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4 border-r-4 <?= $staff_is_verify ? 'border-green-500' : 'border-yellow-500' ?> animate-fade-in delay-2">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full <?= $staff_is_verify ? 'bg-green-100 dark:bg-green-900/50 text-green-600 dark:text-green-400' : 'bg-yellow-100 dark:bg-yellow-900/50 text-yellow-600 dark:text-yellow-400' ?> ml-4">
                                <i class="fas fa-shield-alt"></i>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500 dark:text-gray-400">وضعیت</p>
                                <p class="font-medium <?= $staff_is_verify ? 'text-green-600 dark:text-green-400' : 'text-yellow-600 dark:text-yellow-400' ?>">
                                    <?= $staff_is_verify ? 'تایید شده' : 'در انتظار تایید' ?>
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4 border-r-4 border-purple-500 animate-fade-in delay-1">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-purple-100 dark:bg-purple-900/50 text-purple-600 dark:text-purple-400 ml-4">
                                <i class="fas fa-history"></i>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500 dark:text-gray-400">آخرین ورود</p>
                                <p class="font-medium text-xs"><?= htmlspecialchars($staff_last_login_formatted) ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4 border-r-4 <?= $discord_conn_status_key === 'connected' ? 'border-indigo-500' : 'border-red-500' ?> animate-fade-in delay-2">
                         <div class="flex items-center">
                            <div class="p-3 rounded-full <?= $discord_conn_status_key === 'connected' ? 'bg-indigo-100 dark:bg-indigo-900/50 text-indigo-600 dark:text-indigo-400' : 'bg-red-100 dark:bg-red-900/50 text-red-600 dark:text-red-400' ?> ml-4">
                                <i class="fab fa-discord"></i>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500 dark:text-gray-400">دیسکورد</p>
                                <p class="font-medium <?= $discord_conn_status_key === 'connected' ? 'text-indigo-600 dark:text-indigo-400' : 'text-red-600 dark:text-red-400' ?>">
                                    <?= $discord_conn_status_key === 'connected' ? 'متصل' : 'متصل نشده' ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6 animate-fade-in delay-3">
                    <h2 class="text-lg font-bold text-gray-800 dark:text-white mb-6">
                        <i class="fas fa-th-large text-blue-600 dark:text-blue-400 ml-2"></i>
                        خدمات شما
                    </h2>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <?php if ($has_user_panel): $has_services_to_show = true; ?>
                            <a href="<?= htmlspecialchars(PANEL_URL) ?>" class="service-card block bg-gray-50 dark:bg-gray-700/50 border border-gray-200 dark:border-gray-700 rounded-xl p-5 transition-all duration-300">
                                <div class="flex items-center mb-4">
                                    <div class="p-3 rounded-lg bg-blue-600 text-white ml-4"><i class="fas fa-gamepad"></i></div>
                                    <h3 class="font-bold text-lg">پنل کاربری</h3>
                                </div>
                                <p class="text-gray-600 dark:text-gray-400 text-sm mb-4">دسترسی به پنل مرکزی کنترل سرور ها.</p>
                                <div class="flex items-center justify-between text-blue-600 dark:text-blue-400 text-sm font-semibold">
                                    <span>ورود به پنل</span><i class="fas fa-arrow-left"></i>
                                </div>
                            </a>
                        <?php endif; ?>
                        
                        <?php if ($is_owner): $has_services_to_show = true; ?>
                            <a href="../admin/admin_panel.php" class="service-card block bg-gray-50 dark:bg-gray-700/50 border border-gray-200 dark:border-gray-700 rounded-xl p-5 transition-all duration-300">
                                <div class="flex items-center mb-4">
                                    <div class="p-3 rounded-lg bg-purple-600 text-white ml-4"><i class="fas fa-user-shield"></i></div>
                                    <h3 class="font-bold text-lg">پنل مدیریت SSO</h3>
                                </div>
                                <p class="text-gray-600 dark:text-gray-400 text-sm mb-4">مدیریت کامل کاربران، دسترسی‌ها و تنظیمات سیستم.</p>
                                <div class="flex items-center justify-between text-purple-600 dark:text-purple-400 text-sm font-semibold">
                                    <span>ورود به مدیریت</span><i class="fas fa-arrow-left"></i>
                                </div>
                            </a>
                        <?php endif; ?>
                        
                        <?php if ($is_staff && $staff_is_verify && $has_developer_access): $has_services_to_show = true; ?>
                             <a href="/server_management/server_control.php" class="service-card block bg-gray-50 dark:bg-gray-700/50 border border-gray-200 dark:border-gray-700 rounded-xl p-5 transition-all duration-300">
                                <div class="flex items-center mb-4">
                                    <div class="p-3 rounded-lg bg-green-600 text-white ml-4"><i class="fas fa-server"></i></div>
                                    <h3 class="font-bold text-lg">کنترل سرور</h3>
                                </div>
                                <p class="text-gray-600 dark:text-gray-400 text-sm mb-4">ابزارهای مدیریتی و کنترل سرور (مخصوص DEV).</p>
                                <div class="flex items-center justify-between text-green-600 dark:text-green-400 text-sm font-semibold">
                                    <span>ورود به بخش</span><i class="fas fa-arrow-left"></i>
                                </div>
                            </a>
                        <?php endif; ?>

                        <?php if (!$has_services_to_show): ?>
                            <div class="col-span-1 md:col-span-2 lg:col-span-3 text-center py-10">
                                <i class="fas fa-box-open text-4xl text-gray-400 dark:text-gray-500"></i>
                                <p class="mt-4 text-gray-500 dark:text-gray-400">در حال حاضر سرویس فعالی برای شما وجود ندارد.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

            </main>
        </div>
    </div>
    
    <div id="mobile-sidebar" class="hidden fixed inset-0 z-40 md:hidden">
        <div class="fixed inset-0 bg-black/30" onclick="toggleMobileSidebar()"></div>
        <div class="relative flex flex-col w-72 max-w-xs h-full bg-white dark:bg-gray-800 border-l dark:border-gray-700">
             <div class="flex items-center justify-center h-16 border-b dark:border-gray-700">
                <img src="/assets/images/logo.png" alt="Logo" class="h-8 w-8 ml-2">
                <span class="text-gray-800 dark:text-white font-bold text-lg">SSO Center</span>
            </div>
            <nav class="flex-grow p-4 space-y-2">
                <a href="#" class="flex items-center px-4 py-2.5 text-sm font-medium rounded-lg sidebar-item active">
                    <i class="fas fa-home sidebar-icon w-6 text-center ml-3"></i>داشبورد
                </a>
                <a href="profile.php" class="flex items-center px-4 py-2.5 text-sm font-medium text-gray-600 dark:text-gray-400 rounded-lg sidebar-item hover:bg-gray-100 dark:hover:bg-gray-700">
                    <i class="fas fa-user-edit sidebar-icon w-6 text-center ml-3"></i>ویرایش پروفایل
                </a>
                <a href="passkey_management.php" class="flex items-center px-4 py-2.5 text-sm font-medium text-gray-600 dark:text-gray-400 rounded-lg sidebar-item hover:bg-gray-100 dark:hover:bg-gray-700">
                    <i class="fas fa-key sidebar-icon w-6 text-center ml-3"></i>مدیریت Passkey
                </a>
            </nav>
        </div>
    </div>

    <script>
        // --- UI Interaction Scripts ---
        const mobileSidebar = document.getElementById('mobile-sidebar');
        const userDropdown = document.getElementById('user-dropdown');
        
        function toggleMobileSidebar() {
            mobileSidebar.classList.toggle('hidden');
        }

        function toggleUserDropdown() {
            userDropdown.classList.toggle('hidden');
        }
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const userMenuButton = event.target.closest('.relative > button');
            if (!userMenuButton && !userDropdown.contains(event.target)) {
                userDropdown.classList.add('hidden');
            }
        }, true);

        // --- Theme Toggle Script ---
        const themeToggleBtn = document.getElementById('theme-toggle');
        const lightIcon = document.getElementById('theme-toggle-light-icon');
        const darkIcon = document.getElementById('theme-toggle-dark-icon');

        // Function to update icon state
        function updateThemeIcons() {
            if (document.documentElement.classList.contains('dark')) {
                lightIcon.classList.add('hidden');
                darkIcon.classList.remove('hidden');
            } else {
                lightIcon.classList.remove('hidden');
                darkIcon.classList.add('hidden');
            }
        }
        
        // Set initial icon state on page load
        updateThemeIcons();
        
        themeToggleBtn.addEventListener('click', () => {
            // toggle theme
            document.documentElement.classList.toggle('dark');
            
            // save preference to localStorage
            if (document.documentElement.classList.contains('dark')) {
                localStorage.setItem('theme', 'dark');
            } else {
                localStorage.setItem('theme', 'light');
            }
            
            // update icons
            updateThemeIcons();
        });
    </script>
</body>
</html>