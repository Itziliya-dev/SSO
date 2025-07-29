<?php
// ۱. شروع پردازش‌های سمت سرور
error_reporting(E_ALL);
ini_set('display_errors', 1);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require __DIR__.'/../vendor/autoload.php'; 

require_once __DIR__.'/../includes/config.php';
require_once __DIR__.'/../includes/database.php';

session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: /../login.php');
    exit();
}

$current_id_in_session = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'کاربر';
$user_type = $_SESSION['user_type'] ?? 'user';
$is_staff = ($user_type === 'staff');

$conn = getDbConnection();
if (!$conn || $conn->connect_error) {
    die("خطا در اتصال به دیتابیس.");
}

$message = '';
$message_type = '';
$profile_data = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_verification') {
    $user_email = $_POST['email_to_verify'];
    if (!filter_var($user_email, FILTER_VALIDATE_EMAIL)) {
        $message = "فرمت ایمیل نامعتبر است.";
        $message_type = 'error';
    } else {
        // ایجاد توکن و تاریخ انقضا
        $token = bin2hex(random_bytes(32));
        $expires_at = (new DateTime())->add(new DateInterval('PT1H'))->format('Y-m-d H:i:s'); // انقضا تا ۱ ساعت دیگر

        // ذخیره توکن در دیتابیس
        $table_to_update = $is_staff ? '`staff-manage`' : '`users`';
        $stmt_token = $conn->prepare("UPDATE $table_to_update SET verification_token = ?, token_expires_at = ? WHERE id = ?");
        $stmt_token->bind_param("ssi", $token, $expires_at, $current_id_in_session);
        
        if ($stmt_token->execute()) {
            // تنظیمات ارسال ایمیل با PHPMailer
            $mail = new PHPMailer(true);
            try {
                // اطلاعات SMTP شما
                $mail->isSMTP();
                $mail->Host       = 'smtp.c1.liara.email';
                $mail->SMTPAuth   = true;
                $mail->Username   = 'keen_panini_36oc86'; // یوزرنیم شما
                $mail->Password   = '7fd84fc1-f26c-4d28-aafa-2f505b94915f'; // پسورد شما
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // یا 'ssl'
                $mail->Port       = 587; // یا 465 برای SSL
                $mail->CharSet    = 'UTF-8';

                // فرستنده و گیرنده
                $mail->setFrom('no-reply@itziliya-dev.ir', 'SSO Center'); // ایمیل فرستنده را با دامنه خود جایگزین کنید
                $mail->addAddress($user_email, $username);

                // قالب و محتوای ایمیل (قالب حرفه‌ای برای جلوگیری از اسپم)
                $verification_link = "https://itziliya-dev.ir/auth/verify_email.php?token=" . $token; // آدرس دامنه خود را جایگزین کنید

                $emailBody = file_get_contents('email_template.html'); // یک فایل قالب برای خوانایی بهتر
                $emailBody = str_replace('{{username}}', htmlspecialchars($username), $emailBody);
                $emailBody = str_replace('{{verification_link}}', $verification_link, $emailBody);

                $mail->isHTML(true);
                $mail->Subject = 'تایید حساب کاربری شما در SSO Center';
                $mail->Body    = $emailBody;
                $mail->AltBody = 'برای تایید حساب خود، لطفا لینک زیر را در مرورگر خود باز کنید: ' . $verification_link;

                $mail->send();
                $message = 'ایمیل تایید با موفقیت به ' . htmlspecialchars($user_email) . ' ارسال شد.';
                $message_type = 'success';
            } catch (Exception $e) {
                $message = "خطا در ارسال ایمیل: {$mail->ErrorInfo}";
                $message_type = 'error';
            }
        } else {
            $message = "خطا در ذخیره توکن در دیتابیس.";
            $message_type = 'error';
        }
    }
}

// ۲. واکشی اطلاعات فعلی کاربر/استف
$sql_fetch = '';
if ($is_staff) {
    $sql_fetch = "SELECT id, username, password, fullname, email, phone, age, discord_id, discord_id2, steam_id, permissions, is_verify, discord_conn FROM `staff-manage` WHERE id = ?";
} else {
    $sql_fetch = "SELECT id, username, password, fullname, email, phone, is_owner, has_user_panel FROM `users` WHERE id = ?";
}

