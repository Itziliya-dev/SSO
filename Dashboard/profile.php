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
        $token = bin2hex(random_bytes(32));
        $expires_at = (new DateTime())->add(new DateInterval('PT1H'))->format('Y-m-d H:i:s');
        $table_to_update = $is_staff ? '`staff-manage`' : '`users`';
        $stmt_token = $conn->prepare("UPDATE $table_to_update SET verification_token = ?, token_expires_at = ? WHERE id = ?");
        $stmt_token->bind_param("ssi", $token, $expires_at, $current_id_in_session);
        
        if ($stmt_token->execute()) {
            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host       = 'smtp.c1.liara.email';
                $mail->SMTPAuth   = true;
                $mail->Username   = 'keen_panini_36oc86';
                $mail->Password   = '7fd84fc1-f26c-4d28-aafa-2f505b94915f';
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = 587;
                $mail->CharSet    = 'UTF-8';

                $mail->setFrom('no-reply@itziliya-dev.ir', 'SSO Center');
                $mail->addAddress($user_email, $username);

                $verification_link = "https://itziliya-dev.ir/auth/verify_email.php?token=" . $token;
                $emailBody = file_get_contents('email_template.html');
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
    $sql_fetch = "SELECT id, username, password, fullname, email, email_verified_at, phone, age, discord_id, discord_id2, steam_id, permissions, is_verify, discord_conn, avatar_url FROM `staff-manage` WHERE id = ?";
} else {
    $sql_fetch = "SELECT id, username, password, fullname, email, email_verified_at, phone, is_owner, has_user_panel, avatar_url FROM `users` WHERE id = ?";
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
// ۳. پردازش فرم آپدیت
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['action'])) {

    $update_sql_parts = [];
    $params_update = [];
    $types_update = "";
    $table_to_update = $is_staff ? '`staff-manage`' : '`users`';

    // --- بخش جدید: پردازش آپلود عکس پروفایل ---
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['profile_picture'];
        $upload_dir = __DIR__.'/../uploads/avatars/';
        
        // اعتبارسنجی فایل
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $file_info = getimagesize($file['tmp_name']);
        if ($file_info && in_array($file_info['mime'], $allowed_types)) {
            if ($file['size'] <= 2000000) { // حداکثر 2MB
                
                // حذف عکس قدیمی (اگر وجود دارد)
                if (!empty($profile_data['avatar_url'])) {
                    $old_avatar_path = $upload_dir . $profile_data['avatar_url'];
                    if (file_exists($old_avatar_path)) {
                        unlink($old_avatar_path);
                    }
                }

                // ساخت نام جدید و انتقال فایل
                $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                $new_filename = uniqid('avatar_', true) . '.' . $file_extension;
                $target_path = $upload_dir . $new_filename;

                if (move_uploaded_file($file['tmp_name'], $target_path)) {
                    // افزودن نام فایل جدید به کوئری آپدیت دیتابیس
                    $update_sql_parts[] = "avatar_url = ?";
                    $params_update[] = $new_filename;
                    $types_update .= "s";
                } else {
                    $message = "خطا در آپلود فایل.";
                    $message_type = 'error';
                }
            } else {
                $message = "حجم فایل باید کمتر از 2 مگابایت باشد.";
                $message_type = 'error';
            }
        } else {
            $message = "فرمت فایل مجاز نیست (فقط JPG, PNG, GIF).";
            $message_type = 'error';
        }
    }
    // --- پایان بخش جدید ---

    $posted_fullname = $_POST['fullname'] ?? $profile_data['fullname'];
    $posted_email = $_POST['email'] ?? $profile_data['email'];
    $posted_phone = $_POST['phone'] ?? $profile_data['phone'];
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';


    if ($posted_fullname !== $profile_data['fullname']) { $update_sql_parts[] = "fullname = ?"; $params_update[] = $posted_fullname; $types_update .= "s"; }
    if ($posted_email !== $profile_data['email']) { 
        $update_sql_parts[] = "email = ?"; $params_update[] = $posted_email; $types_update .= "s"; 
        $update_sql_parts[] = "email_verified_at = NULL";
    }
    if ($posted_phone !== $profile_data['phone']) { $update_sql_parts[] = "phone = ?"; $params_update[] = $posted_phone; $types_update .= "s"; }

    if ($is_staff) {
        $posted_staff_age = $_POST['staff_age'] ?? $profile_data['age'];
        $posted_staff_steam_id = $_POST['staff_steam_id'] ?? $profile_data['steam_id'];
        if ($posted_staff_age != $profile_data['age']) { $update_sql_parts[] = "age = ?"; $params_update[] = $posted_staff_age; $types_update .= "i"; }
        if ($posted_staff_steam_id != $profile_data['steam_id']) { $update_sql_parts[] = "steam_id = ?"; $params_update[] = $posted_staff_steam_id; $types_update .= "s"; }
    }

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
            if ($stmt_update === false) { throw new Exception("خطا در آماده‌سازی کوئری: " . $conn->error); }
            $stmt_update->bind_param($types_update, ...$params_update);
            $stmt_update->execute();
            
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
    } elseif (empty($message) && empty($_FILES['profile_picture']['name'])) { // نمایش پیام فقط اگر فایلی هم آپلود نشده باشد
        $message = "هیچ تغییری برای بروزرسانی وجود نداشت.";
        $message_type = 'info';
    }
}

