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
$permissions = $stmt->get_result()->fetch_assoc() ?: [];
$stmt->close();

// ۴. تنظیم متغیرهای دسترسی
$is_owner = !empty($permissions['is_owner']);
$has_user_panel = !empty($permissions['has_user_panel']);
$has_developer_access = !empty($permissions['has_developer_access']);

// ۵. واکشی اطلاعات تکمیلی کاربر/استف (شامل آواتار)
$avatar_url = null;
$staff_info = []; 
if ($is_staff) {
    $stmt = $conn->prepare("SELECT is_verify, last_login, discord_conn, discord_id2, avatar_url, permissions FROM `staff-manage` WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $staff_info = $stmt->get_result()->fetch_assoc() ?: [];
    $stmt->close();
    $avatar_url = $staff_info['avatar_url'] ?? null;
} else {
    $stmt = $conn->prepare("SELECT avatar_url FROM `users` WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user_info = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $avatar_url = $user_info['avatar_url'] ?? null;
}

// تابع کمکی برای دریافت آواتار دیسکورد
function get_discord_avatar_url_from_bot($discord_id) {
    if (empty($discord_id)) return null;
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

// ۶. آماده‌سازی متغیرهای نهایی برای نمایش در صفحه
$default_avatar = 'https://ui-avatars.com/api/?name=' . urlencode($username) . '&background=ffd700&color=0f0f23&bold=true';
$final_avatar_src = $default_avatar;

// اولویت اول: آواتار آپلود شده
if (!empty($avatar_url)) {
    $final_avatar_src = '../uploads/avatars/' . htmlspecialchars($avatar_url);
}
// اولویت دوم: آواتار دیسکورد برای استف‌ها
else if ($is_staff && !empty($staff_info['discord_id2'])) {
    $discord_avatar_url = get_discord_avatar_url_from_bot($staff_info['discord_id2']);
    if ($discord_avatar_url) {
        $final_avatar_src = '../admin/image_proxy.php?url=' . urlencode($discord_avatar_url);
    }
}

// تعریف متغیرهای دیگر برای نمایش
$staff_display_permissions = 'فاقد مقام';
$staff_is_verify = 0;
$staff_last_login_formatted = 'نامشخص';
$discord_conn_status_key = 'unknown';
$has_services_to_show = false;
$welcome_message = 'از این داشبورد می‌توانید به خدماتی که برای شما فعال شده استفاده کنید. در صورت نیاز به راهنمایی با منیجرها در تماس باشید.';
$user_role_display = $is_staff ? 'حساب کاربری استف' : 'حساب کاربری';

// پردازش اطلاعات مخصوص استف (اگر کاربر استف بود)
if ($is_staff && !empty($staff_info)) {
    $staff_is_verify = $staff_info['is_verify'] ?? 0;
    $discord_conn_status_key = ($staff_info['discord_conn'] == 1) ? 'connected' : 'not_connected';

    if (!empty($staff_info['last_login'])) {
        try {
            $date = new DateTime($staff_info['last_login']);
            $staff_last_login_formatted = $date->format('Y/m/d ساعت H:i');
        } catch (Exception $e) {
            $staff_last_login_formatted = 'تاریخ نامعتبر';
        }
    }
    
if (!empty($staff_info['permissions'])) {
    $staff_display_permissions = $staff_info['permissions'];
} 
// در صورتی که ستون بالا خالی بود، از منطق قبلی استفاده می‌شود
elseif ($has_developer_access) {
    $staff_display_permissions = 'توسعه‌دهنده (Dev)';
} elseif ($is_owner) {
    $staff_display_permissions = 'مالک';
} else {
    $staff_display_permissions = 'استف';
}
    
    if (!$staff_is_verify) {
        $welcome_message = 'حساب کاربری استف شما هنوز تایید نشده است. تا زمان تایید، دسترسی شما محدود خواهد بود.';
    } elseif ($discord_conn_status_key !== 'connected') {
        $welcome_message = 'برای استفاده از تمامی امکانات، حساب دیسکورد خود را متصل کنید. (دستور /connect در سرور)';
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>داشبورد | SSO Center</title>
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
                        'yellow-secondary': '#ffed4e',
                        'yellow-dark': '#b7791f'
                    }
                }
            }
        }
    </script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;500;600;700&display=swap');
        
        body {
            font-family: 'Vazirmatn', sans-serif;
        }

        .gradient-bg {
            background: linear-gradient(135deg, #0f0f23 0%, #1a1a2e 50%, #16213e 100%);
        }

        .glow-effect {
            box-shadow: 0 0 20px rgba(255, 215, 0, 0.3);
        }

        .card-hover {
            transition: all 0.3s ease;
        }

        .card-hover:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(255, 215, 0, 0.2);
        }

            .card-blue:hover {
                border-color: rgba(59, 130, 246, 0.4);
                box-shadow: 0 8px 25px rgba(59, 130, 246, 0.2);
            }
            .card-purple:hover {
                border-color: rgba(168, 85, 247, 0.4);
                box-shadow: 0 8px 25px rgba(168, 85, 247, 0.2);
            }
            .card-orange:hover {
                border-color: rgba(249, 115, 22, 0.4);
                box-shadow: 0 8px 25px rgba(249, 115, 22, 0.2);
            }
            .card-green:hover {
                border-color: rgba(34, 197, 94, 0.4);
                box-shadow: 0 8px 25px rgba(34, 197, 94, 0.2);
            }


        .sidebar-item {
            transition: all 0.3s ease;
            border-right: 3px solid transparent;
        }
        
        .sidebar-item:hover, .sidebar-item.active {
            background: linear-gradient(90deg, rgba(255, 215, 0, 0.07) 0%, transparent 100%);
            border-right-color: #ffd700;
            color: #ffd700;
        }
        .sidebar-item.active {
            background: linear-gradient(90deg, rgba(255, 215, 0, 0.1) 0%, transparent 100%);
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in { animation: fadeIn 0.5s ease-out forwards; }
        .delay-1 { animation-delay: 0.1s; }
        .delay-2 { animation-delay: 0.2s; }
        .delay-3 { animation-delay: 0.3s; }
        .delay-4 { animation-delay: 0.4s; }
    </style>
</head>
<body class="gradient-bg text-gray-200">
    <div class="flex h-screen">
        <aside id="sidebar" class="fixed right-0 top-0 h-full w-64 bg-dark-secondary shadow-2xl z-50 transform translate-x-full md:translate-x-0 transition-transform duration-300 flex flex-col">
            <div class="p-6 border-b border-yellow-primary/20">
                <div class="flex items-center space-x-3 space-x-reverse">
                    <div class="w-12 h-12 bg-gradient-to-br from-yellow-primary to-yellow-dark rounded-full flex items-center justify-center glow-effect">
                         <img src="/assets/images/logo.png" alt="Logo" class="h-12 w-12 rounded-full">
                    </div>
                    <div>
                        <h2 class="text-yellow-primary font-bold text-lg">SSO Center</h2>
                        <p class="text-gray-400 text-sm">داشبورد کاربری</p>
                    </div>
                </div>
            </div>
            <nav class="flex-grow p-4 space-y-2">
                <a href="#" class="flex items-center px-4 py-2.5 text-sm font-medium rounded-lg sidebar-item active">
                    <i class="fas fa-home w-6 text-center ml-3"></i>
                    داشبورد
                </a>
                <a href="profile.php" class="flex items-center px-4 py-2.5 text-sm font-medium text-gray-300 rounded-lg sidebar-item">
                    <i class="fas fa-user-edit w-6 text-center ml-3"></i>
                    ویرایش پروفایل
                </a>
                <a href="passkey_management.php" class="flex items-center px-4 py-2.5 text-sm font-medium text-gray-300 rounded-lg sidebar-item">
                    <i class="fas fa-key w-6 text-center ml-3"></i>
                    مدیریت Passkey
                </a>
            </nav>
            <div class="p-4">
                <div class="bg-dark-tertiary rounded-lg p-4 border border-yellow-primary/20">
                    <div class="flex items-center">
                        <img src="<?= htmlspecialchars($final_avatar_src) ?>" alt="User Avatar" class="w-10 h-10 rounded-full">
                        <div class="mr-3">
                            <p class="text-sm font-medium text-yellow-primary"><?= htmlspecialchars($username) ?></p>
                            <p class="text-xs text-gray-400"><?= htmlspecialchars($user_role_display) ?></p>
                        </div>
                    </div>
                    <a href="logout.php" class="block mt-4 text-center text-sm text-dark-primary bg-gradient-to-r from-yellow-primary to-yellow-dark hover:shadow-lg hover:shadow-yellow-primary/20 font-semibold py-2 rounded-lg transition-all">
                        <i class="fas fa-sign-out-alt ml-2"></i>
                        خروج از حساب
                    </a>
                </div>
            </div>
        </aside>

        <div id="sidebar-overlay" class="fixed inset-0 bg-black bg-opacity-50 z-30 hidden md:hidden" onclick="toggleMobileSidebar()"></div>

            <div class="flex flex-col flex-1 md:mr-64">
                <header class="relative z-10 bg-dark-secondary/50 backdrop-blur-sm border-b border-yellow-primary/20 flex items-center justify-between h-16 px-4 sm:px-6">
                <div class="flex items-center">
                    <button class="md:hidden text-yellow-primary focus:outline-none" onclick="toggleMobileSidebar()">
                        <i class="fas fa-bars text-xl"></i>
                    </button>
                     <h1 class="text-lg font-bold text-yellow-primary mr-4 hidden sm:block">داشبورد</h1>
                </div>
                
                <div class="flex items-center space-x-4 space-x-reverse">
                    <button class="relative bg-dark-tertiary p-2 rounded-full text-yellow-primary hover:bg-yellow-primary hover:text-dark-primary transition-all glow-effect">
                        <i class="fas fa-bell text-lg"></i>
                    </button>
                    
                    <div class="relative">
                        <button class="flex items-center focus:outline-none" onclick="toggleUserDropdown()">
                            <img src="<?= htmlspecialchars($final_avatar_src) ?>" alt="User" class="w-8 h-8 rounded-full">
                        </button>
                        
                        <div id="user-dropdown" class="hidden absolute left-0 mt-2 w-48 bg-dark-secondary rounded-md shadow-lg py-1 z-50 border border-yellow-primary/20">
                            <a href="profile.php" class="block px-4 py-2 text-sm text-gray-300 hover:text-yellow-primary"><i class="fas fa-user-edit ml-2"></i> ویرایش پروفایل</a>
                            <a href="passkey_management.php" class="block px-4 py-2 text-sm text-gray-300 hover:text-yellow-primary"><i class="fas fa-key ml-2"></i> مدیریت Passkey</a>
                            <div class="border-t border-yellow-primary/20 my-1"></div>
                            <a href="logout.php" class="block px-4 py-2 text-sm text-gray-300 hover:text-yellow-primary"><i class="fas fa-sign-out-alt ml-2"></i> خروج</a>
                        </div>
                    </div>
                </div>
            </header>
            
            <main class="flex-1 overflow-y-auto p-4 md:p-6">
                <div class="bg-dark-secondary rounded-xl shadow-lg border border-yellow-primary/20 p-6 mb-6 animate-fade-in glow-effect">
                    <h2 class="text-xl font-bold text-yellow-primary">خوش آمدید، <?= htmlspecialchars($username) ?>!</h2>
                    <p class="text-gray-400 mt-2"><?= htmlspecialchars($welcome_message) ?></p>
                </div>
                
                <?php if ($is_staff): ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                    <div class="bg-dark-secondary rounded-xl p-4 border border-yellow-primary/20 card-hover animate-fade-in delay-1">
                         <div class="flex items-center">
                            <div class="p-3 rounded-full bg-yellow-primary/20 text-yellow-primary ml-4"><i class="fas fa-user-tag"></i></div>
                            <div>
                                <p class="text-sm text-gray-400">مقام</p>
                                <p class="font-bold text-yellow-primary"><?= htmlspecialchars($staff_display_permissions) ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-dark-secondary rounded-xl p-4 border <?= $staff_is_verify ? 'border-green-400/30' : 'border-yellow-primary/20' ?> card-hover animate-fade-in delay-2">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full <?= $staff_is_verify ? 'bg-green-500/20 text-green-400' : 'bg-yellow-primary/20 text-yellow-primary' ?> ml-4"><i class="fas fa-shield-alt"></i></div>
                            <div>
                                <p class="text-sm text-gray-400">وضعیت</p>
                                <p class="font-bold <?= $staff_is_verify ? 'text-green-400' : 'text-yellow-primary' ?>"><?= $staff_is_verify ? 'تایید شده' : 'در انتظار تایید' ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-dark-secondary rounded-xl p-4 border border-yellow-primary/20 card-hover animate-fade-in delay-3">
                         <div class="flex items-center">
                            <div class="p-3 rounded-full bg-yellow-primary/20 text-yellow-primary ml-4"><i class="fas fa-history"></i></div>
                            <div>
                                <p class="text-sm text-gray-400">آخرین ورود</p>
                                <p class="font-bold text-yellow-primary text-xs"><?= htmlspecialchars($staff_last_login_formatted) ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-dark-secondary rounded-xl p-4 border <?= $discord_conn_status_key === 'connected' ? 'border-indigo-400/30' : 'border-red-500/30' ?> card-hover animate-fade-in delay-4">
                         <div class="flex items-center">
                            <div class="p-3 rounded-full <?= $discord_conn_status_key === 'connected' ? 'bg-indigo-500/20 text-indigo-400' : 'bg-red-500/20 text-red-400' ?> ml-4"><i class="fab fa-discord"></i></div>
                            <div>
                                <p class="text-sm text-gray-400">دیسکورد</p>
                                <p class="font-bold <?= $discord_conn_status_key === 'connected' ? 'text-indigo-400' : 'text-red-400' ?>"><?= $discord_conn_status_key === 'connected' ? 'متصل' : 'متصل نشده' ?></p>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
<div class="bg-dark-secondary rounded-xl shadow-lg border border-yellow-primary/20 p-6 animate-fade-in delay-3">
    <h2 class="text-lg font-bold text-yellow-primary mb-6">
        <i class="fas fa-th-large ml-2"></i>
        خدمات شما
    </h2>
    
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php if ($has_user_panel): $has_services_to_show = true; ?>
            <a href="<?= htmlspecialchars(PANEL_URL) ?>" class="card-hover card-blue block bg-dark-tertiary border border-gray-700/50 rounded-xl p-5 transition-all duration-300">
                <div class="flex items-center mb-4">
                    <div class="p-3 rounded-lg bg-blue-600/80 text-white ml-4"><i class="fas fa-gamepad"></i></div>
                    <h3 class="font-bold text-lg text-gray-200">پنل مرکزی</h3>
                </div>
                <p class="text-gray-400 text-sm mb-4">دسترسی به پنل مرکزی کنترل سرور ها.</p>
                <div class="flex items-center justify-between text-blue-400 text-sm font-semibold">
                    <span>ورود به پنل</span><i class="fas fa-arrow-left"></i>
                </div>
            </a>
        <?php endif; ?>
        
        <?php if ($is_owner): $has_services_to_show = true; ?>
            <a href="../admin/admin_panel.php" class="card-hover card-purple block bg-dark-tertiary border border-gray-700/50 rounded-xl p-5 transition-all duration-300">
                <div class="flex items-center mb-4">
                    <div class="p-3 rounded-lg bg-purple-600/80 text-white ml-4"><i class="fas fa-user-shield"></i></div>
                    <h3 class="font-bold text-lg text-gray-200">پنل مدیریت SSO</h3>
                </div>
                <p class="text-gray-400 text-sm mb-4">مدیریت کامل کاربران، ارشیو محرمانه، دسترسی‌ها و تنظیمات سیستم.</p>
                <div class="flex items-center justify-between text-purple-400 text-sm font-semibold">
                    <span>ورود به مدیریت</span><i class="fas fa-arrow-left"></i>
                </div>
            </a>
        <?php endif; ?>
        
        <?php if ($has_user_panel): $has_services_to_show = true; ?>
            <a href="https://oss.itziliya-dev.ir" target="_blank" class="card-hover card-orange block bg-dark-tertiary border border-gray-700/50 rounded-xl p-5 transition-all duration-300">
            <div class="flex items-center mb-4">
                <div class="p-3 rounded-lg bg-orange-600/80 text-white ml-4"><i class="fas fa-server"></i></div>
                <h3 class="font-bold text-lg text-gray-200">پنل ورود اعضا پشتیبانی (OSS)</h3>
            </div>
            <p class="text-gray-400 text-sm mb-4">مدیریت افراد، سوابق مجازات ها، تایید هویت اشخاص.</p>
            <div class="flex items-center justify-between text-orange-400 text-sm font-semibold">
                <span>ورود به پنل OSS</span><i class="fas fa-arrow-left"></i>
            </div>
        </a>
        <?php endif; ?>
        
        <?php if ($is_staff && $staff_is_verify && $has_developer_access): $has_services_to_show = true; ?>
            <a href="/server_management/server_control.php" class="card-hover card-green block bg-dark-tertiary border border-gray-700/50 rounded-xl p-5 transition-all duration-300">
                <div class="flex items-center mb-4">
                    <div class="p-3 rounded-lg bg-green-600/80 text-white ml-4"><i class="fas fa-server"></i></div>
                    <h3 class="font-bold text-lg text-gray-200">کنترل سرور</h3>
                </div>
                <p class="text-gray-400 text-sm mb-4">ابزارهای مدیریتی و کنترل بحران (مخصوص DEV).</p>
                <div class="flex items-center justify-between text-green-400 text-sm font-semibold">
                    <span>ورود به بخش</span><i class="fas fa-arrow-left"></i>
                </div>
            </a>
        <?php endif; ?>

        <?php if (!$has_services_to_show): ?>
            <div class="col-span-1 md:col-span-2 lg:col-span-3 text-center py-10">
                <i class="fas fa-box-open text-4xl text-gray-500"></i>
                <p class="mt-4 text-gray-400">در حال حاضر سرویس فعالی برای شما وجود ندارد.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

            </main>
        </div>
    </div>
    
    <script>
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebar-overlay');
        const userDropdown = document.getElementById('user-dropdown');
        
        function toggleMobileSidebar() {
            sidebar.classList.toggle('translate-x-full');
            overlay.classList.toggle('hidden');
        }

        function toggleUserDropdown() {
            userDropdown.classList.toggle('hidden');
        }
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const userMenuButton = event.target.closest('.relative > button');
            if (!userMenuButton && userDropdown && !userDropdown.contains(event.target)) {
                userDropdown.classList.add('hidden');
            }
        }, true);
    </script>
</body>
</html>