$stmt_fetch = $conn->prepare($sql_fetch);
if ($stmt_fetch) {
    $stmt_fetch->bind_param("i", $current_id_in_session);
    $stmt_fetch->execute();
    $result_fetch = $stmt_fetch->get_result();
    $profile_data = $result_fetch->fetch_assoc();
    $stmt_fetch->close();
}

if (!$profile_data) {
    die("خطا: اطلاعات کاربر یافت نشد.");
}

// ۳. پردازش فرم آپدیت
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $posted_fullname = $_POST['fullname'] ?? $profile_data['fullname'];
    $posted_email = $_POST['email'] ?? $profile_data['email'];
    $posted_phone = $_POST['phone'] ?? $profile_data['phone'];
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    $update_sql_parts = [];
    $params_update = [];
    $types_update = "";
    $table_to_update = $is_staff ? '`staff-manage`' : '`users`';

    // مقایسه و افزودن فیلدهای تغییر یافته
    if ($posted_fullname !== $profile_data['fullname']) { $update_sql_parts[] = "fullname = ?"; $params_update[] = $posted_fullname; $types_update .= "s"; }
    if ($posted_email !== $profile_data['email']) { $update_sql_parts[] = "email = ?"; $params_update[] = $posted_email; $types_update .= "s"; }
    if ($posted_phone !== $profile_data['phone']) { $update_sql_parts[] = "phone = ?"; $params_update[] = $posted_phone; $types_update .= "s"; }

    if ($is_staff) {
        $posted_staff_age = $_POST['staff_age'] ?? $profile_data['age'];
        $posted_staff_steam_id = $_POST['staff_steam_id'] ?? $profile_data['steam_id'];
        if ($posted_staff_age != $profile_data['age']) { $update_sql_parts[] = "age = ?"; $params_update[] = $posted_staff_age; $types_update .= "i"; }
        if ($posted_staff_steam_id != $profile_data['steam_id']) { $update_sql_parts[] = "steam_id = ?"; $params_update[] = $posted_staff_steam_id; $types_update .= "s"; }
    }

    // بررسی و آپدیت رمز عبور
    if (!empty($new_password)) {
        if ($new_password === $confirm_password) {
            $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);
            $update_sql_parts[] = "password = ?";
            $params_update[] = $hashed_password;
            $types_update .= "s";
        } else {
            $message = "رمزهای عبور جدید مطابقت ندارند.";
            $message_type = 'error';
        }
    }

    if (empty($message) && !empty($update_sql_parts)) {
        $conn->begin_transaction();
        try {
            $update_sql = "UPDATE $table_to_update SET " . implode(", ", $update_sql_parts) . " WHERE id = ?";
            $params_update[] = $current_id_in_session;
            $types_update .= "i";
            
            $stmt_update = $conn->prepare($update_sql);
            $stmt_update->bind_param($types_update, ...$params_update);
            $stmt_update->execute();
            $stmt_update->close();
            
            $conn->commit();
            $message = "اطلاعات با موفقیت بروزرسانی شد.";
            $message_type = 'success';

            // خواندن مجدد اطلاعات برای نمایش مقادیر جدید
            $stmt_fetch_again = $conn->prepare($sql_fetch);
            $stmt_fetch_again->bind_param("i", $current_id_in_session);
            $stmt_fetch_again->execute();
            $profile_data = $stmt_fetch_again->get_result()->fetch_assoc();
            $stmt_fetch_again->close();

        } catch (Exception $e) {
            $conn->rollback();
            $message = "خطا در بروزرسانی: " . $e->getMessage();
            $message_type = 'error';
        }
    } elseif (empty($message)) {
        $message = "هیچ تغییری برای بروزرسانی وجود نداشت.";
        $message_type = 'info';
    }
}

