<?php
require_once __DIR__.'/../includes/config.php';
require_once __DIR__.'/../includes/auth_functions.php';

session_start();

// --- بخش API: پردازش درخواست‌های AJAX (بدون تغییر) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action'])) {
    if (!isset($_SESSION['is_owner']) || !$_SESSION['is_owner']) {
        echo json_encode(['success' => false, 'message' => 'دسترسی غیر مجاز']);
        exit();
    }

    $conn = getDbConnection();
    $action = $_POST['action'];
    $userId = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
    header('Content-Type: application/json');

    switch ($action) {
        case 'get_user_details':
            if ($userId > 0) {
                $stmt = $conn->prepare("SELECT id, username, fullname, email, phone, status, created_at, created_by, suspended_reason FROM users WHERE id = ?");
                $stmt->bind_param("i", $userId);
                $stmt->execute();
                $user = $stmt->get_result()->fetch_assoc();
                echo json_encode($user ? ['success' => true, 'user' => $user] : ['success' => false, 'message' => 'کاربر یافت نشد']);
            }
            break;

        case 'activate_user':
            if ($userId > 0) {
                $stmt = $conn->prepare("UPDATE users SET status = 'active', suspended_reason = NULL WHERE id = ?");
                $stmt->bind_param("i", $userId);
                $stmt->execute();
                echo json_encode(['success' => true, 'message' => 'کاربر با موفقیت فعال شد']);
            }
            break;

        case 'suspend_user':
            if ($userId > 0) {
                $reason = $_POST['reason'] ?? 'بدون دلیل';
                $stmt = $conn->prepare("UPDATE users SET status = 'suspended', suspended_reason = ? WHERE id = ?");
                $stmt->bind_param("si", $reason, $userId);
                $stmt->execute();
                echo json_encode(['success' => true, 'message' => 'کاربر با موفقیت غیرفعال شد']);
            }
            break;

        case 'delete_user':
            if ($userId > 0) {
                $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
                $stmt->bind_param("i", $userId);
                $stmt->execute();
                echo json_encode(['success' => true, 'message' => 'کاربر با موفقیت حذف شد']);
            }
            break;

        case 'reset_password':
            if ($userId > 0) {
                $newPassword = $_POST['new_password'];
                if (empty($newPassword) || $newPassword !== $_POST['confirm_password']) {
                    echo json_encode(['success' => false, 'message' => 'رمزهای عبور مطابقت ندارند']);
                    exit();
                }
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->bind_param("si", $hashedPassword, $userId);
                $stmt->execute();
                echo json_encode(['success' => true, 'message' => 'رمز عبور با موفقیت تغییر یافت']);
            }
            break;
    }
    exit();
}

// --- بخش نمایش صفحه ---
if (!isset($_SESSION['is_owner']) || !$_SESSION['is_owner']) {
    header('Location: /../login.php');
    exit();
}
$conn = getDbConnection();

// منطق فیلتر و جستجو (بدون تغییر)
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? 'all';

$whereConditions = [];
$params = [];
$types = '';

if (!empty($search)) {
    $whereConditions[] = "(username LIKE ? OR email LIKE ? OR phone LIKE ?)";
    $likeParam = "%$search%";
    $params = array_merge($params, [$likeParam, $likeParam, $likeParam]);
    $types .= 'sss';
}
if ($status !== 'all' && in_array($status, ['active', 'suspended'], true)) {
    $whereConditions[] = "status = ?";
    $params[] = $status;
    $types .= 's';
}

$whereClause = $whereConditions ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
$query = "SELECT id, username, email, phone, status, is_owner, has_user_panel FROM users $whereClause ORDER BY created_at DESC";
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// کوئری برای شمارش درخواست‌های معلق (برای نمایش در سایدبار)
$pending_requests_count = $conn->query("SELECT COUNT(id) as count FROM `registration_requests` WHERE status = 'pending'")->fetch_assoc()['count'];

$currentPage = 'users_management'; // ۱. تعریف صفحه فعلی برای فعال شدن لینک صحیح در سایدبار

?>
<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مدیریت کاربران | پنل مدیریت</title>
    <link rel="stylesheet" href="/../assets/css/admin.css">
    <link rel="stylesheet" href="/../assets/css/admin_dashboard_redesign.css">
    <link rel="stylesheet" href="/../assets/css/custom-dialog.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>

<div id="mainModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modalTitle"></h3>
            <span class="close-modal" id="modalClose">&times;</span>
        </div>
        <div class="modal-body" id="modalBody"></div>
    </div>
</div>

