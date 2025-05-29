<?php
require_once __DIR__.'/includes/config.php';
require_once __DIR__.'/includes/auth_functions.php';

session_start();

// بررسی دسترسی ادمین
if (!isset($_SESSION['is_owner']) || !$_SESSION['is_owner']) {
    header('Location: login.php');
    exit();
}

// اتصال به دیتابیس
$conn = getDbConnection();

// پردازش عملیات حذف اگر درخواست شده باشد
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_staff'])) {
    $staff_id = $_POST['staff_id'];
    $reason = $_POST['reason'] ?? '';
    
    try {
        $stmt = $conn->prepare("DELETE FROM `staff-manage` WHERE id = ?");
        $stmt->bind_param("i", $staff_id);
        $stmt->execute();
        
        $_SESSION['success_message'] = "استف با موفقیت حذف شد";
        header("Location: staff_management.php");
        exit();
    } catch (Exception $e) {
        $_SESSION['error_message'] = "خطا در حذف استف: " . $e->getMessage();
    }
}

// دریافت لیست استف‌ها از دیتابیس
$staff_query = "SELECT * FROM `staff-manage` ORDER BY created_at DESC";
$staff_result = $conn->query($staff_query);

// دریافت پیام‌های موفقیت/خطا از session
$success_message = $_SESSION['success_message'] ?? '';
$error_message = $_SESSION['error_message'] ?? '';
unset($_SESSION['success_message'], $_SESSION['error_message']);
?>

<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مدیریت استف‌ها | پنل مدیریت</title>
    
    <!-- استایل‌ها -->
    <link rel="stylesheet" href="assets/css/staff_management.css">
    <link rel="stylesheet" href="assets/css/admin.css">
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- فونت فارسی -->
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        /* استایل‌های اضافی برای زیبایی بیشتر */
        .staff-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            background-color: rgba(124, 77, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary-color);
            font-weight: bold;
        }
        
        .action-buttons {
            display: flex;
            gap: 8px;
            justify-content: center;
        }
        
        .no-staff {
            text-align: center;
            padding: 40px;
            color: var(--text-muted);
            font-size: 18px;
        }
    </style>
</head>
<body>

<?php include __DIR__ . '/includes/header.php'; ?>

