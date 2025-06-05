<?php
require_once __DIR__.'/includes/config.php';
require_once __DIR__.'/includes/auth_functions.php';

session_start();

if (!isset($_SESSION['is_owner']) || !$_SESSION['is_owner']) {
    header('Location: login.php');
    exit();
}

// پردازش عملیات (بدون تغییر)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conn = getDbConnection();
    if (isset($_POST['mark_all_read'])) {
        $stmt = $conn->prepare("UPDATE login_attempts SET viewed = 1 WHERE viewed = 0");
        $stmt->execute();
    } elseif (isset($_POST['mark_as_read']) && isset($_POST['alert_id'])) {
        $stmt = $conn->prepare("UPDATE login_attempts SET viewed = 1 WHERE id = ?");
        $stmt->bind_param("i", $_POST['alert_id']);
        $stmt->execute();
    } elseif (isset($_POST['delete_alert']) && isset($_POST['alert_id'])) {
        $stmt = $conn->prepare("DELETE FROM login_attempts WHERE id = ?");
        $stmt->bind_param("i", $_POST['alert_id']);
        $stmt->execute();
    } elseif (isset($_POST['delete_all'])) {
        $stmt = $conn->prepare("DELETE FROM login_attempts");
        $stmt->execute();
    }
    // برای جلوگیری از ارسال مجدد فرم با رفرش، می‌توان کاربر را به همین صفحه هدایت کرد
    header("Location: security_alerts.php");
    exit();
}

