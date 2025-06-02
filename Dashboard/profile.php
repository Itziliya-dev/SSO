<?php
// این دو خط باید در بالاترین نقطه فایل باشند
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__.'/../includes/config.php';
require_once __DIR__.'/../includes/auth_functions.php'; // برای getDbConnection

session_start();

// بررسی اینکه کاربر وارد شده است یا خیر
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$current_id_in_session = $_SESSION['user_id']; // این ID یا از users است یا از staff-manage
$user_type = $_SESSION['user_type'] ?? 'user'; // 'user' یا 'staff'

$conn = getDbConnection();
if (!$conn || $conn->connect_error) {
    die("خطا در اتصال به دیتابیس: " . ($conn ? $conn->connect_error : 'Failed to connect'));
}

$message = '';
$message_type = '';
$profile_data = null; // داده‌های پروفایل برای نمایش در فرم

// 1. دریافت اطلاعات فعلی کاربر برای نمایش در فرم
if ($user_type === 'staff') {
    // کاربر استف است، فقط از staff-manage بخوان
    $sql_fetch = "SELECT id, username, fullname, email, phone, age, discord_id, steam_id, permissions, is_verify
                  FROM `staff-manage`
                  WHERE id = ?";
} else { // کاربر عادی است، فقط از users بخوان
    $sql_fetch = "SELECT id, username, fullname, email, phone, is_owner, has_user_panel
                  FROM `users`
                  WHERE id = ?";
    // اگر کاربر عادی هم می‌توانست is_staff باشد و اطلاعات بیشتری از staff_manage نیاز داشت، باید JOIN اضافه می‌شد
}

$stmt_fetch = $conn->prepare($sql_fetch);
if ($stmt_fetch === false) {
    error_log("Profile Fetch Prepare Error: " . $conn->error);
    die("خطا در آماده سازی کوئری (خواندن اطلاعات): " . htmlspecialchars($conn->error));
}
$stmt_fetch->bind_param("i", $current_id_in_session);
if (!$stmt_fetch->execute()) {
    error_log("Profile Fetch Execute Error: " . $stmt_fetch->error);
    die("خطا در اجرای کوئری (خواندن اطلاعات): " . htmlspecialchars($stmt_fetch->error));
}

$result_fetch = $stmt_fetch->get_result();
$profile_data = $result_fetch->fetch_assoc();
$stmt_fetch->close();

if (!$profile_data) {
    die("خطا: اطلاعات کاربر با ID " . htmlspecialchars($current_id_in_session) . " و نوع " . $user_type . " یافت نشد.");
}


// 2. پردازش فرم آپدیت وقتی POST ارسال می‌شود
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $posted_fullname = $_POST['fullname'] ?? null;
    $posted_email = $_POST['email'] ?? null;
    $posted_phone = $_POST['phone'] ?? null;
    $new_password = $_POST['new_password'] ?? null;
    $confirm_password = $_POST['confirm_password'] ?? null;

    // فیلدهای مخصوص استف (اگر کاربر استف است)
    $posted_staff_age = ($user_type === 'staff') ? ($_POST['staff_age'] ?? null) : null;
    $posted_staff_discord_id = ($user_type === 'staff') ? ($_POST['staff_discord_id'] ?? null) : null;
    $posted_staff_steam_id = ($user_type === 'staff') ? ($_POST['staff_steam_id'] ?? null) : null;
    // permissions و is_verify معمولا توسط ادمین تغییر می‌کنند، نه خود استف در پروفایلش

    $conn->begin_transaction();
    try {
        $update_sql_parts = [];
        $params_update = [];
        $types_update = "";
        $table_to_update = ($user_type === 'staff') ? '`staff-manage`' : '`users`';

        // مقادیر برای آپدیت
        if ($posted_fullname !== null) { $update_sql_parts[] = "fullname = ?"; $params_update[] = $posted_fullname; $types_update .= "s"; }
        if ($posted_email !== null) { $update_sql_parts[] = "email = ?"; $params_update[] = $posted_email; $types_update .= "s"; }
        if ($posted_phone !== null) { $update_sql_parts[] = "phone = ?"; $params_update[] = $posted_phone; $types_update .= "s"; }

        if ($user_type === 'staff') {
            if ($posted_staff_age !== null) { $update_sql_parts[] = "age = ?"; $params_update[] = $posted_staff_age; $types_update .= "i"; }
            if ($posted_staff_discord_id !== null) { $update_sql_parts[] = "discord_id = ?"; $params_update[] = $posted_staff_discord_id; $types_update .= "s"; }
            if ($posted_staff_steam_id !== null) { $update_sql_parts[] = "steam_id = ?"; $params_update[] = $posted_staff_steam_id; $types_update .= "s"; }
        }

        if (!empty($new_password)) {
            if ($new_password === $confirm_password) {
                $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);
                $update_sql_parts[] = "password = ?";
                $params_update[] = $hashed_password;
                $types_update .= "s";
            } else {
                throw new Exception("رمزهای عبور جدید مطابقت ندارند.");
            }
        }

        if (!empty($update_sql_parts)) {
            $update_sql = "UPDATE $table_to_update SET " . implode(", ", $update_sql_parts);
            // اضافه کردن updated_at اگر در جدول وجود دارد و به طور خودکار آپدیت نمی‌شود
            if (array_key_exists('updated_at', $profile_data)) { // بررسی وجود ستون
                 $update_sql .= ", updated_at = NOW()";
            }
            $update_sql .= " WHERE id = ?";

            $params_update[] = $current_id_in_session;
            $types_update .= "i";

            $stmt_update = $conn->prepare($update_sql);
            if($stmt_update === false) throw new Exception("خطای Prepare (Update $table_to_update): " . $conn->error);
            $stmt_update->bind_param($types_update, ...$params_update);
            if(!$stmt_update->execute()) throw new Exception("خطای Execute (Update $table_to_update): " . $stmt_update->error);
            $stmt_update->close();
        }

        $conn->commit();
        $message = "اطلاعات با موفقیت بروزرسانی شد.";
        $message_type = 'success';

        // خواندن مجدد اطلاعات برای نمایش مقادیر بروز شده در فرم
        $stmt_fetch_again = $conn->prepare($sql_fetch); // از همان کوئری اولیه مناسب با user_type استفاده می‌کنیم
        $stmt_fetch_again->bind_param("i", $current_id_in_session);
        $stmt_fetch_again->execute();
        $profile_data = $stmt_fetch_again->get_result()->fetch_assoc();
        $stmt_fetch_again->close();

    } catch (Exception $e) {
        $conn->rollback();
        $message = "خطا در بروزرسانی: " . $e->getMessage();
        $message_type = 'error';
    }
}