<div class="staff-management-container">
    <div class="staff-management-header">
        <h1 class="staff-management-title">
            <i class="fas fa-users-gear"></i>
            مدیریت استف‌ها
        </h1>
        
        <div class="header-actions">
            <a href="admin_panel.php" class="staff-management-btn">
                <i class="fas fa-arrow-left"></i>
                بازگشت به پنل
            </a>
        </div>
    </div>

    <!-- نمایش پیام‌های موفقیت/خطا -->
    <?php if ($success_message): ?>
    <div class="success-message">
        <i class="fas fa-check-circle"></i>
        <?= htmlspecialchars($success_message) ?>
    </div>
    <?php endif; ?>
    
    <?php if ($error_message): ?>
    <div class="error-message">
        <i class="fas fa-exclamation-circle"></i>
        <?= htmlspecialchars($error_message) ?>
    </div>
    <?php endif; ?>

    <div class="staff-management-card">
        <div class="card-header">
            <h2>
                <i class="fas fa-list-check"></i>
                لیست استف‌ها
            </h2>
            
            <div class="staff-search-box">
                <input type="text" id="staffSearch" placeholder="جستجوی استف..." class="staff-search-input">
                <i class="fas fa-search staff-search-icon"></i>
            </div>
        </div>

        <div class="staff-table-container">
            <table class="staff-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>استف</th>
                        <th>نام کاربری</th>
                        <th>اطلاعات تماس</th>
                        <th>تاریخ عضویت</th>
                        <th>وضعیت</th>
                        <th>عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($staff_result->num_rows > 0): ?>
                        <?php while ($staff = $staff_result->fetch_assoc()): ?>
                        <tr data-id="<?= $staff['id'] ?>">
                            <td><?= htmlspecialchars($staff['id']) ?></td>
                            <td>
                                <div class="staff-info">
                                    <div class="staff-avatar">
                                        <?= mb_substr($staff['fullname'], 0, 1) ?>
                                    </div>
                                    <div class="staff-name">
                                        <?= htmlspecialchars($staff['fullname']) ?>
                                        <small><?= htmlspecialchars($staff['username']) ?></small>
                                    </div>
                                </div>
                            </td>
                            <td><?= htmlspecialchars($staff['username']) ?></td>
                            <td>
                                <?php if ($staff['email']): ?>
                                    <div><i class="fas fa-envelope"></i> <?= htmlspecialchars($staff['email']) ?></div>
                                <?php endif; ?>
                                <?php if ($staff['phone']): ?>
                                    <div><i class="fas fa-phone"></i> <?= htmlspecialchars($staff['phone']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td><?= date('Y/m/d H:i', strtotime($staff['created_at'])) ?></td>
                            <td>
                                <span class="staff-status <?= $staff['is_active'] ? 'active' : 'inactive' ?>">
                                    <?= $staff['is_active'] ? 'فعال' : 'غیرفعال' ?>
                                </span>
                                <span class="staff-status <?= ($staff['is_verify'] ?? 0) ? 'verified' : 'not-verified' ?>">
                                    <?= ($staff['is_verify'] ?? 0) ? 'تایید شده' : 'تایید نشده' ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <button class="staff-action-btn view" data-id="<?= $staff['id'] ?>" title="مشاهده جزئیات">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="staff-action-btn edit" data-id="<?= $staff['id'] ?>" title="ویرایش">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="staff-action-btn reset" data-id="<?= $staff['id'] ?>" title="ریست پسورد">
                                        <i class="fas fa-key"></i>
                                    </button>
                                    <button
                                        class="staff-action-btn verify <?= ($staff['is_verify'] ?? 0) ? 'disabled' : '' ?>"
                                        data-id="<?= $staff['id'] ?>"
                                        title="تایید هویت استف"
                                        <?= ($staff['is_verify'] ?? 0) ? 'disabled' : '' ?> >
                                        <i class="fas fa-user-check"></i>
                                    </button>
                                    <?php if ($staff['is_active']): ?>
                                        <button class="staff-action-btn suspend" data-id="<?= $staff['id'] ?>" title="غیرفعال کردن">
                                            <i class="fas fa-user-slash"></i>
                                        </button>
                                    <?php else: ?>
                                        <button class="staff-action-btn activate" data-id="<?= $staff['id'] ?>" title="فعال کردن">
                                            <i class="fas fa-user-check"></i>
                                        </button>
                                    <?php endif; ?>
                                    <button class="staff-action-btn delete" data-id="<?= $staff['id'] ?>" title="حذف">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7">
                                <div class="no-staff">
                                    <i class="fas fa-user-clock fa-2x"></i>
                                    <p>هیچ استفی یافت نشد</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- مدال مشاهده جزئیات استف -->
<!-- مدال مشاهده جزئیات استف -->
<div id="staffDetailsModal" class="staff-modal">
    <div class="staff-modal-content">
        <div class="staff-modal-header">
            <h3>جزئیات استف</h3>
            <span class="staff-modal-close">&times;</span>
        </div>
        <div class="staff-modal-body">
            <div class="staff-details-container">
                <div class="staff-detail-row">
                    <span class="staff-detail-label">نام کامل:</span>
                    <span class="staff-detail-value" id="detail-fullname"></span>
                </div>
                <div class="staff-detail-row">
                    <span class="staff-detail-label">نام کاربری:</span>
                    <span class="staff-detail-value" id="detail-username"></span>
                </div>
                <div class="staff-detail-row">
                    <span class="staff-detail-label">ایمیل:</span>
                    <span class="staff-detail-value" id="detail-email"></span>
                </div>
                <div class="staff-detail-row">
                    <span class="staff-detail-label">تلفن:</span>
                    <span class="staff-detail-value" id="detail-phone"></span>
                </div>
                <div class="staff-detail-row">
                    <span class="staff-detail-label">سن:</span>
                    <span class="staff-detail-value" id="detail-age"></span>
                </div>
                <div class="staff-detail-row">
                    <span class="staff-detail-label">دیسکورد:</span>
                    <span class="staff-detail-value" id="detail-discord"></span>
                </div>
                <div class="staff-detail-row">
                    <span class="staff-detail-label">استیم:</span>
                    <span class="staff-detail-value" id="detail-steam"></span>
                </div>
                <div class="staff-detail-row">
                    <span class="staff-detail-label">تاریخ عضویت:</span>
                    <span class="staff-detail-value" id="detail-created-at"></span>
                </div>
                <div class="staff-detail-row">
                    <span class="staff-detail-label">وضعیت:</span>
                    <span class="staff-detail-value" id="detail-status"></span>
                </div>
                <!-- اطلاعات اضافی در صورت نیاز -->

                <div class="staff-detail-row">
                    <span class="staff-detail-label">دسترسی‌ها:</span>
                    <span class="staff-detail-value" id="detail-permissions"></span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- مدال ویرایش استف -->
<div id="editStaffModal" class="staff-modal">
    <div class="staff-modal-content">
        <div class="staff-modal-header">
            <h3>ویرایش اطلاعات استف</h3>
            <span class="staff-modal-close">&times;</span>
        </div>
        <div class="staff-modal-body">
            <form id="editStaffForm" method="POST" action="update_staff.php">
                <input type="hidden" name="staff_id" id="edit-staff-id">
                
                <div class="staff-form-group">
                    <label for="edit-fullname">نام کامل</label>
                    <input type="text" id="edit-fullname" name="fullname" class="staff-form-control" required>
                </div>
                
                <div class="staff-form-group">
                    <label for="edit-username">نام کاربری</label>
                    <input type="text" id="edit-username" name="username" class="staff-form-control" required>
                </div>
                
                <div class="staff-form-group">
                    <label for="edit-email">ایمیل</label>
                    <input type="email" id="edit-email" name="email" class="staff-form-control">
                </div>
                
                <div class="staff-form-group">
                    <label for="edit-phone">تلفن</label>
                    <input type="tel" id="edit-phone" name="phone" class="staff-form-control">
                </div>
                
                <div class="staff-form-group">
                    <label for="edit-discord">آیدی دیسکورد</label>
                    <input type="text" id="edit-discord" name="discord_id" class="staff-form-control">
                </div>
                
                <div class="staff-form-group">
                    <label for="edit-steam">آیدی استیم</label>
                    <input type="text" id="edit-steam" name="steam_id" class="staff-form-control">
                </div>
                

                
                <button type="submit" class="staff-submit-btn">
                    <i class="fas fa-save"></i> ذخیره تغییرات
                </button>
            </form>
        </div>
    </div>
</div>

<!-- مدال ریست پسورد -->
<div id="resetPasswordModal" class="staff-modal">
    <div class="staff-modal-content">
        <div class="staff-modal-header">
            <h3>تغییر رمز عبور</h3>
            <span class="staff-modal-close">&times;</span>
        </div>
        <div class="staff-modal-body">
            <form id="resetPasswordForm" method="POST" action="reset_staff_password.php">
                <input type="hidden" name="staff_id" id="reset-staff-id">
                
                <div class="staff-form-group">
                    <label for="new-password">رمز عبور جدید</label>
                    <input type="password" id="new-password" name="new_password" class="staff-form-control" required>
                </div>
                
                <div class="staff-form-group">
                    <label for="confirm-password">تکرار رمز عبور</label>
                    <input type="password" id="confirm-password" name="confirm_password" class="staff-form-control" required>
                </div>
                
                <button type="submit" class="staff-submit-btn">
                    <i class="fas fa-key"></i> تغییر رمز عبور
                </button>
            </form>
        </div>
    </div>
</div>

<!-- مدال حذف استف -->
<div id="deleteStaffModal" class="staff-modal">
    <div class="staff-modal-content">
        <div class="staff-modal-header">
            <h3>حذف استف</h3>
            <span class="staff-modal-close">&times;</span>
        </div>
        <div class="staff-modal-body">
            <p>آیا مطمئن هستید که می‌خواهید این استف را حذف کنید؟ این عمل قابل بازگشت نیست.</p>
            
            <form id="deleteStaffForm" method="POST" action="staff_management.php">
                <input type="hidden" name="staff_id" id="delete-staff-id">
                <input type="hidden" name="delete_staff" value="1">
                
                <div class="staff-form-group">
                    <label for="delete-reason">دلیل حذف (اختیاری)</label>
                    <textarea id="delete-reason" name="reason" class="staff-form-control" rows="3"></textarea>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="staff-cancel-btn close-modal">
                        <i class="fas fa-times"></i> انصراف
                    </button>
                    <button type="submit" class="staff-submit-btn delete">
                        <i class="fas fa-trash"></i> تایید حذف
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- اسکریپت‌های جاوااسکریپت -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="assets/js/staff_management.js"></script>

<?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>