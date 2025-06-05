<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__.'/../includes/config.php';
require_once __DIR__.'/../includes/auth_functions.php';

session_start();

if (!isset($_SESSION['is_owner']) || !$_SESSION['is_owner']) {
    header('Location: login.php');
    exit();
}

$message = '';
$message_type = '';
$conn = null; // اتصال را در اینجا null می‌کنیم تا در finally قابل بررسی باشد

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $conn = getDbConnection(); // اتصال در ابتدای try باز می‌شود

        $username = trim($_POST['username']);
        // $is_staff در پایین‌تر بر اساس چک‌باکس تعیین می‌شود
        $password = $_POST['password'];
        $email = trim($_POST['email']) ?: null;
        $phone = trim($_POST['phone']) ?: null;
        $fullname = trim($_POST['fullname']) ?: $username;

        $has_user_panel = isset($_POST['access_user_panel']) ? 1 : 0;
        $is_owner_permission = isset($_POST['access_admin_panel']) ? 1 : 0; // نام متغیر تغییر کرد تا با is_staff تداخل نداشته باشد
        $is_staff_checkbox = isset($_POST['is_staff']) ? 1 : 0; // مقدار چک‌باکس استف

        if (empty($username) || empty($password)) {
            $message = "نام کاربری و رمز عبور الزامی هستند.";
            $message_type = 'error';
        } else {
            $hashed_password = password_hash($password, PASSWORD_BCRYPT);

            // $conn->begin_transaction(); // اگر نیاز به تراکنش دارید (برای عملیات پیچیده‌تر)

            if ($is_staff_checkbox) {
                $stmt = $conn->prepare("INSERT INTO `staff-manage` (username, password, email, phone, fullname, is_active) VALUES (?, ?, ?, ?, ?, 1)");
                $stmt->bind_param("sssss", $username, $hashed_password, $email, $phone, $fullname);
                $message_text = "استف";
            } else {
                // برای کاربر عادی، is_staff همیشه 0 است
                $stmt = $conn->prepare("INSERT INTO users (username, password, email, phone, fullname, is_owner, has_user_panel, is_staff, status, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, 0, 'active', 'manual')");
                $stmt->bind_param("sssssii", $username, $hashed_password, $email, $phone, $fullname, $is_owner_permission, $has_user_panel);
                $message_text = "کاربر";
            }

            if ($stmt->execute()) {
                // $conn->commit(); // اگر از تراکنش استفاده می‌کنید
                $message = "$message_text '$username' با موفقیت ایجاد شد.";
                $message_type = 'success';
            } else {
                throw new Exception("خطا در ایجاد $message_text: " . $stmt->error);
            }
            $stmt->close();
            // $conn->close(); // این خط از اینجا حذف می‌شود
        }
    } catch (Exception $e) {
        // if ($conn) $conn->rollback(); // اگر از تراکنش استفاده می‌کنید و اتصال هنوز باز است
        $message = "خطا: " . $e->getMessage();
        if (isset($conn) && $conn->errno == 1062) { // بررسی می‌کنیم $conn تعریف شده باشد
            $message = "خطا: نام کاربری '$username' قبلاً استفاده شده است.";
        }
        $message_type = 'error';
    } finally {
        // این بلوک همیشه اجرا می‌شود
        if ($conn) { // فقط اگر اتصال باز باشد، آن را می‌بندیم
            $conn->close();
        }
    }
}
?>

