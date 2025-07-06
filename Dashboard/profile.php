<?php
// این دو خط باید در بالاترین نقطه فایل باشند
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__.'/../includes/config.php';
require_once __DIR__.'/../includes/database.php'; // <--- این خط را اضافه کنید اگر تابع در این فایل است
require_once __DIR__.'/../includes/header.php';


// require_once __DIR__.'/../includes/auth_functions.php'; // برای getDbConnection، اگر در config.php نیست
// getDbConnection باید در config.php یا database.php (که توسط config.php لود می‌شود) موجود باشد.

session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: /../login.php'); // اطمینان از مسیر صحیح
    exit();
}

$current_id_in_session = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'] ?? 'user';

$conn = getDbConnection(); // تابع اتصال به دیتابیس شما
if (!$conn || $conn->connect_error) {
    error_log("Profile Page - DB Connection Error: " . ($conn ? $conn->connect_error : 'Failed to connect object'));
    die("خطا در اتصال به دیتابیس.");
}

$message = '';
$message_type = '';
$profile_data = null;

// 1. دریافت اطلاعات فعلی کاربر برای نمایش در فرم
if ($user_type === 'staff') {
    // کاربر استف است، از staff-manage بخوان (discord_id2 و permissions هم باید باشند)
    $sql_fetch = "SELECT id, username, fullname, email, phone, age, discord_id, discord_id2, steam_id, permissions, is_verify, discord_conn
                  FROM `staff-manage`
                  WHERE id = ?"; // یا user_id = ? اگر current_id_in_session مربوط به جدول users است
} else { // کاربر عادی است، فقط از users بخوان
    $sql_fetch = "SELECT id, username, fullname, email, phone, is_owner, has_user_panel
                  FROM `users`
                  WHERE id = ?";
}

$stmt_fetch = $conn->prepare($sql_fetch);
if ($stmt_fetch === false) {
    error_log("Profile Fetch Prepare Error: " . $conn->error);
    die("خطا در آماده سازی کوئری (خواندن اطلاعات اولیه): " . htmlspecialchars($conn->error));
}
// اگر current_id_in_session همیشه ID جدول مربوطه است (staff-manage.id یا users.id)
$stmt_fetch->bind_param("i", $current_id_in_session);

// اگر current_id_in_session همیشه user_id از جدول users است و برای staff باید staff-manage.user_id را مپ کنید:
// if ($user_type === 'staff') {
//     // $stmt_fetch->bind_param("i", $user_id_from_users_table_for_staff); // که باید این user_id را داشته باشید
// } else {
//    $stmt_fetch->bind_param("i", $current_id_in_session);
// }


if (!$stmt_fetch->execute()) {
    error_log("Profile Fetch Execute Error: " . $stmt_fetch->error);
    die("خطا در اجرای کوئری (خواندن اطلاعات اولیه): " . htmlspecialchars($stmt_fetch->error));
}

$result_fetch = $stmt_fetch->get_result();
$profile_data = $result_fetch->fetch_assoc();
$stmt_fetch->close();

if (!$profile_data) {
    // اگر از user_id برای staff استفاده می‌کنید، اینجا باید پیام مناسب‌تری باشد
    die("خطا: اطلاعات کاربر با ID " . htmlspecialchars($current_id_in_session) . " و نوع " . htmlspecialchars($user_type) . " یافت نشد.");
}


