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

// --- بخش اصلاح شده ---
// ۱. تمام متغیرها را در ابتدای فایل با مقدار پیش‌فرض تعریف می‌کنیم
$message = '';
$message_type = '';
$username = '';
$fullname = '';
$email = '';
$phone = '';

$currentPage = 'create_user'; // ۱. تعریف صفحه فعلی برای فعال شدن لینک صحیح در سایدبار


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conn = null;
    try {
        // ۲. مقادیر را از POST دریافت می‌کنیم. متغیرها از قبل تعریف شده‌اند.
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $email = trim($_POST['email'] ?? null);
        $phone = trim($_POST['phone'] ?? null);
        $fullname = trim($_POST['fullname'] ?? '') ?: $username;

        $has_user_panel = isset($_POST['access_user_panel']) ? 1 : 0;
        $is_owner_permission = isset($_POST['access_admin_panel']) ? 1 : 0;
        $is_staff_checkbox = isset($_POST['is_staff']) ? 1 : 0;
        
        $conn = getDbConnection();

        if (empty($username) || empty($password)) {
            throw new Exception("نام کاربری و رمز عبور الزامی هستند.");
        }
        
        $hashed_password = password_hash($password, PASSWORD_BCRYPT);

        if ($is_staff_checkbox) {
            $stmt = $conn->prepare("INSERT INTO `staff-manage` (username, password, email, phone, fullname, is_active, is_verify) VALUES (?, ?, ?, ?, ?, 1, 1)");
            $stmt->bind_param("sssss", $username, $hashed_password, $email, $phone, $fullname);
            $message_text = "استف";
        } else {
            $stmt = $conn->prepare("INSERT INTO users (username, password, email, phone, fullname, is_owner, has_user_panel, is_staff, status, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, 0, 'active', 'manual')");
            $stmt->bind_param("sssssii", $username, $hashed_password, $email, $phone, $fullname, $is_owner_permission, $has_user_panel);
            $message_text = "کاربر";
        }

        if ($stmt->execute()) {
            $message = "$message_text '$username' با موفقیت ایجاد شد.";
            $message_type = 'success';
            // پاک کردن مقادیر پس از موفقیت
            $username = $fullname = $email = $phone = '';
        } else {
            throw new Exception($stmt->error);
        }
        $stmt->close();

    } catch (Exception $e) {
        $message = "خطا: " . $e->getMessage();
        // ۳. حالا اینجا $username همیشه تعریف شده است (حتی اگر خالی باشد)
        if (isset($conn) && $conn->errno == 1062) {
            $message = "خطا: نام کاربری '$username' قبلاً استفاده شده است.";
        }
        $message_type = 'error';
    } finally {
        if ($conn) $conn->close();
    }
}

// برای سایدبار
$currentPage = 'create_user';
$pending_requests_count = 0;
$conn_sidebar = getDbConnection();
if ($conn_sidebar) {
    $pending_requests_count_result = $conn_sidebar->query("SELECT COUNT(id) as count FROM `registration_requests` WHERE status = 'pending'");
    if ($pending_requests_count_result) {
        $pending_requests_count = $pending_requests_count_result->fetch_assoc()['count'];
    }
    $conn_sidebar->close();
}
?>
<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ایجاد کاربر جدید | پنل مدیریت</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="../assets/css/admin_dashboard_redesign.css">
    <link rel="stylesheet" href="../assets/css/custom-dialog.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>

<div class="admin-layout">
    
    <?php include __DIR__.'/../includes/_sidebar.php'; ?>

    
    <main class="main-content">
        <header class="main-header">
            <h1 class="header-title"><i class="fas fa-user-plus"></i> ایجاد کاربر جدید</h1>
        </header>

        <div class="admin-card">
            <?php if ($message): ?>
                <div class="notification <?= $message_type == 'success' ? 'success' : 'error' ?>" style="opacity:1; transform:none; margin-bottom:20px;">
                    <i class="fas fa-<?= $message_type == 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
                    <span><?= htmlspecialchars($message) ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" action="create_user.php">
                <div class="form-row">
                    <div class="form-group">
                        <label for="username">نام کاربری:</label>
                        <input type="text" id="username" name="username" class="form-control" value="<?= htmlspecialchars($username) ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="password">رمز عبور:</label>
                        <input type="password" id="password" name="password" class="form-control" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="fullname">نام کامل (اختیاری):</label>
                        <input type="text" id="fullname" name="fullname" class="form-control" value="<?= htmlspecialchars($fullname) ?>">
                    </div>
                </div>
                 <div class="form-row">
                    <div class="form-group">
                        <label for="email">ایمیل (اختیاری):</label>
                        <input type="email" id="email" name="email" class="form-control" value="<?= htmlspecialchars($email) ?>">
                    </div>
                    <div class="form-group">
                        <label for="phone">تلفن (اختیاری):</label>
                        <input type="tel" id="phone" name="phone" class="form-control" value="<?= htmlspecialchars($phone) ?>">
                    </div>
                </div>

                <div class="checkbox-group">
                    <h4>نوع کاربر:</h4>
                    <label>
                        <input type="checkbox" name="is_staff" value="1" id="is_staff_checkbox">
                        این کاربر **استف** است (اطلاعات در جدول `staff-manage` ذخیره می‌شود)
                    </label>
                    <small class="form-hint">توصیه می‌شود استف‌ها از طریق فرم درخواست ثبت‌نام کنند تا تمام اطلاعاتشان به طور کامل ثبت شود.</small>
                </div>
                
                <div class="checkbox-group" id="user_permissions_group">
                    <h4>دسترسی‌های کاربر عادی:</h4>
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
            </form>
        </div>
    </main>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const isStaffCheckbox = document.getElementById('is_staff_checkbox');
        const userPermissionsGroup = document.getElementById('user_permissions_group');

        function togglePermissionsView() {
            if (userPermissionsGroup) {
                userPermissionsGroup.style.display = isStaffCheckbox.checked ? 'none' : 'block';
            }
        }

        if (isStaffCheckbox) {
            isStaffCheckbox.addEventListener('change', togglePermissionsView);
            togglePermissionsView();
        }
    });
</script>
</body>
</html>