// ۴. آماده‌سازی متغیرهای نمایشی برای قالب (بخش‌هایی که از داشبورد کپی شده)
$final_avatar_src = '/assets/images/logo.png'; // آواتار پیش‌فرض
if ($is_staff && !empty($profile_data['discord_id2'])) {
    // برای سادگی، تابع دریافت آواتار را اینجا دوباره تعریف یا include می‌کنیم
    function get_discord_avatar_url_for_profile($discord_id) {
        if (empty($discord_id)) return null;
        $webhook_url = 'http://83.149.95.39:1030/get-avatar-url?discord_id=' . urlencode($discord_id);
        $secret_token = '4a97dd86-4388-4cc0-a54f-65ebbf51649d'; // توکن شما
        $ch = curl_init();
        curl_setopt_array($ch, [CURLOPT_URL => $webhook_url, CURLOPT_RETURNTRANSFER => 1, CURLOPT_TIMEOUT => 2, CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $secret_token]]);
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($http_code == 200) {
            $data = json_decode($response, true);
            return $data['avatar_url'] ?? null;
        }
        return null;
    }
    $discord_avatar_url = get_discord_avatar_url_for_profile($profile_data['discord_id2']);
    if ($discord_avatar_url) {
        $final_avatar_src = '../admin/image_proxy.php?url=' . urlencode($discord_avatar_url);
    }
}
$user_role_display = $is_staff ? 'حساب کاربری استف' : 'حساب کاربری';

