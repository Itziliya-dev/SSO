<?php
require_once __DIR__.'/../includes/config.php';
require_once __DIR__.'/../includes/auth_functions.php';
require_once __DIR__.'/../includes/database.php'; // اطمینان از وجود این خط
require_once __DIR__.'/../includes/header.php';

session_start();

// فقط ادمین اصلی به این صفحه دسترسی دارد
if (!isset($_SESSION['is_owner']) || !$_SESSION['is_owner']) {
    header('Location: login.php');
    exit();
}

// 1. افزایش امنیت: ایجاد توکن CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

$conn = getDbConnection();
$message = '';
$message_type = '';

// پردازش فرم در صورت ارسال
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 2. افزایش امنیت: بررسی توکن CSRF
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $message = "خطای امنیتی: درخواست نامعتبر است. لطفاً صفحه را رفرش کرده و مجدداً تلاش کنید.";
        $message_type = 'error';
    } else {
        // 3. بهینه‌سازی دیتابیس: استفاده از تراکنش و آپدیت مقیاس‌پذیر
        try {
            $conn->begin_transaction();

            // آرایه‌ای از تنظیماتی که می‌خواهیم آپدیت کنیم
            // برای اضافه کردن تنظیمات جدید، فقط کافیست کلید و مقدار آن را به این آرایه اضافه کنید
            $settings_to_update = [
                'login_notice_text'    => $_POST['login_notice_text'] ?? '',
                'login_notice_enabled' => isset($_POST['login_notice_enabled']) ? '1' : '0',
                'login_notice_expiry'  => !empty($_POST['login_notice_expiry']) ? $_POST['login_notice_expiry'] : null
            ];
            
            $stmt = $conn->prepare("UPDATE `settings` SET `setting_value` = ? WHERE `setting_key` = ?");

            foreach ($settings_to_update as $key => $value) {
                $stmt->bind_param('ss', $value, $key);
                $stmt->execute();
            }
            
            $stmt->close();
            $conn->commit();
            
            $message = "تنظیمات با موفقیت ذخیره شد.";
            $message_type = 'success';

        } catch (Exception $e) {
            $conn->rollback(); // بازگرداندن تغییرات در صورت بروز خطا
            $message = "خطا در ذخیره تنظیمات: " . $e->getMessage();
            $message_type = 'error';
        }
    }
}

// خواندن تنظیمات فعلی از دیتابیس برای نمایش در فرم
$result = $conn->query("SELECT * FROM `settings` WHERE `setting_key` LIKE 'login_notice_%'");
$settings = [];
while ($row = $result->fetch_assoc()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

// این متغیرها برای سایدبار استفاده می‌شوند
$currentPage = 'settings';
$pending_requests_count = $conn->query("SELECT COUNT(id) as count FROM `registration_requests` WHERE status = 'pending'")->fetch_assoc()['count'];
?>
    <title>تنظیمات | پنل مدیریت</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="../assets/css/admin_dashboard_redesign.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/assets/fonts/Vazirmatn-font-face.css">
<?php
?>

<div class="admin-layout">
    <?php include __DIR__.'/../includes/_sidebar.php'; ?>
    <main class="main-content">
        <header class="main-header">
            <h1 class="header-title"><i class="fas fa-cogs"></i> تنظیمات پنل</h1>
        </header>

        <div class="admin-card">
            <h2><i class="fas fa-bullhorn"></i> تنظیمات اعلان صفحه لاگین</h2>
            
            <?php if ($message): ?>
                <div class="notification <?= $message_type == 'success' ? 'success' : 'error' ?>" style="opacity:1; transform:none; margin-bottom:20px;">
                    <i class="fas fa-<?= $message_type == 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
                    <span><?= htmlspecialchars($message) ?></span>
                </div>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

                <div class="form-group">
                    <label for="login_notice_text">متن اعلان:</label>
                    <textarea id="login_notice_text" name="login_notice_text" class="form-control" rows="4"><?= htmlspecialchars($settings['login_notice_text'] ?? '') ?></textarea>
                </div>

                <div class="form-group">
                    <label for="login_notice_expiry">تاریخ انقضای اعلان (اختیاری):</label>
                    <input type="date" id="login_notice_expiry" name="login_notice_expiry" class="form-control" value="<?= htmlspecialchars($settings['login_notice_expiry'] ?? '') ?>">
                    <small class="form-hint">پس از این تاریخ، اعلان به طور خودکار غیرفعال می‌شود. اگر خالی بگذارید، تاریخ انقضا نخواهد داشت.</small>
                </div>

                <div class="checkbox-group">
                    <label>
                        <input type="checkbox" name="login_notice_enabled" value="1" <?= ($settings['login_notice_enabled'] ?? '0') == '1' ? 'checked' : '' ?>>
                        نمایش اعلان در صفحه لاگین فعال باشد
                    </label>
                </div>

                <button type="submit" class="submit-btn">
                    <i class="fas fa-save"></i> ذخیره تنظیمات
                </button>
            </form>
        </div>
    </main>
</div>

</body>
</html>