// 2. پردازش فرم آپدیت وقتی POST ارسال می‌شود
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // اطمینان از اینکه اتصال هنوز برقرار است یا دوباره برقرار شود
    if (!$conn || $conn->ping() === false) { // ping() برای بررسی زنده بودن اتصال
        $conn = getDbConnection();
        if (!$conn || $conn->connect_error) {
            error_log("Profile Update - DB Re-Connection Error: " . ($conn ? $conn->connect_error : 'Failed to connect object'));
            die("خطا در اتصال مجدد به دیتابیس برای آپدیت.");
        }
    }

    $posted_fullname = $_POST['fullname'] ?? ($profile_data['fullname'] ?? null);
    $posted_email = $_POST['email'] ?? ($profile_data['email'] ?? null);
    $posted_phone = $_POST['phone'] ?? ($profile_data['phone'] ?? null);
    $new_password = $_POST['new_password'] ?? null;
    $confirm_password = $_POST['confirm_password'] ?? null;

    // فیلدهای مخصوص استف (اگر کاربر استف است)
    $posted_staff_age = ($user_type === 'staff') ? ($_POST['staff_age'] ?? ($profile_data['age'] ?? null)) : null;
    // آیدی‌های دیسکورد و استیم معمولاً توسط کاربر اینجا تغییر داده نمی‌شوند، مگر اینکه بخواهید
    // $posted_staff_discord_id = ($user_type === 'staff') ? ($_POST['staff_discord_id'] ?? ($profile_data['discord_id'] ?? null)) : null;
    // $posted_staff_steam_id = ($user_type === 'staff') ? ($_POST['staff_steam_id'] ?? ($profile_data['steam_id'] ?? null)) : null;


    $conn->begin_transaction();
    try {
        $update_sql_parts = [];
        $params_update = [];
        $types_update = "";
        $table_to_update = ($user_type === 'staff') ? '`staff-manage`' : '`users`';

        if ($posted_fullname !== $profile_data['fullname']) {
            $update_sql_parts[] = "fullname = ?";
            $params_update[] = $posted_fullname;
            $types_update .= "s";
        }
        if ($posted_email !== $profile_data['email']) {
            $update_sql_parts[] = "email = ?";
            $params_update[] = $posted_email;
            $types_update .= "s";
        }
        if ($posted_phone !== $profile_data['phone']) {
            $update_sql_parts[] = "phone = ?";
            $params_update[] = $posted_phone;
            $types_update .= "s";
        }

        if ($user_type === 'staff') {
            if ($posted_staff_age !== null && (int)$posted_staff_age !== (int)$profile_data['age']) {
                $update_sql_parts[] = "age = ?";
                $params_update[] = (int)$posted_staff_age;
                $types_update .= "i";
            }
            // معمولاً استف خودش discord_id, discord_id2, permissions را از اینجا تغییر نمی‌دهد
            // این مقادیر توسط بات یا ادمین تنظیم می‌شوند.
        }

        if (!empty($new_password)) {
            if ($new_password === $confirm_password) {
                $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);
                $update_sql_parts[] = "password = ?"; // ستون پسورد باید در هر دو جدول users و staff-manage وجود داشته باشد
                $params_update[] = $hashed_password;
                $types_update .= "s";
            } else {
                throw new Exception("رمزهای عبور جدید مطابقت ندارند.");
            }
        }

        if (!empty($update_sql_parts)) {
            $update_sql = "UPDATE $table_to_update SET " . implode(", ", $update_sql_parts);
            // اضافه کردن updated_at اگر در جدول وجود دارد و به طور خودکار آپدیت نمی‌شود
            if ($user_type === 'staff' && array_key_exists('updated_at', $profile_data)) {
                $update_sql .= (empty($update_sql_parts) ? "" : ", ") . "updated_at = NOW()";
            } elseif ($user_type === 'user' && method_exists($conn, 'prepare') /* check if users has updated_at too */) {
                // $update_sql .= (empty($update_sql_parts) ? "" : ", ") . "updated_at = NOW()";
            }


            $update_sql .= " WHERE id = ?";

            $params_update[] = $current_id_in_session;
            $types_update .= "i";

            $stmt_update = $conn->prepare($update_sql);
            if ($stmt_update === false) {
                throw new Exception("خطای Prepare (Update $table_to_update): " . $conn->error);
            }

            // اطمینان از ارسال پارامترها فقط اگر آرایه params_update خالی نیست
            if (!empty($params_update)) {
                $stmt_update->bind_param($types_update, ...$params_update);
            }

            if (!$stmt_update->execute()) {
                throw new Exception("خطای Execute (Update $table_to_update): " . $stmt_update->error);
            }
            $stmt_update->close();
            $message = "اطلاعات با موفقیت بروزرسانی شد.";
            $message_type = 'success';
        } else {
            $message = "هیچ تغییری برای بروزرسانی وجود نداشت.";
            $message_type = 'info'; // یا هر نوع پیام دیگری
        }


        $conn->commit();

        // خواندن مجدد اطلاعات برای نمایش مقادیر بروز شده در فرم
        $stmt_fetch_again = $conn->prepare($sql_fetch);
        $stmt_fetch_again->bind_param("i", $current_id_in_session);
        $stmt_fetch_again->execute();
        $profile_data = $stmt_fetch_again->get_result()->fetch_assoc();
        $stmt_fetch_again->close();

    } catch (Exception $e) {
        $conn->rollback();
        $message = "خطا در بروزرسانی: " . $e->getMessage();
        $message_type = 'error';
        error_log("Profile Update Error: " . $e->getMessage());
    }
}