<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ایجاد کاربر جدید | پنل مدیریت</title>
    <link rel="stylesheet" href="/../assets/css/admin.css">
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        body { background-color: #0a0a1a; } /* یا استفاده از background-image */
        .create-user-container { max-width: 700px; margin: 50px auto; }
        .form-control { width: 100%; padding: 12px; margin-bottom: 15px; border-radius: 8px; border: 1px solid rgba(255, 255, 255, 0.2); background: rgba(255, 255, 255, 0.1); color: white; }
        .checkbox-group { margin-bottom: 20px; background: rgba(255, 255, 255, 0.05); padding: 15px; border-radius: 8px;}
        .checkbox-group label { display: block; margin-bottom: 10px; cursor: pointer; }
        .checkbox-group input { margin-left: 10px; }
        .submit-btn { background: #27ae60; color: white; padding: 12px 25px; border: none; border-radius: 8px; cursor: pointer; font-size: 16px; }
        .submit-btn:hover { background: #219a52; }
        .message { padding: 15px; margin-bottom: 20px; border-radius: 8px; font-size: 14px; }
        .message.success { background-color: rgba(0, 200, 83, 0.3); color: #00c853; border: 1px solid #00c853; }
        .message.error { background-color: rgba(255, 107, 107, 0.3); color: #ff6b6b; border: 1px solid #ff6b6b; }
    </style>
</head>
<body>
<div class="create-user-container">
    <div class="admin-card">
        <h2>
            <i class="fas fa-user-plus"></i>
            ایجاد کاربر جدید
        </h2>

        <?php if ($message): ?>
            <div class="message <?= $message_type ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>نام کاربری:</label>
                <input type="text" name="username" class="form-control" required>
            </div>
            <div class="form-group">
                <label>رمز عبور:</label>
                <input type="password" name="password" class="form-control" required>
            </div>
             <div class="form-group">
                <label>نام کامل (اختیاری):</label>
                <input type="text" name="fullname" class="form-control">
            </div>
            <div class="form-group">
                <label>ایمیل (اختیاری):</label>
                <input type="email" name="email" class="form-control">
            </div>
            <div class="form-group">
                <label>تلفن (اختیاری):</label>
                <input type="tel" name="phone" class="form-control">
            </div>

            <div class="checkbox-group">
                <h4>دسترسی‌ها:</h4>
                <label>
                    <input type="checkbox" name="access_user_panel" value="1" checked>
                    دسترسی به پنل کاربری (dev-panel)
                </label>
                <label>
                    <input type="checkbox" name="access_admin_panel" value="1">
                    دسترسی به پنل مدیریت SSO (مدیر)
                </label>
            </div>

             <div class="checkbox-group">
            <h4>نوع کاربر:</h4>
            <label>
                <input type="checkbox" name="is_staff" value="1" id="is_staff_checkbox">
                این کاربر استف است
            </label>
            <small class="form-hint">
                        توصیه می شود از استف مورد نظر درخواست کنید که از پنل، درخواست خود را ارسال کند تا تمامی مراحل ثبت اطلاعات به طور اتوماتیک انجام شود و در سیستم اختلالی ایجاد نشود.
        </smaill>
             </div>
        <div class="checkbox-group" id="user_permissions_group"> <h4>دسترسی‌های کاربر عادی:</h4>
            <label>
                <input type="checkbox" name="access_user_panel" value="1" checked>
                دسترسی به پنل کاربری (dev-panel)
            </label>
            <label>
                <input type="checkbox" name="access_admin_panel" value="1">
                دسترسی به پنل مدیریت SSO (مدیر)
            </label>
        </div>
            

            <button type="submit" class="submit-btn">
                <i class="fas fa-save"></i> ایجاد کاربر
            </button>
             <a href="admin_panel.php" class="btn-panel" style="background: #555; margin-right: 10px; padding: 12px 20px; border-radius: 8px; text-decoration: none; color: white;">
                <i class="fas fa-arrow-left"></i> بازگشت
            </a>
        </form>
    </div>
</div>

    <script>
        // JS برای مخفی کردن دسترسی‌ها اگر کاربر استف است
        document.getElementById('is_staff_checkbox').addEventListener('change', function() {
            document.getElementById('user_permissions_group').style.display = this.checked ? 'none' : 'block';
        });
        // اجرای اولیه برای بارگذاری صفحه
         document.getElementById('user_permissions_group').style.display = document.getElementById('is_staff_checkbox').checked ? 'none' : 'block';
    </script>
</body>
</html>