<?php
require_once __DIR__.'/includes/config.php';
require_once __DIR__.'/includes/auth_functions.php'; // لازم برای getDbConnection

session_start();

// بررسی دسترسی ادمین
if (!isset($_SESSION['is_owner']) || !$_SESSION['is_owner']) {
    header('Location: login.php');
    exit();
}

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conn = getDbConnection();

    $username = trim($_POST['username']);
    $is_staff = isset($_POST['is_staff']) ? 1 : 0;
    $password = $_POST['password'];
    $email = trim($_POST['email']) ?: null;
    $phone = trim($_POST['phone']) ?: null;
    $fullname = trim($_POST['fullname']) ?: $username; // نام کامل، پیش‌فرض نام کاربری

    $has_user_panel = isset($_POST['access_user_panel']) ? 1 : 0;
    $is_owner = isset($_POST['access_admin_panel']) ? 1 : 0;
    $is_staff = isset($_POST['is_staff']) ? 1 : 0;

    // اعتبار سنجی
    if (empty($username) || empty($password)) {
        $message = "نام کاربری و رمز عبور الزامی هستند.";
        $message_type = 'error';
    } else {
        $hashed_password = password_hash($password, PASSWORD_BCRYPT);
        try {
            if ($is_staff) {
                // ***** ذخیره در staff-manage *****
                // ستون password و username اضافه شد
                $stmt = $conn->prepare("INSERT INTO `staff-manage` (username, password, email, phone, fullname, is_active) VALUES (?, ?, ?, ?, ?, 1)");
                $stmt->bind_param("sssss", $username, $hashed_password, $email, $phone, $fullname);
                $message_text = "استف";

            } else {
                // ***** ذخیره در users *****
                $stmt = $conn->prepare("INSERT INTO users (username, password, email, phone, fullname, is_owner, has_user_panel, is_staff, status, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, 0, 'active', 'manual')");
                $stmt->bind_param("sssssii", $username, $hashed_password, $email, $phone, $fullname, $is_owner, $has_user_panel);
                $message_text = "کاربر";
            }

            if ($stmt->execute()) {
                $message = "$message_text '$username' با موفقیت ایجاد شد.";
                $message_type = 'success';
            } else {
                 throw new Exception("خطا در ایجاد $message_text: " . $stmt->error);
            }
            $stmt->close();
            $conn->close();


        } catch (Exception $e) {
            $conn->rollback();
            $message = "خطا: " . $e->getMessage();
            // بررسی خطای تکراری بودن نام کاربری
            if ($conn->errno == 1062) {
                 $message = "خطا: نام کاربری '$username' قبلاً استفاده شده است.";
            }
            $message_type = 'error';
        }
        $conn->close();
    }
}
?>

<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ایجاد کاربر جدید | پنل مدیریت</title>
    <link rel="stylesheet" href="assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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