// بستن اتصال در انتهای اسکریپت
if ($conn) {
    $conn->close();
}
?>
<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ویرایش پروفایل | SSO Center</title>
    <link rel="stylesheet" href="/../assets/css/style.css"> <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* استایل‌های عمومی که در پاسخ داشبورد هم استفاده شد */
        :root {
            --text-color: #e5e7eb; /* رنگ متن روشن */
            --text-muted-color: #9ca3af; /* رنگ متن تیره‌تر */
            --card-bg: rgba(31, 41, 55, 0.7); /* پس‌زمینه کارت با شفافیت بیشتر */
            --card-border: rgba(75, 85, 99, 0.5); /* رنگ بوردر کارت */
            --primary-color: #3b82f6; /* آبی */
            --primary-hover: #2563eb;
            --font-family: 'Vazirmatn', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            --input-bg: rgba(55, 65, 81, 0.7); /* پس‌زمینه اینپوت‌ها */
            --input-border: rgba(107, 114, 128, 0.5);
            --input-focus-border: var(--primary-color);
            --glass-bg: rgba(55, 65, 81, 0.5);
            --glass-border: rgba(107, 114, 128, 0.3);
            --glass-shadow: 0 8px 32px rgba(0, 0, 0, 0.37);
        }
        body {
            font-family: var(--font-family);
            color: var(--text-color);
            background-color: #0a0a1a;
            min-height: 100vh;
            display: flex; /* برای وسط‌چین کردن profile-container */
            justify-content: center;
            align-items: center;
            padding: 20px;
            direction: rtl;
        }
        .background-image {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background-image: url('/../assets/images/background.jpg');
            background-size: cover; background-position: center; z-index: -1;
            filter: brightness(0.6) contrast(1.1);
        }
        .profile-container {
            width: 100%;
            max-width: 650px; /* کمی عریض‌تر */
        }
        .profile-box {
            background: var(--card-bg);
            border-radius: 16px;
            padding: 30px 35px; /* پدینگ بیشتر */
            box-shadow: var(--glass-shadow);
            backdrop-filter: blur(12px);
            border: 1px solid var(--card-border);
        }
        .profile-box h1 {
            text-align: center;
            margin-bottom: 30px;
            font-size: 24px; /* کمی بزرگتر */
            font-weight: 600;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        .form-group { margin-bottom: 20px; /* فاصله بیشتر */ }
        .form-group label {
            display: block;
            margin-bottom: 8px; /* فاصله بیشتر */
            font-size: 14px;
            font-weight: 500;
            color: var(--text-muted-color);
        }
        .form-control {
            width: 100%;
            padding: 12px 15px; /* پدینگ بهتر */
            border-radius: 8px;
            border: 1px solid var(--input-border);
            background: var(--input-bg);
            color: var(--text-color);
            font-size: 15px;
            transition: border-color 0.3s, box-shadow 0.3s;
        }
        .form-control:focus {
            outline: none;
            border-color: var(--input-focus-border);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.3); /* آبی با شفافیت */
        }
        .submit-btn {
            background: var(--primary-color);
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 500;
            width: 100%;
            margin-top: 15px; /* فاصله از آخرین بخش فرم */
            transition: background-color 0.3s, transform 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .submit-btn:hover { background: var(--primary-hover); transform: translateY(-2px); }
        .message {
            padding: 15px; margin-bottom: 25px; border-radius: 8px;
            font-size: 14px; text-align: center;
            display: flex; align-items: center; justify-content: center; gap: 10px;
        }
        .message.success { background-color: rgba(16, 185, 129, 0.15); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.3); }
        .message.error { background-color: rgba(239, 68, 68, 0.15); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.3); }
        .form-section {
            border-top: 1px solid var(--card-border);
            margin-top: 25px;
            padding-top: 25px;
        }
        .form-section h4 {
            font-size: 16px;
            font-weight: 600;
            color: #fff;
            margin-bottom: 15px;
        }
        .back-link {
            display: block; text-align: center; margin-top: 20px;
            color: var(--primary-color); text-decoration: none; font-size: 14px;
            transition: color 0.3s;
        }
        .back-link:hover { color: var(--primary-hover); }
    </style>
