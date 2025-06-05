<?php
require_once __DIR__.'/../includes/config.php';
require_once __DIR__.'/../includes/auth_functions.php';

session_start();

if (!isset($_SESSION['is_owner']) || !$_SESSION['is_owner']) {
    header('Location: login.php');
    exit();
}

$conn = getDbConnection();
$currentPage = 'registration_requests'; // ۱. تعریف صفحه فعلی برای فعال شدن لینک صحیح در سایدبار
// --- منطق فیلتر کردن درخواست‌ها ---
$allowed_statuses = ['pending', 'approved', 'rejected', 'staff'];
$filter_status = $_GET['status'] ?? 'all'; // Default to 'all'

$sql = "SELECT * FROM `registration_requests`";
$params = [];
$types = '';

if (in_array($filter_status, $allowed_statuses)) {
    $sql .= " WHERE status = ?";
    $params[] = $filter_status;
    $types .= 's';
}

$sql .= " ORDER BY created_at DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$requests_result = $stmt->get_result();

// کوئری برای شمارش درخواست‌های معلق (برای نمایش در سایدبار)
$pending_requests_count = $conn->query("SELECT COUNT(id) as count FROM `registration_requests` WHERE status = 'pending'")->fetch_assoc()['count'];

?>
<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مدیریت درخواست‌های ثبت‌نام | پنل مدیریت</title>
    <link rel="stylesheet" href="/../assets/css/admin.css">
    <link rel="stylesheet" href="/../assets/css/admin_dashboard_redesign.css">
    <link rel="stylesheet" href="/../assets/css/custom-dialog.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        /* استایل برای دکمه‌های فیلتر */
        .filter-container {
            display: flex;
            gap: 10px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }
        .filter-btn {
            background: var(--card-bg-solid);
            color: var(--text-secondary);
            border: 1px solid var(--border-color);
            padding: 8px 16px;
            border-radius: var(--border-radius-sm);
            text-decoration: none;
            transition: all 0.2s ease;
            font-weight: 500;
        }
        .filter-btn:hover {
            background: var(--primary-hover);
            color: #fff;
            border-color: var(--primary-hover);
        }
        .filter-btn.active {
            background: var(--primary-color);
            color: #fff;
            border-color: var(--primary-color);
        }
    </style>
</head>
<body>

<div id="requestDetailsModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>جزئیات درخواست ثبت نام</h3>
            <span class="close-modal">&times;</span>
        </div>
        <div class="modal-body">
            <div class="request-details">
                <div class="detail-row"><span class="detail-label">کد پیگیری:</span><span class="detail-value" id="detail-tracking-code"></span></div>
                <div class="detail-row"><span class="detail-label">نام کامل:</span><span class="detail-value" id="detail-fullname"></span></div>
                <div class="detail-row"><span class="detail-label">نام کاربری:</span><span class="detail-value" id="detail-username"></span></div>
                <div class="detail-row"><span class="detail-label">ایمیل:</span><span class="detail-value" id="detail-email"></span></div>
                <div class="detail-row"><span class="detail-label">تلفن:</span><span class="detail-value" id="detail-phone"></span></div>
                <div class="detail-row"><span class="detail-label">سن:</span><span class="detail-value" id="detail-age"></span></div>
                <div class="detail-row"><span class="detail-label">آیدی دیسکورد:</span><span class="detail-value" id="detail-discord"></span></div>
                <div class="detail-row"><span class="detail-label">آیدی استیم:</span><span class="detail-value" id="detail-steam"></span></div>
                <div class="detail-row"><span class="detail-label">تاریخ درخواست:</span><span class="detail-value" id="detail-created-at"></span></div>
                <div class="detail-row"><span class="detail-label">وضعیت:</span><span class="detail-value" id="detail-status"></span></div>
            </div>
        </div>
    </div>
</div>

<div class="admin-layout">
    <?php include __DIR__.'/../includes/_sidebar.php'; // ۳. فراخوانی سایدبار از فایل مجزا ?>


    <main class="main-content">
        <header class="main-header">
            <h1 class="header-title">مدیریت درخواست‌های ثبت‌نام</h1>
        </header>

        <div class="filter-container">
            <a href="?" class="filter-btn <?= $filter_status === 'all' ? 'active' : '' ?>">همه</a>
            <a href="?status=pending" class="filter-btn <?= $filter_status === 'pending' ? 'active' : '' ?>">در حال بررسی</a>
            <a href="?status=approved" class="filter-btn <?= $filter_status === 'approved' ? 'active' : '' ?>">تایید شده</a>
            <a href="?status=rejected" class="filter-btn <?= $filter_status === 'rejected' ? 'active' : '' ?>">رد شده</a>
            <a href="?status=staff" class="filter-btn <?= $filter_status === 'staff' ? 'active' : '' ?>">استف</a>
        </div>

        <div class="admin-card">
            <h2><i class="fas fa-list-ul"></i> لیست درخواست‌ها</h2>
            <div class="table-container">
                <table class="user-table">
                    <thead>
                        <tr>
                            <th>کد پیگیری</th>
                            <th>نام کاربری</th>
                            <th>نام کامل</th>
                            <th>تاریخ درخواست</th>
                            <th>وضعیت</th>
                            <th>عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($requests_result->num_rows > 0): ?>
                            <?php while($request = $requests_result->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($request['tracking_code']) ?></td>
                                <td><?= htmlspecialchars($request['username']) ?></td>
                                <td><?= htmlspecialchars($request['fullname']) ?></td>
                                <td><?= date('Y/m/d H:i', strtotime($request['created_at'])) ?></td>
                                <td>
                                    <span class="status-badge <?= htmlspecialchars($request['status']) ?>">
                                        <?php
                                            switch ($request['status']) {
                                                case 'pending': echo 'در حال بررسی'; break;
                                                case 'approved': echo 'تایید شده'; break;
                                                case 'rejected': echo 'رد شده'; break;
                                                case 'staff': echo 'استف'; break;
                                                default: echo htmlspecialchars($request['status']);
                                            }
                                        ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="action-btn view-request" data-id="<?= $request['id'] ?>" title="مشاهده جزئیات"><i class="fas fa-eye"></i></button>
                                    <button class="action-btn approve-request" data-id="<?= $request['id'] ?>" title="تایید درخواست"><i class="fas fa-check"></i></button>
                                    <button class="action-btn staff-request" data-id="<?= $request['id'] ?>" title="تایید استف"><i class="fas fa-user-plus"></i></button>
                                    <button class="action-btn reject-request" data-id="<?= $request['id'] ?>" title="رد درخواست"><i class="fas fa-times"></i></button>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 20px;">هیچ درخواستی با این وضعیت یافت نشد.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>


<script src="/../assets/js/admin.js"></script>
<script src="/../assets/js/custom-dialog.js"></script>


</body>
</html>