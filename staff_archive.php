<?php
require_once __DIR__.'/includes/config.php';
require_once __DIR__.'/includes/auth_functions.php';

session_start();

if (!isset($_SESSION['is_owner']) || !$_SESSION['is_owner']) {
    header('Location: login.php');
    exit();
}

$conn = getDbConnection();
$currentPage = 'staff_archive'; // ۱. تعریف صفحه فعلی برای فعال شدن لینک صحیح در سایدبار
$archive_query = "SELECT * FROM `deleted_staff` ORDER BY `deleted_at` DESC";
$result = $conn->query($archive_query);
$archived_staff = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $archived_staff[] = $row;
    }
}

// برای بج درخواست‌ها در سایدبار
$pending_requests_count = $conn->query("SELECT COUNT(id) as count FROM `registration_requests` WHERE status = 'pending'")->fetch_assoc()['count'];
$conn->close();
?>
<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>آرشیو استف‌های دیموت شده | پنل مدیریت</title>
    
    <link rel="stylesheet" href="assets/css/admin.css">
    <link rel="stylesheet" href="assets/css/admin_dashboard_redesign.css">
    <link rel="stylesheet" href="assets/css/custom-dialog.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        .info-notice {
            padding: 15px 20px;
            margin-bottom: 25px;
            border-radius: var(--border-radius-sm);
            background-color: rgba(var(--info-color-rgb, 33, 150, 243), 0.1);
            border: 1px solid rgba(var(--info-color-rgb, 33, 150, 243), 0.3);
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            gap: 15px;
            font-size: 14px;
            line-height: 1.8;
        }
        .info-notice i { color: var(--info-color, #2196f3); }
        .reason-cell { white-space: normal; max-width: 280px; text-align: right; line-height: 1.7; }
        .no-archive { text-align: center; padding: 50px 20px; color: var(--text-muted); }
        .no-archive i { font-size: 36px; margin-bottom: 15px; display: block; }
        .modal-reason { background-color: rgba(0,0,0,0.2); padding: 10px; border-radius: var(--border-radius-sm); white-space: pre-wrap; line-height: 1.8; color: var(--text-secondary); margin-top: 5px;}
        :root { --info-color-rgb: 33, 150, 243; }
    </style>
</head>
<body>

<div id="archiveDetailsModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modal-archive-title">جزئیات استف دیموت شده</h3>
            <span class="close-modal">&times;</span>
        </div>
        <div class="modal-body">
            <div class="user-details">
                <div class="detail-row"><span class="detail-label">نام کامل:</span><span id="modal-fullname" class="detail-value"></span></div>
                <div class="detail-row"><span class="detail-label">نام کاربری:</span><span id="modal-username" class="detail-value"></span></div>
                <div class="detail-row"><span class="detail-label">ایمیل:</span><span id="modal-email" class="detail-value"></span></div>
                <div class="detail-row"><span class="detail-label">تلفن:</span><span id="modal-phone" class="detail-value"></span></div>
                <div class="detail-row"><span class="detail-label">سن:</span><span id="modal-age" class="detail-value"></span></div>
                <div class="detail-row"><span class="detail-label">آیدی دیسکورد:</span><span id="modal-discord_id" class="detail-value"></span></div>
                <div class="detail-row"><span class="detail-label">آیدی استیم:</span><span id="modal-steam_id" class="detail-value"></span></div>
                <div class="detail-row"><span class="detail-label">سطوح دسترسی:</span><span id="modal-permissions" class="detail-value"></span></div>
                <hr style="border-color: rgba(255,255,255,0.1); margin: 10px 0;">
                <div class="detail-row"><span class="detail-label">تاریخ عضویت اولیه:</span><span id="modal-joined_at" class="detail-value"></span></div>
                <div class="detail-row"><span class="detail-label">تاریخ دیموت:</span><span id="modal-deleted_at" class="detail-value"></span></div>
                <div class="detail-row"><span class="detail-label">دیموت توسط:</span><span id="modal-deleted_by" class="detail-value"></span></div>
                <div>
                    <span class="detail-label">دلیل دیموت:</span>
                    <div id="modal-delete_reason" class="modal-reason"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="admin-layout">
    <?php include __DIR__.'/includes/_sidebar.php'; // ۳. فراخوانی سایدبار از فایل مجزا ?>


    <main class="main-content">
        <header class="main-header">
            <h1 class="header-title"><i class="fas fa-archive"></i> آرشیو استف‌ها</h1>
        </header>

        <div class="admin-card">
            <div class="info-notice">
                <i class="fas fa-info-circle"></i>
                <div><strong>توجه:</strong> استف‌های موجود در این لیست از سیستم مدیریت استف‌ها حذف شده‌اند و تنها سوابق آن‌ها در اینجا نگهداری می‌شود. امکان ویرایش یا بازگردانی مستقیم از این بخش وجود ندارد.</div>
            </div>

            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 15px;">
                <h2><i class="fas fa-history"></i> لیست سوابق دیموت</h2>
                <input type="text" id="archiveSearch" class="form-control" placeholder="جستجو در آرشیو..." style="width: 300px;">
            </div>
            
            <div class="table-container">
                <table class="user-table">
                    <thead>
                        <tr>
                            <th>نام کامل</th>
                            <th>نام کاربری</th>
                            <th>مقام قبلی</th>
                            <th>تاریخ دیموت</th>
                            <th>دیموت توسط</th>
                            <th style="min-width: 250px;">دلیل دیموت</th>
                            <th>عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($archived_staff)): ?>
                            <?php foreach ($archived_staff as $staff): ?>
                                <tr data-id="<?= htmlspecialchars($staff['id']) ?>">
                                    <td><?= htmlspecialchars($staff['fullname']) ?></td>
                                    <td><?= htmlspecialchars($staff['username']) ?></td>
                                    <td><?= htmlspecialchars($staff['permissions'] ?? 'تعریف نشده') ?></td>
                                    <td><?= date('Y/m/d H:i', strtotime($staff['deleted_at'])) ?></td>
                                    <td><?= htmlspecialchars($staff['deleted_by']) ?></td>
                                    <td class="reason-cell"><?= nl2br(htmlspecialchars($staff['delete_reason'])) ?></td>
                                    <td class="actions">
                                        <button class="action-btn view-details" 
                                            title="مشاهده جزئیات کامل"
                                            data-original_id="<?= htmlspecialchars($staff['original_id']) ?>"
                                            data-fullname="<?= htmlspecialchars($staff['fullname']) ?>"
                                            data-username="<?= htmlspecialchars($staff['username']) ?>"
                                            data-email="<?= htmlspecialchars($staff['email'] ?? '---') ?>"
                                            data-phone="<?= htmlspecialchars($staff['phone'] ?? '---') ?>"
                                            data-age="<?= htmlspecialchars($staff['age'] ?? '---') ?>"
                                            data-discord_id="<?= htmlspecialchars($staff['discord_id'] ?? '---') ?>"
                                            data-steam_id="<?= htmlspecialchars($staff['steam_id'] ?? '---') ?>"
                                            data-permissions="<?= htmlspecialchars($staff['permissions'] ?? '---') ?>"
                                            data-joined_at="<?= date('Y/m/d H:i', strtotime($staff['joined_at'])) ?>"
                                            data-deleted_at="<?= date('Y/m/d H:i', strtotime($staff['deleted_at'])) ?>"
                                            data-deleted_by="<?= htmlspecialchars($staff['deleted_by']) ?>"
                                            data-delete_reason="<?= htmlspecialchars($staff['delete_reason']) ?>">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7">
                                    <div class="no-archive">
                                        <i class="fas fa-box-open"></i>
                                        <p>هیچ استفی در آرشیو یافت نشد.</p>
                                    </div>
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
<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('archiveSearch');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase().trim();
            const rows = document.querySelectorAll('.user-table tbody tr');
            rows.forEach(row => {
                if (row.querySelector('.no-archive')) return; 
                const rowText = row.textContent.toLowerCase();
                row.style.display = rowText.includes(searchTerm) ? '' : 'none';
            });
        });
    }

    const modal = document.getElementById('archiveDetailsModal');
    const modalCloseBtn = modal.querySelector('.close-modal');
    
    if(modalCloseBtn) {
        modalCloseBtn.addEventListener('click', () => modal.style.display = 'none');
    }
    window.addEventListener('click', (event) => {
        if (event.target == modal) modal.style.display = 'none';
    });

    document.querySelectorAll('.action-btn.view-details').forEach(button => {
        button.addEventListener('click', function() {
            const data = this.dataset;
            document.getElementById('modal-archive-title').textContent = `جزئیات استف: ${data.fullname}`;
            document.getElementById('modal-fullname').textContent = data.fullname;
            document.getElementById('modal-username').textContent = data.username;
            document.getElementById('modal-email').textContent = data.email;
            document.getElementById('modal-phone').textContent = data.phone;
            document.getElementById('modal-age').textContent = data.age;
            document.getElementById('modal-discord_id').textContent = data.discord_id;
            document.getElementById('modal-steam_id').textContent = data.steam_id;
            document.getElementById('modal-permissions').textContent = data.permissions;
            document.getElementById('modal-joined_at').textContent = data.joined_at;
            document.getElementById('modal-deleted_at').textContent = data.deleted_at;
            document.getElementById('modal-deleted_by').textContent = data.deleted_by;
            document.getElementById('modal-delete_reason').textContent = data.delete_reason;
            modal.style.display = 'block';
        });
    });

    // تمام کدهای مربوط به SweetAlert2 و دکمه بازگردانی حذف شده است.
});
</script>
</body>
</html>