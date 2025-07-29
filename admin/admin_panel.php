<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__.'/../includes/config.php';
require_once __DIR__.'/../includes/auth_functions.php';


if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (empty($_SESSION['permissions']['is_owner']) || empty($_SESSION['permissions']['can_view_dashboard'])) {
    header('Location: /Dashboard/dashboard.php');
    exit();
}

require_once __DIR__.'/../includes/header.php';


$conn = getDbConnection();

// کوئری‌های لازم برای ویجت‌های داشبورد
$currentPage = 'admin_panel'; // ۱. تعریف صفحه فعلی برای فعال شدن لینک صحیح در سایدبار


$pending_requests_query = $conn->query("SELECT * FROM `registration_requests` WHERE status = 'pending' ORDER BY created_at DESC LIMIT 5");
$pending_requests_count = $conn->query("SELECT COUNT(id) as count FROM `registration_requests` WHERE status = 'pending'")->fetch_assoc()['count'];

// شمارش کاربران فعال از جدول users
$user_count = $conn->query("SELECT COUNT(id) as count FROM `users` WHERE status = 'active'")->fetch_assoc()['count'];

// شمارش استف‌ها از جدول staff-manage
$staff_count = $conn->query("SELECT COUNT(id) as count FROM `staff-manage` WHERE is_active = 1")->fetch_assoc()['count'];

// [ خط اصلاح شده ]
// کوئری قبلی که باعث خطا شد حذف و با کوئری صحیح جایگزین شد

?>
    <title>داشبورد مدیریت | SSO Center</title>
    <link rel="stylesheet" href="/../assets/css/admin.css">
    <link rel="stylesheet" href="/../assets/css/admin_dashboard_redesign.css">
    <link rel="stylesheet" href="/../assets/css/custom-dialog.css">
    <link rel="stylesheet" href="/assets/fonts/Vazirmatn-font-face.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<?php
?>
<div id="requestDetailsModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>جزئیات درخواست ثبت نام</h3>
            <span class="close-modal">&times;</span>
        </div>
        <div class="modal-body">
            <div class="request-details">
                <div class="detail-row">
                    <span class="detail-label">کد پیگیری:</span>
                    <span class="detail-value" id="detail-tracking-code"></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">نام کامل:</span>
                    <span class="detail-value" id="detail-fullname"></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">نام کاربری:</span>
                    <span class="detail-value" id="detail-username"></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">ایمیل:</span>
                    <span class="detail-value" id="detail-email"></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">تلفن:</span>
                    <span class="detail-value" id="detail-phone"></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">سن:</span>
                    <span class="detail-value" id="detail-age"></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">آیدی دیسکورد:</span>
                    <span class="detail-value" id="detail-discord"></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">آیدی استیم:</span>
                    <span class="detail-value" id="detail-steam"></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">تاریخ درخواست:</span>
                    <span class="detail-value" id="detail-created-at"></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">وضعیت:</span>
                    <span class="detail-value" id="detail-status"></span>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="admin-layout">

    <?php include __DIR__.'/../includes/_sidebar.php'; // ۳. فراخوانی سایدبار از فایل مجزا?>

    <main class="main-content">

        <section class="dashboard-stats">
            <div class="stat-card">
                <div class="stat-icon" style="background-color: rgba(255, 193, 7, 0.2);"><i class="fas fa-user-clock" style="color: #ffc107;"></i></div>
                <div class="stat-info">
                    <span class="stat-title">درخواست‌های معلق</span>
                    <span class="stat-value"><?= htmlspecialchars($pending_requests_count) ?></span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background-color: rgba(0, 200, 83, 0.2);"><i class="fas fa-users" style="color: #00c853;"></i></div>
                <div class="stat-info">
                    <span class="stat-title">کاربران فعال</span>
                    <span class="stat-value"><?= htmlspecialchars($user_count) ?></span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background-color: rgba(33, 150, 243, 0.2);"><i class="fa-solid fa-users-line" style="color: #2196f3;"></i></div>
                <div class="stat-info">
                    <span class="stat-title">تعداد استف‌ها</span>
                    <span class="stat-value"><?= htmlspecialchars($staff_count) ?></span>
                </div>
            </div>
        </section>

        <div class="admin-card">
            <h2><i class="fas fa-hourglass-half"></i> آخرین درخواست‌های در حال بررسی</h2>
            <div class="table-container">
                <table class="user-table">
                    <thead>
                        <tr>
                            <th>کد پیگیری</th>
                            <th>نام کاربری</th>
                            <th>نام کامل</th>
                            <th>تاریخ درخواست</th>
                            <th>عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($pending_requests_query->num_rows > 0): ?>
                            <?php while ($request = $pending_requests_query->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($request['tracking_code']) ?></td>
                                <td><?= htmlspecialchars($request['username']) ?></td>
                                <td><?= htmlspecialchars($request['fullname']) ?></td>
                                <td><?= date('Y/m/d H:i', strtotime($request['created_at'])) ?></td>
                                <td>
                                    <button class="action-btn view-request" data-id="<?= $request['id'] ?>" title="مشاهده جزئیات">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="action-btn approve-request" data-id="<?= $request['id'] ?>" title="تایید درخواست">
                                        <i class="fas fa-check"></i>
                                    </button>
                                    <button class="action-btn staff-request" data-id="<?= $request['id'] ?>" title="تایید استف">
                                        <i class="fas fa-user-plus"></i> </button>
                                    <button class="action-btn reject-request" data-id="<?= $request['id'] ?>" title="رد درخواست">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" style="text-align: center; padding: 20px;">در حال حاضر درخواست جدیدی برای بررسی وجود ندارد.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="card-footer">
                <a href="registration_requests_page.php">مشاهده تمام درخواست‌ها <i class="fas fa-angle-left"></i></a>
            </div>
        </div>
    </main>
</div>

<script src="/../assets/js/admin.js"></script>
<script src="/../assets/js/custom-dialog.js"></script>

</body>
</html>