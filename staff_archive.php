<?php
// فراخوانی فایل‌های ضروری
require_once __DIR__.'/includes/config.php';
require_once __DIR__.'/includes/auth_functions.php';

// شروع نشست
session_start();

// بررسی دسترسی ادمین
if (!isset($_SESSION['is_owner']) || !$_SESSION['is_owner']) {
    header('Location: login.php');
    exit();
}

// اتصال به دیتابیس
$conn = getDbConnection();

// دریافت تمام استف‌های حذف شده از جدول آرشیو
$archive_query = "SELECT * FROM `deleted_staff` ORDER BY `deleted_at` DESC";
$result = $conn->query($archive_query);

$archived_staff = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $archived_staff[] = $row;
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>آرشیو استف‌های دیموت شده | پنل مدیریت</title>
    
    <link rel="stylesheet" href="assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;600&display=swap" rel="stylesheet">

    
    <style>
        .info-notice {
            padding: 15px 20px;
            margin-bottom: 25px;
            border-radius: var(--border-radius-sm);
            background-color: rgba(33, 150, 243, 0.1); /* استفاده از رنگ --info-color */
            border: 1px solid rgba(33, 150, 243, 0.3);
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            gap: 15px;
            font-size: 14px;
            line-height: 1.8;
        }
        .reason-cell {
            white-space: normal; 
            max-width: 300px;
            text-align: right;
            line-height: 1.7;
        }
        .no-archive {
            text-align: center;
            padding: 60px;
            color: var(--text-muted);
        }
        .no-archive i {
            font-size: 40px;
            margin-bottom: 15px;
            display: block;
        }
        .modal-reason {
            background-color: rgba(0,0,0,0.2);
            padding: 10px;
            border-radius: var(--border-radius-sm);
            white-space: pre-wrap; 
            line-height: 1.8;
            color: var(--text-secondary);
            margin-top: 5px;
        }
    </style>
</head>
<body>

<div class="admin-container">
    <header class="admin-header">
        <h1 class="admin-title">
            <i class="fas fa-archive"></i>
            آرشیو استف‌ها
        </h1>
        <a href="admin_panel.php" class="btn-panel">
            <i class="fas fa-arrow-left"></i>
            بازگشت به پنل
        </a>
    </header>

    <main>
        <div class="admin-card">
            <div class="info-notice">
                <i class="fas fa-info-circle"></i>
                <div>
                    <strong>توجه:</strong> به دلایل امنیتی، ویرایش اطلاعات استف‌های آرشیو شده از طریق این پنل امکان‌پذیر نمی‌باشد. در صورت نیاز به هرگونه تغییر، لطفاً به دولوپر مجموعه اطلاع دهید .
                </div>
            </div>
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 15px;">
                <h2><i class="fas fa-history"></i>لیست سوابق</h2>
                <div class="form-group" style="margin-bottom: 0; min-width: 300px;">
                    <input type="text" id="archiveSearch" class="form-control glass-input" placeholder="جستجو در نام، یوزرنیم، دلیل و...">
                </div>
            </div>
            
            <div class="table-container">
                <table class="user-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>نام کامل</th>
                            <th>نام کاربری</th>
                            <th>مقام</th> <th>تاریخ حذف</th>
                            <th>حذف توسط</th>
                            <th>دلیل دیموت</th>
                            <th>عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($archived_staff)): ?>
                            <?php foreach ($archived_staff as $staff): ?>
                                <tr>
                                    <td><?= htmlspecialchars($staff['id']) ?></td>
                                    <td><?= htmlspecialchars($staff['fullname']) ?></td>
                                    <td><?= htmlspecialchars($staff['username']) ?></td>
                                    <td><?= htmlspecialchars($staff['permissions'] ?? 'تعریف نشده') ?></td> <td><?= date('Y/m/d H:i', strtotime($staff['deleted_at'])) ?></td>
                                    <td><?= htmlspecialchars($staff['deleted_by']) ?></td>
                                    <td class="reason-cell"><?= nl2br(htmlspecialchars($staff['delete_reason'])) ?></td>
                                    <td>
                                        <button class="action-btn view-details" 
                                            title="مشاهده جزئیات کامل"
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
                                <td colspan="8"> <div class="no-archive">
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

<div id="archiveDetailsModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modal-title">جزئیات استف دیموت شده</h3>
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
                <div class="detail-row"><span class="detail-label">تاریخ عضویت:</span><span id="modal-joined_at" class="detail-value"></span></div>
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    // فعال‌سازی جستجو
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

    // مدیریت مدال
    const modal = document.getElementById('archiveDetailsModal');
    const closeBtn = modal.querySelector('.close-modal');
    
    document.querySelectorAll('.action-btn.view-details').forEach(button => {
        button.addEventListener('click', function() {
            const data = this.dataset;
            document.getElementById('modal-title').textContent = `جزئیات استف: ${data.fullname}`;
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

    closeBtn.addEventListener('click', () => {
        modal.style.display = 'none';
    });

    window.addEventListener('click', (event) => {
        if (event.target == modal) {
            modal.style.display = 'none';
        }
    });
});
</script>

</body>
</html>