$conn->close();
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl" class="">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ویرایش پروفایل | SSO Center</title>
    <link rel="stylesheet" href="/assets/fonts/Vazirmatn-font-face.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @font-face {
            font-family: 'Vazirmatn';
            src: url('/assets/fonts/Vazirmatn-Regular.woff2') format('woff2'); /* مسیر فونت خود را تنظیم کنید */
            font-weight: normal;
            font-style: normal;
        }
        body { font-family: 'Vazirmatn', sans-serif; }
        .sidebar-item.active { background-color: rgba(59, 130, 246, 0.1); border-right: 3px solid #3b82f6; color: #3b82f6; }
        .sidebar-item.active .sidebar-icon { color: #3b82f6; }
        html.dark .sidebar-item.active { background-color: rgba(96, 165, 250, 0.2); border-right-color: #60a5fa; color: #93c5fd; }
        html.dark .sidebar-item.active .sidebar-icon { color: #93c5fd; }
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
                <a href="dashboard.php" class="flex items-center px-4 py-2.5 text-sm font-medium text-gray-600 dark:text-gray-400 rounded-lg sidebar-item hover:bg-gray-100 dark:hover:bg-gray-700">
                    <i class="fas fa-home sidebar-icon w-6 text-center ml-3"></i>
                    داشبورد
                </a>
                <a href="#" class="flex items-center px-4 py-2.5 text-sm font-medium rounded-lg sidebar-item active">
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
            <header class="flex items-center justify-between h-16 px-4 sm:px-6 bg-white dark:bg-gray-800 border-b dark:border-gray-700">
                <div class="flex items-center">
                    <button class="md:hidden text-gray-500 dark:text-gray-400 focus:outline-none" onclick="toggleMobileSidebar()">
                        <i class="fas fa-bars text-xl"></i>
                    </button>
                    <h1 class="text-lg font-medium mr-4 hidden sm:block">ویرایش پروفایل</h1>
                </div>
                
                <div class="flex items-center space-x-4">
                    <button id="theme-toggle" class="relative text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 focus:outline-none">
                        <i class="fas fa-sun text-xl" id="theme-toggle-light-icon"></i>
                        <i class="fas fa-moon text-xl hidden" id="theme-toggle-dark-icon"></i>
                    </button>
                    <div class="relative">
                        <button class="flex items-center focus:outline-none" onclick="toggleUserDropdown()">
                            <img src="<?= htmlspecialchars($final_avatar_src) ?>" alt="User" class="w-8 h-8 rounded-full">
                        </button>
                        <div id="user-dropdown" class="hidden absolute left-0 mt-2 w-48 bg-white dark:bg-gray-800 rounded-md shadow-lg py-1 z-50 border dark:border-gray-700">
                            <a href="logout.php" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">
                                <i class="fas fa-sign-out-alt ml-2"></i> خروج از حساب
                            </a>
                        </div>
                    </div>
                </div>
            </header>
            
            <main class="flex-1 overflow-y-auto p-4 md:p-6">
                
                <?php if ($message): ?>
                    <div class="mb-6 p-4 rounded-lg text-sm text-center
                        <?php if ($message_type === 'success'): ?> bg-green-100 dark:bg-green-900/50 text-green-700 dark:text-green-300 <?php endif; ?>
                        <?php if ($message_type === 'error'): ?> bg-red-100 dark:bg-red-900/50 text-red-700 dark:text-red-300 <?php endif; ?>
                        <?php if ($message_type === 'info'): ?> bg-blue-100 dark:bg-blue-900/50 text-blue-700 dark:text-blue-300 <?php endif; ?>
                    ">
                        <?= htmlspecialchars($message) ?>
                    </div>
                <?php endif; ?>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    
                    <?php if ($is_staff): ?>
                    <div class="lg:col-span-1 bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
                        <h2 class="text-lg font-bold text-gray-800 dark:text-white mb-4 flex items-center">
                            <i class="fab fa-discord text-indigo-500 ml-3"></i>
                            اتصال دیسکورد
                        </h2>
                        <div class="space-y-3 text-sm">
                            <p class="text-gray-600 dark:text-gray-400"><strong class="font-medium text-gray-700 dark:text-gray-300">نام کاربری:</strong> <?= htmlspecialchars($profile_data['discord_id'] ?: '---') ?></p>
                            <p class="text-gray-600 dark:text-gray-400"><strong class="font-medium text-gray-700 dark:text-gray-300">آیدی عددی:</strong> <?= htmlspecialchars($profile_data['discord_id2'] ?: '---') ?></p>
                            <p class="text-gray-600 dark:text-gray-400"><strong class="font-medium text-gray-700 dark:text-gray-300">مقام‌ها:</strong> <?= htmlspecialchars($profile_data['permissions'] ?: '---') ?></p>
                             <p class="text-gray-600 dark:text-gray-400 flex items-center">
                                <strong class="font-medium text-gray-700 dark:text-gray-300 ml-2">وضعیت:</strong>
                                <?php if (!empty($profile_data['discord_conn'])): ?>
                                    <span class="font-semibold text-green-600 dark:text-green-400">متصل و تایید شده</span>
                                <?php else: ?>
                                    <span class="font-semibold text-yellow-600 dark:text-yellow-400">در انتظار تایید</span>
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="lg:col-span-2 bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
                        <form method="POST" class="space-y-6">
                            <div>
                                <h3 class="text-md font-semibold text-gray-800 dark:text-white border-b dark:border-gray-700 pb-2 mb-4">اطلاعات پایه</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div class="form-group">
                                        <label for="username" class="block text-sm font-medium text-gray-600 dark:text-gray-400 mb-1">نام کاربری (غیرقابل تغییر)</label>
                                        <input type="text" id="username" name="username" class="w-full p-2 bg-gray-100 dark:bg-gray-700/50 border border-gray-300 dark:border-gray-600 rounded-md cursor-not-allowed" value="<?= htmlspecialchars($profile_data['username']) ?>" readonly>
                                    </div>
                                    <div class="form-group">
                                        <label for="fullname" class="block text-sm font-medium text-gray-600 dark:text-gray-400 mb-1">نام کامل</label>
                                        <input type="text" id="fullname" name="fullname" class="w-full p-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500" value="<?= htmlspecialchars($profile_data['fullname'] ?? '') ?>">
                                    </div>
                                    <div class="form-group">
                                        <label for="phone" class="block text-sm font-medium text-gray-600 dark:text-gray-400 mb-1">تلفن</label>
                                        <input type="tel" id="phone" name="phone" class="w-full p-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500" value="<?= htmlspecialchars($profile_data['phone'] ?? '') ?>">
                                    </div>
                                    <div class="form-group">
                                        <label for="email" class="block text-sm font-medium text-gray-600 dark:text-gray-400 mb-1">ایمیل</label>
                                        <div class="flex items-center">
                                            <input type="email" id="email" name="email" class="w-full p-2 bg-white dark:bg-gray-700 border rounded-r-md" value="<?= htmlspecialchars($profile_data['email'] ?? '') ?>">
                                            <span class="px-3 py-2 text-xs font-bold text-white rounded-l-md whitespace-nowrap
                                                <?= !empty($profile_data['email_verified_at']) ? 'bg-green-600' : 'bg-yellow-600' ?>">
                                                <?= !empty($profile_data['email_verified_at']) ? 'تایید شده' : 'تایید نشده' ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <?php if ($is_staff): ?>
                            <div>
                                <h3 class="text-md font-semibold text-gray-800 dark:text-white border-b dark:border-gray-700 pb-2 mb-4">اطلاعات تکمیلی استف</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div class="form-group">
                                        <label for="staff_age" class="block text-sm font-medium text-gray-600 dark:text-gray-400 mb-1">سن</label>
                                        <input type="number" id="staff_age" name="staff_age" class="w-full p-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md" value="<?= htmlspecialchars($profile_data['age'] ?? '') ?>">
                                    </div>
                                    <div class="form-group">
                                        <label for="staff_steam_id" class="block text-sm font-medium text-gray-600 dark:text-gray-400 mb-1">آیدی استیم</label>
                                        <input type="text" id="staff_steam_id" name="staff_steam_id" class="w-full p-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md" value="<?= htmlspecialchars($profile_data['steam_id'] ?? '') ?>">
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>

                            <div>
                                <h3 class="text-md font-semibold text-gray-800 dark:text-white border-b dark:border-gray-700 pb-2 mb-4">تغییر رمز عبور</h3>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">این بخش را فقط در صورت نیاز به تغییر رمز عبور پر کنید.</p>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div class="form-group">
                                        <label for="new_password" class="block text-sm font-medium text-gray-600 dark:text-gray-400 mb-1">رمز عبور جدید</label>
                                        <input type="password" id="new_password" name="new_password" class="w-full p-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md" autocomplete="new-password">
                                    </div>
                                    <div class="form-group">
                                        <label for="confirm_password" class="block text-sm font-medium text-gray-600 dark:text-gray-400 mb-1">تکرار رمز عبور</label>
                                        <input type="password" id="confirm_password" name="confirm_password" class="w-full p-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md" autocomplete="new-password">
                                    </div>
                                </div>
                            </div>
                            <div class="flex items-center justify-between pt-4 border-t dark:border-gray-700">
                            <?php if (empty($profile_data['email_verified_at']) && !empty($profile_data['email'])): ?>
                                <form method="POST" class="m-0">
                                    <input type="hidden" name="action" value="send_verification">
                                    <input type="hidden" name="email_to_verify" value="<?= htmlspecialchars($profile_data['email']) ?>">
                                    <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700">
                                        <i class="fas fa-envelope ml-2"></i>ارسال ایمیل تایید
                                    </button>
                                </form>
                            <?php endif; ?>
                            <div class="flex items-center justify-end space-x-4 space-x-reverse pt-4 border-t dark:border-gray-700">
                                <a href="dashboard.php" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-600">بازگشت</a>
                                <button type="submit" class="px-6 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                    <i class="fas fa-save ml-2"></i>
                                    ذخیره تغییرات
                                </button>
                            </div>
                        </form>
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
                <a href="dashboard.php" class="flex items-center px-4 py-2.5 text-sm font-medium text-gray-600 dark:text-gray-400 rounded-lg sidebar-item hover:bg-gray-100 dark:hover:bg-gray-700">
                    <i class="fas fa-home sidebar-icon w-6 text-center ml-3"></i>داشبورد
                </a>
                <a href="#" class="flex items-center px-4 py-2.5 text-sm font-medium rounded-lg sidebar-item active">
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
        function toggleMobileSidebar() { mobileSidebar.classList.toggle('hidden'); }
        function toggleUserDropdown() { userDropdown.classList.toggle('hidden'); }
        document.addEventListener('click', function(event) {
            const userMenuButton = event.target.closest('.relative > button');
            if (!userMenuButton && userDropdown && !userDropdown.contains(event.target)) {
                userDropdown.classList.add('hidden');
            }
        }, true);

        // --- Theme Toggle Script ---
        const themeToggleBtn = document.getElementById('theme-toggle');
        const lightIcon = document.getElementById('theme-toggle-light-icon');
        const darkIcon = document.getElementById('theme-toggle-dark-icon');
        function updateThemeIcons() {
            if (document.documentElement.classList.contains('dark')) {
                lightIcon.classList.add('hidden');
                darkIcon.classList.remove('hidden');
            } else {
                lightIcon.classList.remove('hidden');
                darkIcon.classList.add('hidden');
            }
        }
        updateThemeIcons();
        themeToggleBtn.addEventListener('click', () => {
            document.documentElement.classList.toggle('dark');
            localStorage.setItem('theme', document.documentElement.classList.contains('dark') ? 'dark' : 'light');
            updateThemeIcons();
        });
    </script>
</body>
</html>