$currentPage = 'security_alerts'; // ۱. تعریف صفحه فعلی برای فعال شدن لینک صحیح در سایدبار
$conn = getDbConnection();
$alerts_result = $conn->query("
    SELECT * FROM login_attempts 
    WHERE attempt_time > (NOW() - INTERVAL 7 DAY)
    ORDER BY viewed ASC, attempt_time DESC
");

// برای بج درخواست‌ها در سایدبار
$pending_requests_count = $conn->query("SELECT COUNT(id) as count FROM `registration_requests` WHERE status = 'pending'")->fetch_assoc()['count'];
?>
<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>هشدارهای امنیتی | پنل مدیریت</title>
    
    <link rel="stylesheet" href="assets/css/admin.css">
    <link rel="stylesheet" href="assets/css/admin_dashboard_redesign.css">
    <link rel="stylesheet" href="assets/css/custom-dialog.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        /* استایل‌های سفارشی برای این صفحه که با تم جدید هماهنگ شده‌اند */
        .status-badge.status-new { background-color: rgba(var(--warning-color-rgb, 255, 193, 7), 0.2); color: var(--warning-color, #ffc107); }
        .status-badge.status-viewed { background-color: rgba(var(--success-color-rgb, 0, 200, 83), 0.2); color: var(--success-color, #00c853); }
        
        .alert-actions-group { display: flex; gap: 10px; margin-bottom: 20px; }
        .alert-actions-group .submit-btn { padding: 8px 15px; font-size: 14px;}
        .alert-actions-group .submit-btn.delete { background-color: rgb(220, 53, 69); }
        .alert-actions-group .submit-btn.delete:hover { background-color: rgb(200, 35, 51); }
        
        :root { /* تعریف متغیرهای RGB برای استفاده در rgba */
            --warning-color-rgb: 255, 193, 7;
            --success-color-rgb: 0, 200, 83;
        }
    </style>
</head>
<body>

<div class="admin-layout">
    <?php include __DIR__.'/includes/_sidebar.php'; // ۳. فراخوانی سایدبار از فایل مجزا ?>


    <main class="main-content">
        <header class="main-header">
            <h1 class="header-title"><i class="fas fa-shield-alt"></i> هشدارهای امنیتی </h1>
        </header>

        <div class="admin-card">
            <div class="alert-actions-group">
                <form method="POST" style="display: inline;">
                    <button type="submit" name="mark_all_read" class="submit-btn">
                        <i class="fas fa-check-double"></i> علامت‌گذاری همه به عنوان خوانده شده
                    </button>
                </form>
                <form method="POST" style="display: inline;">
                    <button type="submit" name="delete_all" class="submit-btn delete">
                        <i class="fas fa-trash-alt"></i> حذف تمام هشدارها
                    </button>
                </form>
            </div>
            
            <div class="table-container">
                <table class="user-table">
                    <thead>
                        <tr>
                            <th>شناسه</th>
                            <th>نام کاربری</th>
                            <th>آدرس IP</th>
                            <th>تاریخ و زمان تلاش</th>
                            <th>وضعیت</th>
                            <th>عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($alerts_result && $alerts_result->num_rows > 0): ?>
                            <?php while($alert = $alerts_result->fetch_assoc()): ?>
                            <tr>
                                <td><?= $alert['id'] ?></td>
                                <td><?= htmlspecialchars($alert['username']) ?></td>
                                <td><?= htmlspecialchars($alert['ip_address']) ?></td>
                                <td><?= date('Y/m/d H:i', strtotime($alert['attempt_time'])) ?></td>
                                <td>
                                    <span class="status-badge <?= $alert['viewed'] ? 'status-viewed' : 'status-new' ?>">
                                        <i class="fas fa-<?= $alert['viewed'] ? 'check-circle' : 'eye' ?>"></i>
                                        <?= $alert['viewed'] ? 'دیده شده' : 'جدید' ?>
                                    </span>
                                </td>
                                <td class="actions">
                                    <?php if (!$alert['viewed']): ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="alert_id" value="<?= $alert['id'] ?>">
                                        <button type="submit" name="mark_as_read" class="action-btn approve-request" title="علامت‌گذاری به عنوان خوانده شده">
                                            <i class="fas fa-check"></i>
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="alert_id" value="<?= $alert['id'] ?>">
                                        <button type="submit" name="delete_alert" class="action-btn delete-btn" title="حذف این هشدار">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 30px;">
                                    <i class="fas fa-shield-check" style="color: var(--success-color); font-size: 28px; margin-bottom:10px; display:block;"></i>
                                    در حال حاضر هیچ هشدار امنیتی جدیدی (تلاش ناموفق برای ورود) ثبت نشده است.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<div class="notification-container top-right"></div>

<script src="assets/js/custom-dialog.js"></script>
<script src="https://unpkg.com/@popperjs/core@2"></script> <script src="https://unpkg.com/tippy.js@6"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // مقداردهی اولیه Tippy.js برای تولتیپ‌ها
    tippy('[title]', {
        content(reference) { return reference.getAttribute('title'); },
        placement: 'top',
        theme: 'light-border', // یک تم متفاوت برای تنوع
        animation: 'shift-away'
    });

    // مدیریت تاییدیه برای فرم‌ها با استفاده از Dialog سفارشی
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', async function(event) {
            let confirmMessage = null;
            let confirmTitle = 'تایید عملیات';
            let needsConfirmation = false;

            if (this.querySelector('[name="mark_all_read"]')) {
                confirmMessage = 'آیا از علامت‌گذاری همه هشدارها به عنوان دیده شده اطمینان دارید؟';
                needsConfirmation = true;
            } else if (this.querySelector('[name="delete_all"]')) {
                confirmMessage = 'آیا از حذف تمام هشدارها اطمینان دارید؟ این عمل غیرقابل بازگشت است!';
                confirmTitle = 'اخطار حذف کلی';
                needsConfirmation = true;
            } else if (this.querySelector('[name="delete_alert"]')) {
                confirmMessage = 'آیا از حذف این هشدار اطمینان دارید؟';
                confirmTitle = 'تایید حذف';
                needsConfirmation = true;
            }
            // فرم "mark_as_read" تکی نیازی به تایید ندارد و مستقیماً ارسال می‌شود.

            if (needsConfirmation) {
                event.preventDefault(); // جلوگیری از ارسال فرم برای نمایش دیالوگ
                const userConfirmed = await Dialog.confirm(confirmTitle, confirmMessage);
                if (userConfirmed) {
                    this.submit(); // اگر کاربر تایید کرد، فرم را ارسال کن
                }
                // اگر کاربر انصراف داد، هیچ کاری انجام نمی‌شود چون event.preventDefault() فراخوانی شده.
            }
            // اگر needsConfirmation false باشد، فرم به طور عادی و بدون دیالوگ ارسال می‌شود.
        });
    });

    // کد مربوط به به‌روزرسانی بج هشدار در سایدبار (اگر در این صفحه هم نیاز است)
    // این کد مشابه کدی است که در admin_panel.php برای هشدارهای امنیتی داشتید
    // و اطمینان حاصل می‌کند که بج هشدار در سایدبار این صفحه هم به‌روز باشد.
    const securityAlertsBtn = document.getElementById('securityAlertsBtnInPage'); // از ID جدید استفاده می‌کنیم
    const alertBadge = document.getElementById('alertBadgeInPage'); // از ID جدید استفاده می‌کنیم
    
    if (securityAlertsBtn && alertBadge) {
        let unreadAlertsCount = 0;
        let alertsCheckInterval;

        const checkUnreadAlerts = async () => {
            try {
                const response = await fetch('includes/check_alerts.php');
                if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
                const data = await response.json();
                updateAlertCount(data.count || 0);
            } catch (error) {
                console.error('Error fetching alerts for sidebar badge:', error);
            }
        };

        const updateAlertCount = (count) => {
            unreadAlertsCount = count;
            securityAlertsBtn.classList.remove('has-alerts', 'critical-alert-btn');
            alertBadge.classList.remove('new-alert', 'critical-alert');
            
            if (unreadAlertsCount > 0) {
                alertBadge.style.display = 'flex';
                alertBadge.textContent = unreadAlertsCount > 9 ? '9+' : unreadAlertsCount;
                
                if (unreadAlertsCount >= 5) {
                    securityAlertsBtn.classList.add('critical-alert-btn');
                    alertBadge.classList.add('critical-alert');
                } else {
                    securityAlertsBtn.classList.add('has-alerts');
                    alertBadge.classList.add('new-alert');
                }
            } else {
                alertBadge.style.display = 'none';
            }
        };
        // چون در این صفحه هستیم و عملیات "خوانده شده" انجام می‌شود،
        // بهتر است پس از هر عملیات، بج را آپدیت کنیم.
        // اما چون صفحه پس از هر عملیات POST رفرش می‌شود،
        // بج به طور خودکار با اجرای checkUnreadAlerts در لود بعدی آپدیت می‌شود.
        checkUnreadAlerts(); // بررسی اولیه
        alertsCheckInterval = setInterval(checkUnreadAlerts, 15000); // بررسی دوره‌ای
        window.addEventListener('beforeunload', () => clearInterval(alertsCheckInterval));
    }
});
</script>

</body>
</html>