<?php
require_once __DIR__.'/includes/config.php';
require_once __DIR__.'/includes/auth_functions.php';

session_start();

if (!isset($_SESSION['is_owner']) || !$_SESSION['is_owner']) {
    header('Location: login.php');
    exit();
}

// پردازش جستجو و فیلترها
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? 'all';
$createdBy = $_GET['created_by'] ?? 'all';

// ساخت شرط‌های WHERE
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

// دریافت لیست کاربران
$conn = getDbConnection();
$query = "SELECT id, username, email, phone, status, created_at, created_by FROM users $whereClause ORDER BY created_at DESC";
$stmt = $conn->prepare($query);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();
$users = $result->fetch_all(MYSQLI_ASSOC);

// پردازش عملیات مدیریت کاربران
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['suspend_user'])) {
        $userId = (int)$_POST['user_id'];
        $reason = $_POST['reason'];
        
        $stmt = $conn->prepare("UPDATE users SET status = 'suspended', suspended_reason = ? WHERE id = ?");
        $stmt->bind_param("si", $reason, $userId);
        $stmt->execute();
        
        $_SESSION['message'] = ['type' => 'success', 'text' => 'کاربر با موفقیت غیرفعال شد'];
        header("Location: users_management.php");
        exit();
    }
    
    if (isset($_POST['activate_user'])) {
        $userId = (int)$_POST['user_id'];
        
        $stmt = $conn->prepare("UPDATE users SET status = 'active', suspended_reason = NULL WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        
        $_SESSION['message'] = ['type' => 'success', 'text' => 'کاربر با موفقیت فعال شد'];
        header("Location: users_management.php");
        exit();
    }
    
    if (isset($_POST['delete_user'])) {
        $userId = (int)$_POST['user_id'];
        
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        
        $_SESSION['message'] = ['type' => 'success', 'text' => 'کاربر با موفقیت حذف شد'];
        header("Location: users_management.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مدیریت کاربران | پنل مدیریت SSO</title>
    <link rel="stylesheet" href="assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="admin-container">
        <div class="admin-header">
            <h1 class="admin-title">
                <i class="fas fa-users-cog"></i>
                مدیریت کاربران سیستم
            </h1>
            <a href="admin_panel.php" class="btn-panel">
                <i class="fas fa-arrow-left"></i> بازگشت به پنل مدیریت
            </a>
        </div>
        
        <?php if (isset($_SESSION['message'])): ?>
            <div class="notification <?= $_SESSION['message']['type'] ?>">
                <?= $_SESSION['message']['text'] ?>
            </div>
            <?php unset($_SESSION['message']); ?>
        <?php endif; ?>
        
        <div class="admin-content">
            <!-- فیلترها و جستجو -->
            <div class="admin-card filter-card">
                <form method="GET" class="filter-form">
                    <div class="form-row">
                        <div class="form-group">
                            <label>جستجو:</label>
                            <input type="text" name="search" placeholder="نام کاربری، ایمیل یا تلفن" 
                                   value="<?= htmlspecialchars($search) ?>" class="form-control">
                        </div>
                        
                        <div class="form-group">
                            <label>وضعیت:</label>
                            <select name="status" class="form-control">
                                <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>همه</option>
                                <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>فعال</option>
                                <option value="suspended" <?= $status === 'suspended' ? 'selected' : '' ?>>غیرفعال</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>نوع ایجاد:</label>
                            <select name="created_by" class="form-control">
                                <option value="all" <?= $createdBy === 'all' ? 'selected' : '' ?>>همه</option>
                                <option value="system" <?= $createdBy === 'system' ? 'selected' : '' ?>>سیستمی</option>
                                <option value="manual" <?= $createdBy === 'manual' ? 'selected' : '' ?>>دستی</option>
                            </select>
                        </div>
                        
                        <button type="submit" class="filter-btn">
                            <i class="fas fa-filter"></i> اعمال فیلتر
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- لیست کاربران -->
            <div class="admin-card user-list-card">
                <div class="table-container">
                    <table class="user-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>نام کاربری</th>
                                <th>ایمیل</th>
                                <th>تلفن</th>
                                <th>وضعیت</th>
                                <th>نوع ایجاد</th>
                                <th>تاریخ ایجاد</th>
                                <th>عملیات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                            <tr class="<?= $user['status'] === 'suspended' ? 'suspended' : '' ?>">
                                <td><?= $user['id'] ?></td>
                                <td><?= htmlspecialchars($user['username']) ?></td>
                                <td><?= htmlspecialchars($user['email'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($user['phone'] ?? '-') ?></td>
                                <td>
                                    <span class="status-badge <?= $user['status'] ?>">
                                        <?= $user['status'] === 'active' ? 'فعال' : 'غیرفعال' ?>
                                    </span>
                                </td>
                                <td><?= $user['created_by'] === 'system' ? 'سیستمی' : 'دستی' ?></td>
                                <td><?= date('Y/m/d H:i', strtotime($user['created_at'])) ?></td>
                                <td class="actions">
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                        <button type="button" class="action-btn view-details" 
                onclick="showUserDetails(<?= $user['id'] ?>)">
            <i class="fas fa-eye"></i> مشاهده
        </button>      
                                        
                                        <?php if ($user['status'] === 'active'): ?>
                                            <button type="button" class="action-btn suspend-btn" 
                                                    onclick="showSuspendModal(<?= $user['id'] ?>, '<?= htmlspecialchars($user['username']) ?>')">
                                                <i class="fas fa-user-slash"></i> غیرفعال
                                            </button>
                                        <?php else: ?>
                                            <button type="submit" name="activate_user" class="action-btn activate-btn">
                                                <i class="fas fa-user-check"></i> فعال
                                            </button>
                                        <?php endif; ?>
                                        
                                        <button type="button" class="action-btn reset-btn" 
                                                onclick="showResetModal(<?= $user['id'] ?>, '<?= htmlspecialchars($user['username']) ?>')">
                                            <i class="fas fa-key"></i> ریست پسورد
                                        </button>
                                        
                                        <button type="submit" name="delete_user" class="action-btn delete-btn" 
                                                onclick="return confirm('آیا از حذف این کاربر اطمینان دارید؟')">
                                            <i class="fas fa-trash"></i> حذف
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- مدال غیرفعال کردن کاربر -->
    <div id="suspendModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>غیرفعال کردن کاربر <span id="suspendUsername"></span></h3>
                <span class="close-modal" onclick="closeModal('suspendModal')">&times;</span>
            </div>
            <div class="modal-body">
                <form method="POST" id="suspendForm">
                    <input type="hidden" name="user_id" id="suspendUserId">
                    <input type="hidden" name="suspend_user" value="1">
                    <div class="form-group">
                        <label>دلیل غیرفعال سازی:</label>
                        <textarea name="reason" class="form-control" required></textarea>
                    </div>
                    <button type="submit" class="submit-btn">
                        <i class="fas fa-user-slash"></i> تایید غیرفعال سازی
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- مدال ریست پسورد -->
    <div id="resetModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>ریست پسورد برای <span id="resetUsername"></span></h3>
                <span class="close-modal" onclick="closeModal('resetModal')">&times;</span>
            </div>
            <div class="modal-body">
                <form method="POST" action="reset_password.php" id="resetForm">
                    <input type="hidden" name="user_id" id="resetUserId">
                    <div class="form-group">
                        <label>رمز عبور جدید:</label>
                        <input type="password" name="new_password" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>تکرار رمز عبور:</label>
                        <input type="password" name="confirm_password" class="form-control" required>
                    </div>
                    <button type="submit" class="submit-btn">
                        <i class="fas fa-save"></i> ذخیره پسورد جدید
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- مدال جزئیات کاربر -->
<div id="userDetailsModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>جزئیات کاربر</h3>
            <span class="close-modal">&times;</span>
        </div>
        <div class="modal-body">
            <div class="user-details">
                <!-- محتوای این بخش با JS پر خواهد شد -->
            </div>
        </div>
    </div>
</div>

<!-- کانتینر نوتیفیکیشن -->
<div class="notification-container top-right"></div>
<div class="notification-container bottom-right"></div>

    <script>
/**
 * مدیریت کاربران - اسکریپت‌های صفحه
 * نسخه کامل و بهینه‌شده
 */

// تابع نمایش نوتیفیکیشن
function showNotification(message, type = 'success', position = 'top-right', duration = 5000) {
    const container = document.querySelector(`.notification-container.${position}`) || createNotificationContainer(position);
    
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    
    let icon;
    switch(type) {
        case 'success':
            icon = '<i class="fas fa-check-circle"></i>';
            break;
        case 'error':
            icon = '<i class="fas fa-exclamation-circle"></i>';
            break;
        case 'warning':
            icon = '<i class="fas fa-exclamation-triangle"></i>';
            break;
        default:
            icon = '<i class="fas fa-info-circle"></i>';
    }
    
    notification.innerHTML = `
        ${icon}
        <span>${message}</span>
        <span class="notification-close">&times;</span>
    `;
    
    const closeBtn = notification.querySelector('.notification-close');
    closeBtn.addEventListener('click', () => {
        notification.remove();
    });
    
    container.appendChild(notification);
    
    if (duration > 0) {
        setTimeout(() => {
            notification.remove();
        }, duration);
    }
    
    return notification;
}

function createNotificationContainer(position) {
    const container = document.createElement('div');
    container.className = `notification-container ${position}`;
    document.body.appendChild(container);
    return container;
}

// تابع تغییر وضعیت دکمه
function toggleButton(button, isLoading) {
    if (isLoading) {
        button.disabled = true;
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> در حال پردازش...';
    } else {
        button.disabled = false;
        button.innerHTML = button.dataset.originalText || 'ذخیره';
    }
}

// مدیریت مدال‌ها
function setupModals() {
    document.querySelectorAll('.modal').forEach(modal => {
        const closeBtn = modal.querySelector('.close-modal');
        if (closeBtn) {
            closeBtn.addEventListener('click', () => {
                modal.style.display = 'none';
                document.body.style.overflow = 'auto';
            });
        }
    });

    window.addEventListener('click', (event) => {
        if (event.target.classList.contains('modal')) {
            event.target.style.display = 'none';
            document.body.style.overflow = 'auto';
        }
    });
}

// نمایش مدال غیرفعال کردن کاربر
function showSuspendModal(userId, username) {
    document.getElementById('suspendUserId').value = userId;
    document.getElementById('suspendUsername').textContent = username;
    document.getElementById('suspendModal').style.display = 'block';
    document.body.style.overflow = 'hidden';
}

// نمایش مدال ریست پسورد
function showResetModal(userId, username) {
    document.getElementById('resetUserId').value = userId;
    document.getElementById('resetUsername').textContent = username;
    document.getElementById('resetModal').style.display = 'block';
    document.body.style.overflow = 'hidden';
}

// نمایش جزئیات کاربر
function showUserDetails(userId) {
    fetch(`get_user_details.php?id=${userId}`)
        .then(response => {
            if (!response.ok) {
                throw new Error('پاسخ سرور نامعتبر است');
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                const modal = document.getElementById('userDetailsModal');
                const detailsContainer = modal.querySelector('.user-details');
                
                detailsContainer.innerHTML = `
                    <div class="detail-row">
                        <span class="detail-label">ID:</span>
                        <span class="detail-value">${data.user.id}</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">نام کاربری:</span>
                        <span class="detail-value">${data.user.username}</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">ایمیل:</span>
                        <span class="detail-value">${data.user.email || '-'}</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">تلفن:</span>
                        <span class="detail-value">${data.user.phone || '-'}</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">وضعیت:</span>
                        <span class="detail-value">
                            <span class="status-badge ${data.user.status}">
                                ${data.user.status === 'active' ? 'فعال' : 'غیرفعال'}
                            </span>
                        </span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">نوع ایجاد:</span>
                        <span class="detail-value">
                            ${data.user.created_by === 'system' ? 'سیستمی' : 'دستی'}
                        </span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">تاریخ ایجاد:</span>
                        <span class="detail-value">
                            ${new Date(data.user.created_at).toLocaleString('fa-IR')}
                        </span>
                    </div>
                    ${data.user.status === 'suspended' ? `
                    <div class="detail-row">
                        <span class="detail-label">دلیل غیرفعال سازی:</span>
                        <span class="detail-value">${data.user.suspended_reason || 'نامشخص'}</span>
                    </div>
                    ` : ''}
                `;
                
                modal.style.display = 'block';
                document.body.style.overflow = 'hidden';
            } else {
                showNotification(data.message || 'خطا در دریافت اطلاعات کاربر', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('خطا در ارتباط با سرور: ' + error.message, 'error');
        });
}

// مدیریت فرم‌ها
function setupForms() {
    // فرم غیرفعال کردن کاربر
    const suspendForm = document.getElementById('suspendForm');
    if (suspendForm) {
        suspendForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.dataset.originalText = submitBtn.innerHTML;
            toggleButton(submitBtn, true);
            
            fetch('suspend_user.php', {
                method: 'POST',
                body: new FormData(this)
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('پاسخ سرور نامعتبر است');
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    showNotification('کاربر با موفقیت غیرفعال شد', 'success');
                    document.getElementById('suspendModal').style.display = 'none';
                    setTimeout(() => {
                        location.reload();
                    }, 1500);
                } else {
                    showNotification(data.message || 'خطا در غیرفعال کردن کاربر', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('خطا در ارتباط با سرور', 'error');
            })
            .finally(() => {
                toggleButton(submitBtn, false);
            });
        });
    }

    // فرم ریست پسورد
    const resetForm = document.getElementById('resetForm');
    if (resetForm) {
        resetForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const newPassword = this.querySelector('input[name="new_password"]').value;
            const confirmPassword = this.querySelector('input[name="confirm_password"]').value;
            
            if (newPassword !== confirmPassword) {
                showNotification('رمزهای عبور وارد شده مطابقت ندارند!', 'error');
                return;
            }
            
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.dataset.originalText = submitBtn.innerHTML;
            toggleButton(submitBtn, true);
            
            fetch('reset_password.php', {
                method: 'POST',
                body: new FormData(this)
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('پاسخ سرور نامعتبر است');
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    showNotification('رمز عبور با موفقیت تغییر یافت', 'success');
                    document.getElementById('resetModal').style.display = 'none';
                    this.reset();
                } else {
                    showNotification(data.message || 'خطا در تغییر رمز عبور', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('خطا در ارتباط با سرور', 'error');
            })
            .finally(() => {
                toggleButton(submitBtn, false);
            });
        });
    }
}

// مدیریت حذف کاربر
function setupDeleteButtons() {
    document.querySelectorAll('.delete-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            if (!confirm('آیا از حذف این کاربر اطمینان دارید؟')) {
                e.preventDefault();
                return;
            }
            
            const form = this.closest('form');
            const row = this.closest('tr');
            
            row.style.opacity = '0.5';
            row.style.transition = 'opacity 0.3s ease';
            
            fetch('delete_user.php', {
                method: 'POST',
                body: new FormData(form)
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('پاسخ سرور نامعتبر است');
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    showNotification('کاربر با موفقیت حذف شد', 'success');
                    row.remove();
                } else {
                    showNotification(data.message || 'خطا در حذف کاربر', 'error');
                    row.style.opacity = '1';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('خطا در ارتباط با سرور', 'error');
                row.style.opacity = '1';
            });
        });
    });
}

// اجرای توابع تنظیمات پس از بارگذاری DOM
document.addEventListener('DOMContentLoaded', function() {
    setupModals();
    setupForms();
    setupDeleteButtons();
    
    // ایجاد کانتینرهای نوتیفیکیشن
    createNotificationContainer('top-right');
    createNotificationContainer('bottom-right');
});

// تابع بستن مدال
function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
    document.body.style.overflow = 'auto';
}

// بستن مدال با کلیک خارج از آن
window.addEventListener('click', function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.style.display = 'none';
        document.body.style.overflow = 'auto';
    }
});
    </script>
</body>
</html>