<div class="admin-layout">
    <?php include __DIR__.'/../includes/_sidebar.php'; // ۳. فراخوانی سایدبار از فایل مجزا?>


    <main class="main-content">
        <header class="main-header">
            <h1 class="header-title">مدیریت کاربران</h1>
            <a href="create_user.php" class="btn-primary"><i class="fas fa-user-plus"></i><span>ایجاد کاربر جدید</span></a>
        </header>

        <div class="admin-card filter-card">
            <h2><i class="fas fa-filter"></i> فیلتر کاربران</h2>
            <form method="GET" action="" class="filter-form">
                <div class="form-row">
                    <div class="form-group">
                        <label for="search">جستجو (نام کاربری، ایمیل، تلفن)</label>
                        <input type="text" id="search" name="search" class="form-control" value="<?= htmlspecialchars($search) ?>" placeholder="عبارت مورد نظر...">
                    </div>
                    <div class="form-group">
                        <label for="status">وضعیت</label>
                        <select id="status" name="status" class="form-control">
                            <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>همه</option>
                            <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>فعال</option>
                            <option value="suspended" <?= $status === 'suspended' ? 'selected' : '' ?>>غیرفعال</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <button type="submit" class="submit-btn"><i class="fas fa-search"></i> اعمال فیلتر</button>
                    </div>
                </div>
            </form>
        </div>

        <div class="admin-card">
            <h2><i class="fas fa-users"></i> لیست کاربران</h2>
            <div class="table-container">
                <table class="user-table">
                    <thead>
                        <tr>
                            <th>نام کاربری</th>
                            <th>ایمیل</th>
                            <th>وضعیت</th>
                            <th>دسترسی‌ها</th>
                            <th>عملیات</th>
                        </tr>
                    </thead>
                    <tbody id="users-table-body">
                        <?php foreach ($users as $user): ?>
                        <tr id="user-row-<?= $user['id'] ?>">
                            <td class="username"><?= htmlspecialchars($user['username']) ?></td>
                            <td><?= htmlspecialchars($user['email'] ?? '-') ?></td>
                            <td class="status-cell">
                                <span class="status-badge <?= $user['status'] === 'active' ? 'active' : 'suspended' ?>">
                                    <?= $user['status'] === 'active' ? 'فعال' : 'غیرفعال' ?>
                                </span>
                            </td>
                            <td class="permission-cell">
                                <?php
                                if ($user['is_owner'] && $user['has_user_panel']) {
                                    echo '<span class="permission-badge full-access">کامل</span>';
                                } elseif ($user['is_owner']) {
                                    echo '<span class="permission-badge owner-access">مدیریت</span>';
                                } elseif ($user['has_user_panel']) {
                                    echo '<span class="permission-badge server-access">سرور</span>';
                                } else {
                                    echo '<span class="permission-badge no-access">--</span>';
                                }
                            ?>
                            </td>
                            <td class="actions">
                                <button class="action-btn view-details" title="مشاهده" data-action="view" data-user-id="<?= $user['id'] ?>"><i class="fas fa-eye"></i></button>
                                <button class="action-btn reset-btn" title="ریست پسورد" data-action="reset_password" data-user-id="<?= $user['id'] ?>"><i class="fas fa-key"></i></button>
                                <?php if ($user['status'] === 'active'): ?>
                                    <button class="action-btn suspend-btn" title="غیرفعال" data-action="suspend" data-user-id="<?= $user['id'] ?>"><i class="fas fa-user-slash"></i></button>
                                <?php else: ?>
                                    <button class="action-btn activate-btn" title="فعال" data-action="activate" data-user-id="<?= $user['id'] ?>"><i class="fas fa-user-check"></i></button>
                                <?php endif; ?>
                                <button class="action-btn delete-btn" title="حذف" data-action="delete" data-user-id="<?= $user['id'] ?>"><i class="fas fa-trash"></i></button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<div class="notification-container top-right"></div>