// ۴. آماده‌سازی متغیرهای نمایشی
$default_avatar = 'https://ui-avatars.com/api/?name=' . urlencode($username) . '&background=ffd700&color=0f0f23&bold=true';
$final_avatar_src = $default_avatar; // مقدار پیش‌فرض اولیه

// اولویت اول: بررسی آواتار آپلود شده
if (!empty($profile_data['avatar_url'])) {
    $final_avatar_src = '../uploads/avatars/' . htmlspecialchars($profile_data['avatar_url']);
}
// اولویت دوم: آواتار دیسکورد برای استف‌ها
else if ($is_staff && !empty($profile_data['discord_id2'])) {
    function get_discord_avatar_url_for_profile($discord_id) {
        if (empty($discord_id)) return null;
        $webhook_url = 'http://83.149.95.39:1030/get-avatar-url?discord_id=' . urlencode($discord_id);
        $secret_token = '4a97dd86-4388-4cc0-a54f-65ebbf51649d';
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
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ویرایش پروفایل | SSO Center</title>
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
                    }
                }
            }
        }
    </script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;500;600;700&display=swap');
        body { font-family: 'Vazirmatn', sans-serif; }
        .gradient-bg { background: linear-gradient(135deg, #0f0f23 0%, #1a1a2e 50%, #16213e 100%); }
        .glow-effect { box-shadow: 0 0 20px rgba(255, 215, 0, 0.3); }
        .card-hover { transition: all 0.3s ease; }
        .card-hover:hover { transform: translateY(-5px); box-shadow: 0 10px 30px rgba(255, 215, 0, 0.1); }
        .sidebar-item { transition: all 0.3s ease; border-right: 3px solid transparent; }
        .sidebar-item:hover, .sidebar-item.active {
            background: linear-gradient(90deg, rgba(255, 215, 0, 0.07) 0%, transparent 100%);
            border-right-color: #ffd700;
            color: #ffd700;
        }
        .sidebar-item.active { background: linear-gradient(90deg, rgba(255, 215, 0, 0.1) 0%, transparent 100%); }
        /* استایل سفارشی برای input ها */
        .form-input {
            background-color: #16213e; /* dark-tertiary */
            border: 1px solid rgba(255, 215, 0, 0.2); /* yellow-primary/20 */
            color: #e5e7eb; /* gray-200 */
            border-radius: 0.375rem; /* rounded-md */
            padding: 0.75rem;
            transition: all 0.2s ease-in-out;
        }
        .form-input:focus {
            outline: none;
            border-color: #ffd700; /* yellow-primary */
            box-shadow: 0 0 0 2px rgba(255, 215, 0, 0.3);
        }
        .form-input.readonly {
            background-color: rgba(26, 26, 46, 0.5); /* dark-secondary with opacity */
            cursor: not-allowed;
        }
    </style>
</head>
<body class="gradient-bg text-gray-200">
    <div class="flex h-screen">
        <aside id="sidebar" class="hidden md:flex flex-col w-64 bg-dark-secondary shadow-2xl z-20 transition-transform duration-300">
            <div class="p-6 border-b border-yellow-primary/20">
                <div class="flex items-center space-x-3 space-x-reverse">
                    <div class="w-12 h-12 bg-gradient-to-br from-yellow-primary to-yellow-dark rounded-full flex items-center justify-center glow-effect">
                         <img src="/assets/images/logo.png" alt="Logo" class="h-8 w-8">
                    </div>
                    <div>
                        <h2 class="text-yellow-primary font-bold text-lg">SSO Center</h2>
                        <p class="text-gray-400 text-sm">داشبورد کاربری</p>
                    </div>
                </div>
            </div>
            <nav class="flex-grow p-4 space-y-2">
                <a href="dashboard.php" class="flex items-center px-4 py-2.5 text-sm font-medium text-gray-300 rounded-lg sidebar-item">
                    <i class="fas fa-home w-6 text-center ml-3"></i> داشبورد
                </a>
                <a href="#" class="flex items-center px-4 py-2.5 text-sm font-medium rounded-lg sidebar-item active">
                    <i class="fas fa-user-edit w-6 text-center ml-3"></i> ویرایش پروفایل
                </a>
                <a href="passkey_management.php" class="flex items-center px-4 py-2.5 text-sm font-medium text-gray-300 rounded-lg sidebar-item">
                    <i class="fas fa-key w-6 text-center ml-3"></i> مدیریت Passkey
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
                        <i class="fas fa-sign-out-alt ml-2"></i> خروج
                    </a>
                </div>
            </div>
        </aside>

        <div class="flex flex-col flex-1">
            <header class="relative z-10 bg-dark-secondary/50 backdrop-blur-sm border-b border-yellow-primary/20 flex items-center justify-between h-16 px-4 sm:px-6">
                <div class="flex items-center">
                    <button class="md:hidden text-yellow-primary focus:outline-none" onclick="toggleMobileSidebar()">
                        <i class="fas fa-bars text-xl"></i>
                    </button>
                    <h1 class="text-lg font-bold text-yellow-primary mr-4 hidden sm:block">ویرایش پروفایل</h1>
                </div>
                <div class="flex items-center space-x-4 space-x-reverse">
                     <div class="relative">
                        <button class="flex items-center focus:outline-none" onclick="toggleUserDropdown()">
                            <img src="<?= htmlspecialchars($final_avatar_src) ?>" alt="User" class="w-8 h-8 rounded-full">
                        </button>
                        <div id="user-dropdown" class="hidden absolute left-0 mt-2 w-48 bg-dark-secondary rounded-md shadow-lg py-1 z-50 border border-yellow-primary/20">
                            <a href="logout.php" class="block px-4 py-2 text-sm text-gray-300 hover:text-yellow-primary"><i class="fas fa-sign-out-alt ml-2"></i> خروج</a>
                        </div>
                    </div>
                </div>
            </header>
            
            <main class="flex-1 overflow-y-auto p-4 md:p-6">
                <?php if ($message): ?>
                    <div class="mb-6 p-4 rounded-lg text-sm text-center font-semibold
                        <?php if ($message_type === 'success'): ?> bg-green-500/20 text-green-300 border border-green-400/30 <?php endif; ?>
                        <?php if ($message_type === 'error'): ?> bg-red-500/20 text-red-300 border border-red-400/30 <?php endif; ?>
                        <?php if ($message_type === 'info'): ?> bg-blue-500/20 text-blue-300 border border-blue-400/30 <?php endif; ?>
                    ">
                        <?= htmlspecialchars($message) ?>
                    </div>
                <?php endif; ?>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <div class="lg:col-span-3 xl:col-span-2">
                        <div class="bg-dark-secondary rounded-xl p-6 border border-yellow-primary/20 card-hover">
                            <form method="POST" action="profile.php" class="space-y-8" enctype="multipart/form-data">
                                <div>
                                    <h3 class="text-lg font-bold text-yellow-primary border-b border-yellow-primary/20 pb-3 mb-6">اطلاعات پایه</h3>
                                    <div class="flex items-center gap-4">
                                        <img src="<?= htmlspecialchars($final_avatar_src) ?>" alt="User Avatar" class="w-16 h-16 rounded-full object-cover border-2 border-yellow-primary/50">
                                        <div>
                                            <label for="profile_picture" class="block text-sm font-medium text-gray-400 mb-2">تغییر عکس پروفایل</label>
                                            <input type="file" name="profile_picture" id="profile_picture" class="text-xs text-gray-400 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-dark-tertiary file:text-yellow-primary hover:file:bg-yellow-primary/20">
                                        </div>
                                    </div>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <div>
                                            <label for="username" class="block text-sm font-medium text-gray-400 mb-2">نام کاربری</label>
                                            <input type="text" id="username" class="w-full form-input readonly" value="<?= htmlspecialchars($profile_data['username']) ?>" readonly>
                                        </div>
                                        <div>
                                            <label for="fullname" class="block text-sm font-medium text-gray-400 mb-2">نام کامل</label>
                                            <input type="text" id="fullname" name="fullname" class="w-full form-input" value="<?= htmlspecialchars($profile_data['fullname'] ?? '') ?>">
                                        </div>
                                        <div>
                                            <label for="phone" class="block text-sm font-medium text-gray-400 mb-2">تلفن</label>
                                            <input type="tel" id="phone" name="phone" class="w-full form-input" value="<?= htmlspecialchars($profile_data['phone'] ?? '') ?>">
                                        </div>
                                        <div>
                                            <label for="email" class="block text-sm font-medium text-gray-400 mb-2">ایمیل</label>
                                            <div class="flex items-center">
                                                <input type="email" id="email" name="email" class="w-full form-input rounded-l-none" value="<?= htmlspecialchars($profile_data['email'] ?? '') ?>">
                                                <span class="px-3 py-3 text-xs font-bold text-white rounded-l-md whitespace-nowrap <?= !empty($profile_data['email_verified_at']) ? 'bg-green-600' : 'bg-yellow-600' ?>">
                                                    <?= !empty($profile_data['email_verified_at']) ? 'تایید شده' : 'تایید نشده' ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <?php if ($is_staff): ?>
                                <div>
                                    <h3 class="text-lg font-bold text-yellow-primary border-b border-yellow-primary/20 pb-3 mb-6">اطلاعات تکمیلی استف</h3>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <div>
                                            <label for="staff_age" class="block text-sm font-medium text-gray-400 mb-2">سن</label>
                                            <input type="number" id="staff_age" name="staff_age" class="w-full form-input" value="<?= htmlspecialchars($profile_data['age'] ?? '') ?>">
                                        </div>
                                        <div>
                                            <label for="staff_steam_id" class="block text-sm font-medium text-gray-400 mb-2">آیدی استیم</label>
                                            <input type="text" id="staff_steam_id" name="staff_steam_id" class="w-full form-input" value="<?= htmlspecialchars($profile_data['steam_id'] ?? '') ?>">
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>

                                <div>
                                    <h3 class="text-lg font-bold text-yellow-primary border-b border-yellow-primary/20 pb-3 mb-6">تغییر رمز عبور</h3>
                                    <p class="text-xs text-gray-500 mb-4">این بخش را فقط در صورت نیاز به تغییر رمز عبور پر کنید.</p>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <div>
                                            <label for="new_password" class="block text-sm font-medium text-gray-400 mb-2">رمز عبور جدید</label>
                                            <input type="password" id="new_password" name="new_password" class="w-full form-input" autocomplete="new-password">
                                        </div>
                                        <div>
                                            <label for="confirm_password" class="block text-sm font-medium text-gray-400 mb-2">تکرار رمز عبور</label>
                                            <input type="password" id="confirm_password" name="confirm_password" class="w-full form-input" autocomplete="new-password">
                                        </div>
                                    </div>
                                </div>
                                <div class="flex items-center justify-end pt-6 border-t border-yellow-primary/20">
                                    <button type="submit" class="px-6 py-2 text-sm font-bold text-dark-primary bg-gradient-to-r from-yellow-primary to-yellow-dark hover:shadow-lg hover:shadow-yellow-primary/20 rounded-lg transition-all">
                                        <i class="fas fa-save ml-2"></i>
                                        ذخیره تغییرات
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div class="lg:col-span-3 xl:col-span-1 space-y-6">
                        <?php if (empty($profile_data['email_verified_at']) && !empty($profile_data['email'])): ?>
                        <div class="bg-dark-secondary rounded-xl p-6 border border-yellow-primary/20 card-hover">
                             <h2 class="text-lg font-bold text-yellow-primary mb-4">تایید ایمیل</h2>
                             <p class="text-gray-400 text-sm mb-4">ایمیل شما هنوز تایید نشده است. برای دسترسی به تمام امکانات، لطفا آن را تایید کنید.</p>
                             <form method="POST" action="profile.php" class="m-0">
                                 <input type="hidden" name="action" value="send_verification">
                                 <input type="hidden" name="email_to_verify" value="<?= htmlspecialchars($profile_data['email']) ?>">
                                 <button type="submit" class="w-full px-4 py-2 text-sm font-bold text-dark-primary bg-gradient-to-r from-yellow-primary to-yellow-dark hover:shadow-lg hover:shadow-yellow-primary/20 rounded-lg transition-all">
                                     <i class="fas fa-envelope ml-2"></i>ارسال مجدد ایمیل تایید
                                 </button>
                             </form>
                        </div>
                        <?php endif; ?>

                        <?php if ($is_staff): ?>
                        <div class="bg-dark-secondary rounded-xl p-6 border border-yellow-primary/20 card-hover">
                            <h2 class="text-lg font-bold text-yellow-primary mb-4 flex items-center">
                                <i class="fab fa-discord text-indigo-400 ml-3"></i>
                                اتصال دیسکورد
                            </h2>
                            <div class="space-y-3 text-sm">
                                <p class="text-gray-400"><strong class="font-medium text-gray-300">نام کاربری:</strong> <?= htmlspecialchars($profile_data['discord_id'] ?: '---') ?></p>
                                <p class="text-gray-400"><strong class="font-medium text-gray-300">آیدی عددی:</strong> <?= htmlspecialchars($profile_data['discord_id2'] ?: '---') ?></p>
                                <p class="text-gray-400"><strong class="font-medium text-gray-300">مقام‌ها:</strong> <?= htmlspecialchars($profile_data['permissions'] ?: '---') ?></p>
                                 <p class="text-gray-400 flex items-center">
                                    <strong class="font-medium text-gray-300 ml-2">وضعیت:</strong>
                                    <?php if (!empty($profile_data['discord_conn'])): ?>
                                        <span class="font-semibold text-green-400">متصل و تایید شده</span>
                                    <?php else: ?>
                                        <span class="font-semibold text-yellow-400">در انتظار تایید</span>
                                    <?php endif; ?>
                                </p>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <div id="mobile-sidebar" class="hidden fixed inset-0 z-40 md:hidden">
        <div class="fixed inset-0 bg-black/30" onclick="toggleMobileSidebar()"></div>
        <div class="relative flex flex-col w-72 max-w-xs h-full bg-dark-secondary border-l border-yellow-primary/20">
             <div class="flex items-center justify-center h-16 border-b border-yellow-primary/20">
                <img src="/assets/images/logo.png" alt="Logo" class="h-8 w-8 ml-2">
                <span class="text-yellow-primary font-bold text-lg">SSO Center</span>
            </div>
            <nav class="flex-grow p-4 space-y-2">
                 <a href="dashboard.php" class="flex items-center px-4 py-2.5 text-sm font-medium text-gray-300 rounded-lg sidebar-item">
                    <i class="fas fa-home w-6 text-center ml-3"></i> داشبورد
                </a>
                <a href="#" class="flex items-center px-4 py-2.5 text-sm font-medium rounded-lg sidebar-item active">
                    <i class="fas fa-user-edit w-6 text-center ml-3"></i> ویرایش پروفایل
                </a>
                <a href="passkey_management.php" class="flex items-center px-4 py-2.5 text-sm font-medium text-gray-300 rounded-lg sidebar-item">
                    <i class="fas fa-key w-6 text-center ml-3"></i> مدیریت Passkey
                </a>
            </nav>
        </div>
    </div>

    <script>
        const mobileSidebar = document.getElementById('mobile-sidebar');
        const userDropdown = document.getElementById('user-dropdown');
        function toggleMobileSidebar() { 
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('hidden');
            sidebar.classList.toggle('md:flex');
        }
        function toggleUserDropdown() { userDropdown.classList.toggle('hidden'); }
        document.addEventListener('click', function(event) {
            const userMenuButton = event.target.closest('.relative > button');
            if (!userMenuButton && userDropdown && !userDropdown.contains(event.target)) {
                userDropdown.classList.add('hidden');
            }
        }, true);
    </script>
</body>
</html>