</head>
<body>
<div class="background-image"></div>
<div class="profile-container">
    <div class="profile-box">
        <h1>
            <i class="fas fa-user-cog"></i>
            ویرایش پروفایل
            <span style="font-size: 0.7em; color: var(--text-muted-color);">(<?= htmlspecialchars($profile_data['username'] ?? 'کاربر') ?>)</span>
        </h1>

        <?php if ($message): ?>
            <div class="message <?= htmlspecialchars($message_type) ?>">
                <i class="fas <?= ($message_type === 'success') ? 'fa-check-circle' : 'fa-exclamation-triangle' ?>"></i>
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label for="fullname">نام کامل:</label>
                <input type="text" id="fullname" name="fullname" class="form-control" value="<?= htmlspecialchars($profile_data['fullname'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="email">ایمیل:</label>
                <input type="email" id="email" name="email" class="form-control" value="<?= htmlspecialchars($profile_data['email'] ?? '') ?>">
            </div>
             <div class="form-group">
                <label for="phone">تلفن:</label>
                <input type="tel" id="phone" name="phone" class="form-control" value="<?= htmlspecialchars($profile_data['phone'] ?? '') ?>">
            </div>

            <?php if ($user_type === 'staff'): // فیلدهای مخصوص استف ?>
            <div class="form-section staff-section">
                 <h4><i class="fas fa-user-tie"></i> اطلاعات تکمیلی استف</h4>
                 <div class="form-group">
                    <label for="staff_age">سن:</label>
                    <input type="number" id="staff_age" name="staff_age" class="form-control" value="<?= htmlspecialchars($profile_data['age'] ?? '') ?>">
                 </div>
                 <div class="form-group">
                    <label for="staff_discord_id">آیدی دیسکورد:</label>
                    <input type="text" id="staff_discord_id" name="staff_discord_id" class="form-control" value="<?= htmlspecialchars($profile_data['discord_id'] ?? '') ?>">
                 </div>
                 <div class="form-group">
                    <label for="staff_steam_id">آیدی استیم:</label>
                    <input type="text" id="staff_steam_id" name="staff_steam_id" class="form-control" value="<?= htmlspecialchars($profile_data['steam_id'] ?? '') ?>">
                 </div>
                <?php if (isset($profile_data['permissions'])): ?>
                     <div class="form-group">
                         <label>مقام فعلی:</label>
                         <input type="text" class="form-control" value="<?= htmlspecialchars($profile_data['permissions']) ?>" readonly>
                     </div>
                 <?php endif; ?>
                 <?php if (isset($profile_data['is_verify'])): ?>
                     <div class="form-group">
                         <label>وضعیت احراز هویت:</label>
                         <input type="text" class="form-control" value="<?= $profile_data['is_verify'] ? 'تایید شده' : 'تایید نشده' ?>" readonly>
                     </div>
                 <?php endif; ?>
            </div>
            <?php endif; ?>


            <div class="form-section password-section">
                <h4><i class="fas fa-key"></i> تغییر رمز عبور</h4>
                <p style="font-size: 0.85em; color: var(--text-muted-color); margin-bottom: 15px;">اگر نمی‌خواهید رمز عبور را تغییر دهید، این بخش را خالی بگذارید.</p>
                <div class="form-group">
                    <label for="new_password">رمز عبور جدید:</label>
                    <input type="password" id="new_password" name="new_password" class="form-control" autocomplete="new-password">
                </div>
                 <div class="form-group">
                    <label for="confirm_password">تکرار رمز عبور جدید:</label>
                    <input type="password" id="confirm_password" name="confirm_password" class="form-control" autocomplete="new-password">
                </div>
            </div>

            <button type="submit" class="submit-btn"><i class="fas fa-save"></i> ذخیره تغییرات</button>
            <a href="dashboard.php" class="back-link"><i class="fas fa-arrow-left"></i> بازگشت به داشبورد</a>
        </form>
    </div>
</div>
</body>
</html>