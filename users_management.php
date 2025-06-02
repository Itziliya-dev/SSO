<?php
require_once __DIR__.'/includes/config.php';
require_once __DIR__.'/includes/auth_functions.php';

session_start();

// --- بخش API: پردازش درخواست‌های AJAX ---
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
        // این بخش درخواست مشاهده جزئیات را مدیریت می‌کند
        case 'get_user_details':
            if ($userId > 0) {
                // کوئری از جدول users
                $stmt = $conn->prepare("SELECT id, username, fullname, email, phone, status, created_at, created_by, suspended_reason FROM users WHERE id = ?");
                $stmt->bind_param("i", $userId);
                $stmt->execute();
                $user = $stmt->get_result()->fetch_assoc();
                if ($user) {
                    echo json_encode(['success' => true, 'user' => $user]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'کاربر یافت نشد']);
                }
            }
            break;
ak;

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
                $confirmPassword = $_POST['confirm_password'];

                if (empty($newPassword) || $newPassword !== $confirmPassword) {
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
// (این بخش بدون تغییر باقی می‌ماند)
if (!isset($_SESSION['is_owner']) || !$_SESSION['is_owner']) {
    header('Location: login.php');
    exit();
}
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? 'all';
$createdBy = $_GET['created_by'] ?? 'all';
$whereConditions = [];
$params = [];
$types = '';
if (!empty($search)) {
    $whereConditions[] = "(username LIKE ? OR email LIKE ? OR phone LIKE ?)";
    $params = array_merge($params, ["%$search%", "%$search%", "%$search%"]);
    $types .= 'sss';
}
if ($status !== 'all') {
    $whereConditions[] = "status = ?";
    $params[] = $status;
    $types .= 's';
}
if ($createdBy !== 'all') {
    $whereConditions[] = "created_by = ?";
    $params[] = $createdBy;
    $types .= 's';
}
$whereClause = $whereConditions ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
$conn = getDbConnection();
$query = "SELECT id, username, email, phone, status, created_at, created_by, is_owner, has_user_panel FROM users $whereClause ORDER BY created_at DESC";
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$users = $result->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مدیریت کاربران | پنل مدیریت SSO</title>
    <link rel="stylesheet" href="assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;600&display=swap" rel="stylesheet">

</head>
<body>
    <div class="admin-container">
        <div class="admin-header">
            <h1 class="admin-title"><i class="fas fa-users-cog"></i> مدیریت کاربران سیستم</h1>
            <a href="admin_panel.php" class="btn-panel"><i class="fas fa-arrow-left"></i> بازگشت به پنل مدیریت</a>
        </div>
        
        <div class="admin-content">
            <div class="admin-card user-list-card">
                <div class="table-container">
                    <table class="user-table">
                        <thead>
                            <tr>
                                <th>ردیف</th>
                                <th>نام کاربری</th>
                                <th>ایمیل</th>
                                <th>وضعیت</th>
                                <th>دسترسی‌ها</th>
                                <th>عملیات</th>
                            </tr>
                        </thead>
                        <tbody id="users-table-body">
                            <?php $row_counter = 1; ?>
                            <?php foreach ($users as $user): ?>
                            <tr id="user-row-<?= $user['id'] ?>">
                                <td><?= $row_counter++ ?></td>
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
        </div>
    </div>

    <div id="mainModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle"></h3>
                <span class="close-modal" id="modalClose">&times;</span>
            </div>
            <div class="modal-body" id="modalBody">
                </div>
        </div>
    </div>
    
    <div class="notification-container top-right"></div>

<script>

    // این تابع را می‌توانید در بالای بخش <script> خود قرار دهید
function formatPersianDate(dateString) {
    if (!dateString) return '-';

    const date = new Date(dateString);
    const options = {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    };
    
    return new Intl.DateTimeFormat('fa-IR', options).format(date);
}
document.addEventListener('DOMContentLoaded', function() {
    const tableBody = document.getElementById('users-table-body');
    const modal = document.getElementById('mainModal');
    const modalTitle = document.getElementById('modalTitle');
    const modalBody = document.getElementById('modalBody');
    const modalClose = document.getElementById('modalClose');

    // بستن مدال
    modalClose.onclick = () => modal.style.display = "none";
    window.onclick = (event) => {
        if (event.target == modal) {
            modal.style.display = "none";
        }
    };

    // مدیریت کلیک روی دکمه‌های عملیات
    tableBody.addEventListener('click', function(e) {
        const button = e.target.closest('.action-btn');
        if (!button) return;

        const action = button.dataset.action;
        const userId = button.dataset.userId;
        const username = button.closest('tr').querySelector('.username').textContent;

        switch (action) {
            case 'delete':
                if (confirm(`آیا از حذف کاربر "${username}" اطمینان دارید؟`)) {
                    performAjaxAction({ action: 'delete_user', user_id: userId });
                }
                break;
            case 'activate':
                if (confirm(`آیا از فعال کردن کاربر "${username}" اطمینان دارید؟`)) {
                    performAjaxAction({ action: 'activate_user', user_id: userId });
                }
                break;
            case 'suspend':
                showSuspendModal(userId, username);
                break;
            case 'reset_password':
                showResetPasswordModal(userId, username);
                break;
            case 'view':
                showUserDetailsModal(userId);
                break;
        }
    });

    // توابع نمایش مدال‌ها
    function showSuspendModal(userId, username) {
        modalTitle.textContent = `غیرفعال کردن کاربر: ${username}`;
        modalBody.innerHTML = `
            <form id="modalForm">
                <input type="hidden" name="action" value="suspend_user">
                <input type="hidden" name="user_id" value="${userId}">
                <div class="form-group">
                    <label>دلیل غیرفعال سازی:</label>
                    <textarea name="reason" class="form-control" required></textarea>
                </div>
                <button type="submit" class="submit-btn">تایید غیرفعال سازی</button>
            </form>
        `;
        modal.style.display = 'block';
        setupModalForm();
    }

    function showResetPasswordModal(userId, username) {
        modalTitle.textContent = `ریست پسورد برای: ${username}`;
        modalBody.innerHTML = `
            <form id="modalForm">
                <input type="hidden" name="action" value="reset_password">
                <input type="hidden" name="user_id" value="${userId}">
                <div class="form-group">
                    <label>رمز عبور جدید:</label>
                    <input type="password" name="new_password" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>تکرار رمز عبور:</label>
                    <input type="password" name="confirm_password" class="form-control" required>
                </div>
                <button type="submit" class="submit-btn">ذخیره پسورد جدید</button>
            </form>
        `;
        modal.style.display = 'block';
        setupModalForm();
    }

    async function showUserDetailsModal(userId) {
        modalTitle.textContent = 'در حال بارگذاری جزئیات...';
        modalBody.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        modal.style.display = 'block';

        const result = await performAjaxAction({ action: 'get_user_details', user_id: userId }, null, false);
        
        if(result && result.success){
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
                    <div class="detail-row">
                        <span class="detail-label">نحوه ایجاد:</span>
                        <span class="detail-value">${user.created_by === 'system' ? 'سیستمی' : 'دستی'}</span>
                    </div>                    
                    <div class="detail-row"><span class="detail-label">وضعیت:</span><span class="detail-value"><span class="status-badge ${user.status}">${user.status === 'active' ? 'فعال' : 'غیرفعال'}</span></span></div>
                     ${user.status === 'suspended' ? `<div class="detail-row"><span class="detail-label">دلیل غیرفعال سازی:</span><span class="detail-value">${user.suspended_reason || 'نامشخص'}</span></div>` : ''}
                </div>
            `;
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
    async function performAjaxAction(data, button = null, showSuccessNotification = true) {
        let originalIcon;
        if (button) {
            originalIcon = button.innerHTML;
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            button.disabled = true;
        }

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
                // به‌روزرسانی UI در صورت موفقیت
                if (data.action !== 'get_user_details') location.reload(); // ساده‌ترین راه برای به‌روزرسانی جدول
                return result; // برای استفاده در مدال جزئیات
            } else {
                showNotification(result.message || 'خطایی رخ داد', 'error');
                return null;
            }
        } catch (error) {
            console.error('AJAX Error:', error);
            showNotification('خطا در ارتباط با سرور', 'error');
            return null;
        } finally {
            if (button) {
                button.innerHTML = originalIcon;
                button.disabled = false;
            }
        }
    }
    
    function showNotification(message, type = 'success') {
        const container = document.querySelector('.notification-container.top-right');
        const notification = document.createElement('div');
        notification.className = `notification ${type}`;
        notification.innerHTML = `<span>${message}</span>`;
        container.appendChild(notification);
        setTimeout(() => {
            notification.style.opacity = '0';
            setTimeout(() => notification.remove(), 300);
        }, 4000);
    }
});
</script>
</body>
</html>