// بستن اتصال در انتهای اسکریپت
if ($conn) {
    $conn->close();
}
?>

    <title>ویرایش پروفایل | SSO Center</title>
    <link rel="stylesheet" href="/../assets/css/style.css"> <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/assets/fonts/Vazirmatn-font-face.css">
    <style>
        /* استایل‌های عمومی که در پاسخ داشبورد هم استفاده شد */
        :root {
            --text-color: #e5e7eb; 
            --text-muted-color: #9ca3af; 
            --card-bg: rgba(31, 41, 55, 0.7); 
            --card-border: rgba(75, 85, 99, 0.5); 
            --primary-color: #3b82f6; 
            --primary-hover: #2563eb;
            --font-family: 'Vazirmatn', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            --input-bg: rgba(55, 65, 81, 0.7); 
            --input-border: rgba(107, 114, 128, 0.5);
            --input-focus-border: var(--primary-color);
            --glass-bg: rgba(55, 65, 81, 0.5);
            --glass-border: rgba(107, 114, 128, 0.3);
            --glass-shadow: 0 8px 32px rgba(0, 0, 0, 0.37);
            --info-bg: rgba(59, 130, 246, 0.15); /* برای پیام info */
            --info-color: #3b82f6;
            --info-border: rgba(59, 130, 246, 0.3);
            --verified-color: #34d399; 
            --not-verified-color: #f87171; 
        }
        body {
            font-family: var(--font-family);
            color: var(--text-color);
            background-color: #0a0a1a;
            min-height: 100vh;
            display: flex; 
            justify-content: center;
            align-items: flex-start; /* تغییر به flex-start برای اسکرول خوردن محتوا */
            padding: 80px 20px 20px 20px; /* پدینگ بالا برای هدر (اگر دارید) */
            direction: rtl;
            overflow-y: auto; /* اجازه اسکرول عمودی */
        }
        .background-image {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background-image: url('/../assets/images/background.jpg');
            background-size: cover; background-position: center; z-index: -1;
            filter: brightness(0.6) contrast(1.1);
        }
        .profile-container {
            width: 100%;
            max-width: 700px; /* کمی عریض‌تر برای جا دادن بخش جدید */
            margin-bottom: 30px; /* فاصله از پایین صفحه */
        }
        .profile-box {
            background: var(--card-bg);
            border-radius: 16px;
            padding: 30px 35px; 
            box-shadow: var(--glass-shadow);
            backdrop-filter: blur(12px);
            border: 1px solid var(--card-border);
            margin-bottom: 20px; /* فاصله بین باکس‌ها */
        }
        .profile-box h1, .profile-box h2 { /* استایل یکسان برای عناوین اصلی باکس‌ها */
            text-align: center;
            margin-bottom: 25px;
            font-size: 22px; 
            font-weight: 600;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        .profile-box h1 .fas, .profile-box h2 .fab, .profile-box h2 .fas { /* آیکون در عنوان */
            color: var(--primary-color);
        }

        .form-group { margin-bottom: 20px; }
        .form-group label {
            display: block;
            margin-bottom: 8px; 
            font-size: 14px;
            font-weight: 500;
            color: var(--text-muted-color);
        }
        .form-control {
            width: 100%;
            padding: 12px 15px; 
            border-radius: 8px;
            border: 1px solid var(--input-border);
            background: var(--input-bg);
            color: var(--text-color);
            font-size: 15px;
            transition: border-color 0.3s, box-shadow 0.3s;
        }
        .form-control[readonly] { /* این استایل از قبل وجود داشت */
            background-color: rgba(55, 65, 81, 0.4);
            opacity: 0.7;
            cursor: not-allowed;
        }
        .form-control:focus {
            outline: none;
            border-color: var(--input-focus-border);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.3); 
        }
        .submit-btn {
            background: var(--primary-color); color: white; padding: 12px 25px;
            border: none; border-radius: 8px; cursor: pointer; font-size: 16px;
            font-weight: 500; width: 100%; margin-top: 15px; 
            transition: background-color 0.3s, transform 0.2s;
            display: flex; align-items: center; justify-content: center; gap: 8px;
        }
        .submit-btn:hover { background: var(--primary-hover); transform: translateY(-2px); }
        .message {
            padding: 15px; margin-bottom: 25px; border-radius: 8px;
            font-size: 14px; text-align: center;
            display: flex; align-items: center; justify-content: center; gap: 10px;
        }
        .message.success { background-color: rgba(16, 185, 129, 0.15); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.3); }
        .message.error { background-color: rgba(239, 68, 68, 0.15); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.3); }
        .message.info { background-color: var(--info-bg); color: var(--info-color); border: 1px solid var(--info-border); }

        .form-section {
            border-top: 1px solid var(--card-border);
            margin-top: 25px;
            padding-top: 25px;
        }
        .form-section h4 { /* استایل عنوان بخش‌های داخل فرم */
            font-size: 16px;
            font-weight: 600;
            color: #fff;
            margin-bottom: 20px; 
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .back-link {
            display: block; text-align: center; margin-top: 20px;
            color: var(--primary-color); text-decoration: none; font-size: 14px;
            transition: color 0.3s;
        }
        .back-link:hover { color: var(--primary-hover); }

        /* استایل برای بخش نمایش اطلاعات دیسکورد */
        .discord-info-section p {
            margin-bottom: 12px;
            font-size: 15px;
            line-height: 1.6;
            color: var(--text-color);
            background-color: rgba(55, 65, 81, 0.3); /* پس زمینه خیلی ملایم برای هر آیتم */
            padding: 8px 12px;
            border-radius: 6px;
            border: 1px solid var(--input-border);
        }
        .discord-info-section p strong {
            color: var(--text-muted-color);
            margin-left: 8px; /* فاصله لیبل از مقدار */
            font-weight: 500;
        }
        .discord-info-section .status-value { /* برای استفاده از رنگ‌های وضعیت */
            font-weight: 600;
        }
        .discord-info-section .status-verified { color: var(--verified-color) !important; }
        .discord-info-section .status-not-verified { color: var(--not-verified-color) !important; }
        .discord-info-section .fas, .discord-info-section .fab {
            margin-right: 6px; /* برای فارسی */
        }
        .discord-info-section .connect-prompt {
            font-size: 0.9em;
            color: var(--not-verified-color);
            margin-top: 10px;
            padding: 10px;
            background-color: rgba(248, 113, 113, 0.1);
            border: 1px solid rgba(248, 113, 113, 0.3);
            border-radius: 6px;
        }
        .discord-info-section .connect-prompt code {
            background-color: rgba(0,0,0,0.3);
            padding: 2px 5px;
            border-radius: 3px;
            color: #f87171;
        }
    </style>
<?php
?>
<div class="background-image"></div>
<div class="profile-container">
<div class="profile-container">

    <?php if ($user_type === 'staff' && $profile_data): ?>
    <div class="profile-box discord-info-box"> <h2><i class="fab fa-discord"></i> اطلاعات اتصال دیسکورد</h2>
        <div class="discord-info-section">
            <p>
                <strong><i class="fas fa-user-tag"></i> نام کاربری دیسکورد (Tag):</strong>
                <span><?= htmlspecialchars($profile_data['discord_id'] ?: 'هنوز متصل نشده') ?></span>
            </p>
            <p>
                <strong><i class="fas fa-hashtag"></i> آیدی عددی دیسکورد:</strong>
                <span><?= htmlspecialchars($profile_data['discord_id2'] ?: 'هنوز متصل نشده') ?></span>
            </p>
            <p>
                <strong><i class="fas fa-shield-alt"></i> مقام‌های شناسایی شده:</strong>
                <span><?= htmlspecialchars($profile_data['permissions'] ?: 'هنوز متصل نشده / مقامی یافت نشد') ?></span>
            </p>
            <p>
                <strong><i class="fas fa-link"></i> وضعیت کلی اتصال:</strong>
                <?php
                    $discord_conn_status_text = 'متصل نشده یا در انتظار تایید با <code>/connect</code>';
        $discord_conn_status_class = 'status-not-verified';
        if (isset($profile_data['discord_conn']) && (int)$profile_data['discord_conn'] === 1) {
            $discord_conn_status_text = 'متصل و تایید شده';
            $discord_conn_status_class = 'status-verified';
        }
        ?>
                <span class="status-value <?= $discord_conn_status_class ?>">
                    <?= $discord_conn_status_text ?>
                </span>
            </p>
            <?php if (!(isset($profile_data['discord_conn']) && (int)$profile_data['discord_conn'] === 1)): ?>
                <p class="connect-prompt">
                    <i class="fas fa-info-circle"></i> برای استفاده از امکانات کامل بات و تایید اتصال، لطفاً از دستور <code>/connect</code> در سرور دیسکورد استفاده کنید.
                </p>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
    <div class="profile-box"> <h1>
            <i class="fas fa-user-cog"></i>
            ویرایش پروفایل
            <span style="font-size: 0.7em; color: var(--text-muted-color);">(<?= htmlspecialchars($profile_data['username'] ?? 'کاربر') ?>)</span>
        </h1>

        <?php if ($message): ?>
            <div class="message <?= htmlspecialchars($message_type) ?>">
                <i class="fas <?= ($message_type === 'success') ? 'fa-check-circle' : (($message_type === 'info') ? 'fa-info-circle' : 'fa-exclamation-triangle') ?>"></i>
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

            <?php if ($user_type === 'staff'): // فیلدهای مخصوص استف?>
            <div class="form-section staff-section">
                <h4><i class="fas fa-user-tie"></i> اطلاعات تکمیلی استف</h4>
                <div class="form-group">
                    <label for="staff_age">سن:</label>
                    <input type="number" id="staff_age" name="staff_age" class="form-control" value="<?= htmlspecialchars($profile_data['age'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label for="staff_steam_id">آیدی استیم (اختیاری):</label>
                    <input type="text" id="staff_steam_id" name="staff_steam_id" class="form-control" value="<?= htmlspecialchars($profile_data['steam_id'] ?? '') ?>">
                </div>
                
                <?php if (isset($profile_data['is_verify'])): ?>
                    <div class="form-group">
                        <label>وضعیت احراز هویت استف (توسط ادمین):</label>
                        <input type="text" class="form-control" value="<?= $profile_data['is_verify'] ? 'تایید شده' : 'در انتظار تایید' ?>" readonly>
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