<script src="/../assets/js/custom-dialog.js"></script>
<script>
// تابع کمکی برای فرمت تاریخ
function formatPersianDate(dateString) {
    if (!dateString) return '-';
    return new Intl.DateTimeFormat('fa-IR', { year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' }).format(new Date(dateString));
}

document.addEventListener('DOMContentLoaded', function() {
    const tableBody = document.getElementById('users-table-body');
    const modal = document.getElementById('mainModal');
    const modalTitle = document.getElementById('modalTitle');
    const modalBody = document.getElementById('modalBody');
    const modalClose = document.getElementById('modalClose');

    modalClose.onclick = () => modal.style.display = "none";
    window.onclick = (event) => {
        if (event.target == modal) modal.style.display = "none";
    };

    // مدیریت کلیک روی دکمه‌های عملیات با استفاده از Event Delegation
    tableBody.addEventListener('click', async function(e) {
        const button = e.target.closest('.action-btn');
        if (!button) return;

        const action = button.dataset.action;
        const userId = button.dataset.userId;
        const username = button.closest('tr').querySelector('.username').textContent;

        switch (action) {
            case 'delete':
                if (await Dialog.confirm('حذف کاربر', `آیا از حذف کاربر "${username}" اطمینان دارید؟ این عمل قابل بازگشت نیست.`)) {
                    performAjaxAction({ action: 'delete_user', user_id: userId });
                }
                break;
            case 'activate':
                if (await Dialog.confirm('فعال‌سازی کاربر', `آیا از فعال کردن کاربر "${username}" اطمینان دارید؟`)) {
                    performAjaxAction({ action: 'activate_user', user_id: userId });
                }
                break;
            case 'suspend': showSuspendModal(userId, username); break;
            case 'reset_password': showResetPasswordModal(userId, username); break;
            case 'view': showUserDetailsModal(userId); break;
        }
    });

    // توابع نمایش مدال‌ها (بدون تغییر)
    function showSuspendModal(userId, username) {
        modalTitle.textContent = `غیرفعال کردن کاربر: ${username}`;
        modalBody.innerHTML = `
            <form id="modalForm">
                <input type="hidden" name="action" value="suspend_user">
                <input type="hidden" name="user_id" value="${userId}">
                <div class="form-group"><label>دلیل غیرفعال سازی:</label><textarea name="reason" class="form-control" required></textarea></div>
                <button type="submit" class="submit-btn">تایید غیرفعال سازی</button>
            </form>`;
        modal.style.display = 'block';
        setupModalForm();
    }

    function showResetPasswordModal(userId, username) {
        modalTitle.textContent = `ریست پسورد برای: ${username}`;
        modalBody.innerHTML = `
            <form id="modalForm">
                <input type="hidden" name="action" value="reset_password">
                <input type="hidden" name="user_id" value="${userId}">
                <div class="form-group"><label>رمز عبور جدید:</label><input type="password" name="new_password" class="form-control" required></div>
                <div class="form-group"><label>تکرار رمز عبور:</label><input type="password" name="confirm_password" class="form-control" required></div>
                <button type="submit" class="submit-btn">ذخیره پسورد جدید</button>
            </form>`;
        modal.style.display = 'block';
        setupModalForm();
    }

    async function showUserDetailsModal(userId) {
        modalTitle.textContent = 'در حال بارگذاری جزئیات...';
        modalBody.innerHTML = '<div style="text-align:center; padding: 20px;"><i class="fas fa-spinner fa-spin fa-2x"></i></div>';
        modal.style.display = 'block';
        const result = await performAjaxAction({ action: 'get_user_details', user_id: userId }, null, false);
        if (result && result.success) {
            const user = result.user;
            modalTitle.textContent = `جزئیات کاربر: ${user.username}`;
            modalBody.innerHTML = `
                <div class="user-details">
                    <div class="detail-row"><span class="detail-label">ID:</span><span class="detail-value">${user.id}</span></div>
                    <div class="detail-row"><span class="detail-label">نام کاربری:</span><span class="detail-value">${user.username}</span></div>
                    <div class="detail-row"><span class="detail-label">نام کامل:</span><span class="detail-value">${user.fullname || '-'}</span></div>
                    <div class="detail-row"><span class="detail-label">ایمیل:</span><span class="detail-value">${user.email || '-'}</span></div>
                    <div class="detail-row"><span class="detail-label">تلفن:</span><span class="detail-value">${user.phone || '-'}</span></div>
                    <div class="detail-row"><span class="detail-label">تاریخ ساخت:</span><span class="detail-value">${formatPersianDate(user.created_at)}</span></div>
                    <div class="detail-row"><span class="detail-label">نحوه ایجاد:</span><span class="detail-value">${user.created_by === 'system' ? 'سیستمی' : 'دستی'}</span></div>
                    <div class="detail-row"><span class="detail-label">وضعیت:</span><span class="detail-value"><span class="status-badge ${user.status}">${user.status === 'active' ? 'فعال' : 'غیرفعال'}</span></span></div>
                    ${user.status === 'suspended' ? `<div class="detail-row"><span class="detail-label">دلیل غیرفعال سازی:</span><span class="detail-value">${user.suspended_reason || 'نامشخص'}</span></div>` : ''}
                </div>`;
        } else {
            modalBody.innerHTML = 'خطا در دریافت اطلاعات.';
        }
    }
    
    function setupModalForm() {
        const form = document.getElementById('modalForm');
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(form);
            const data = Object.fromEntries(formData.entries());
            performAjaxAction(data);
            modal.style.display = 'none';
        });
    }

    // تابع اصلی برای تمام درخواست‌های AJAX

    // این دو تابع را از فایل قبلی کپی می‌کنیم
    async function performAjaxAction(data, button = null, showSuccessNotification = true) {
        try {
            const response = await fetch('users_management.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams(data).toString()
            });
            if (!response.ok) throw new Error('خطا در پاسخ سرور');
            const result = await response.json();

            if (result.success) {
                if (showSuccessNotification) showNotification(result.message, 'success');
                if (data.action !== 'get_user_details') location.reload();
                return result;
            } else {
                showNotification(result.message || 'خطایی رخ داد', 'error');
                return null;
            }
        } catch (error) {
            console.error('AJAX Error:', error);
            showNotification('خطا در ارتباط با سرور', 'error');
            return null;
        }
    }
    
    function showNotification(message, type = 'success', duration = 4000) {
        const container = document.querySelector('.notification-container.top-right');
        if (!container) return;
        const notification = document.createElement('div');
        notification.className = `notification ${type}`;
        notification.innerHTML = `<span>${message}</span>`;
        container.appendChild(notification);
        setTimeout(() => {
            notification.style.opacity = '0';
            setTimeout(() => notification.remove(), 300);
        }, duration);
    }
});
</script>
</body>
</html>