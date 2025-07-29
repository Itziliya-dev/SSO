<?php
require_once __DIR__.'/../includes/config.php';
require_once __DIR__.'/../includes/auth_functions.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (empty($_SESSION['permissions']['is_owner']) || empty($_SESSION['permissions']['can_manage_staff'])) {
    header('Location: /Dashboard/dashboard.php');
    exit();
}
// --- بخش API: پردازش تمام درخواست‌های AJAX ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action'])) {
    if (!isset($_SESSION['is_owner']) || !$_SESSION['is_owner']) {
        echo json_encode(['success' => false, 'message' => 'دسترسی غیر مجاز']);
        exit();
    }

    $conn = getDbConnection();
    $action = $_POST['action'];
    $staffId = isset($_POST['staff_id']) ? (int)$_POST['staff_id'] : 0;
    header('Content-Type: application/json');

    switch ($action) {
        case 'get_staff_details':
            if ($staffId > 0) {
                $stmt = $conn->prepare("SELECT id, fullname, username, email, phone, age, discord_id, steam_id, permissions, created_at, is_active, is_verify, discord_conn FROM `staff-manage` WHERE id = ?");
                $stmt->bind_param("i", $staffId);
                $stmt->execute();
                $staff = $stmt->get_result()->fetch_assoc();
                if ($staff) {
                    // فرمت‌دهی تاریخ برای نمایش بهتر
                    $staff['created_at_formatted'] = date('Y/m/d H:i', strtotime($staff['created_at']));
                    echo json_encode(['success' => true, 'staff' => $staff]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'استف یافت نشد']);
                }
            }
            break;

        case 'activate_staff':
            if ($staffId > 0) {
                $stmt = $conn->prepare("UPDATE `staff-manage` SET is_active = 1 WHERE id = ?");
                $stmt->bind_param("i", $staffId);
                $stmt->execute();
                echo json_encode(['success' => true, 'message' => 'استف با موفقیت فعال شد']);
            }
            break;

        case 'suspend_staff':
            if ($staffId > 0) {
                $stmt = $conn->prepare("UPDATE `staff-manage` SET is_active = 0 WHERE id = ?");
                $stmt->bind_param("i", $staffId);
                $stmt->execute();
                echo json_encode(['success' => true, 'message' => 'استف با موفقیت غیرفعال شد']);
            }
            break;

        case 'verify_staff':
            if ($staffId > 0) {
                $stmt = $conn->prepare("UPDATE `staff-manage` SET is_verify = 1 WHERE id = ?");
                $stmt->bind_param("i", $staffId);
                $stmt->execute();
                echo json_encode(['success' => true, 'message' => 'هویت استف با موفقیت تایید شد']);
            }
            break;

        case 'reset_password_staff':
            if ($staffId > 0 && isset($_POST['new_password'])) {
                $newPassword = $_POST['new_password'];
                if (empty($newPassword) || strlen($newPassword) < 6) {
                    echo json_encode(['success' => false, 'message' => 'رمز عبور باید حداقل ۶ کاراکتر باشد']);
                    exit();
                }
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT); // از هشینگ استاندارد استفاده کنید
                $stmt = $conn->prepare("UPDATE `staff-manage` SET password = ? WHERE id = ?");
                $stmt->bind_param("si", $hashedPassword, $staffId);
                $stmt->execute();
                echo json_encode(['success' => true, 'message' => 'رمز عبور استف با موفقیت تغییر یافت']);
            } else {
                echo json_encode(['success' => false, 'message' => 'اطلاعات ناقص است']);
            }
            break;

        case 'update_staff':
            if ($staffId > 0) {
                // دریافت تمام فیلدهای ارسالی از فرم ویرایش
                $fullname = $_POST['fullname'] ?? '';
                $username = $_POST['username'] ?? '';
                $email = $_POST['email'] ?? null;
                $phone = $_POST['phone'] ?? null;
                $permissions = $_POST['permissions'] ?? null;
                // ... سایر فیلدها

                // اعتبارسنجی ساده
                if (empty($fullname) || empty($username)) {
                    echo json_encode(['success' => false, 'message' => 'نام کامل و نام کاربری نمی‌توانند خالی باشند.']);
                    exit();
                }

                $stmt = $conn->prepare("UPDATE `staff-manage` SET fullname = ?, username = ?, email = ?, phone = ?, permissions = ? WHERE id = ?");
                $stmt->bind_param("sssssi", $fullname, $username, $email, $phone, $permissions, $staffId);

                if ($stmt->execute()) {
                    echo json_encode(['success' => true, 'message' => 'اطلاعات استف با موفقیت ویرایش شد']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'خطا در ویرایش اطلاعات استف: ' . $stmt->error]);
                }
            }
            break;

// این کیس جایگزین کیس 'delete_staff' می‌شود
        case 'archive_staff':
            // چک می‌کنیم که هر سه پارامتر لازم ارسال شده باشد
            if ($staffId > 0 && isset($_POST['reason']) && isset($_POST['deleted_by'])) {
                $reason = trim($_POST['reason']);
                $deleted_by = trim($_POST['deleted_by']); // از فرم خوانده می‌شود

            if (empty($reason) || empty($deleted_by)) {
                echo json_encode(['success' => false, 'message' => 'لطفاً دلیل و نام دیموت کننده را وارد کنید.']);
                exit();
            }

                // شروع تراکنش برای اطمینان از صحت عملیات
                $conn->begin_transaction();

                try {
                    // مرحله ۱: خواندن اطلاعات کامل استف از جدول اصلی
                    $stmt_select = $conn->prepare("SELECT * FROM `staff-manage` WHERE id = ?");
                    $stmt_select->bind_param("i", $staffId);
                    $stmt_select->execute();
                    $staff_data = $stmt_select->get_result()->fetch_assoc();

                    if ($staff_data) {
                        // مرحله ۲: وارد کردن اطلاعات در جدول deleted_staff
                        $stmt_insert = $conn->prepare(
                            "INSERT INTO deleted_staff (original_id, fullname, username, email, phone, age, discord_id, discord_id2, steam_id, permissions, joined_at, deleted_at, delete_reason, deleted_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?)"
                        );
                        $stmt_insert->bind_param(
                            "issssisssssss",
                            $staff_data['id'],
                            $staff_data['fullname'],
                            $staff_data['username'],
                            $staff_data['email'],
                            $staff_data['phone'],
                            $staff_data['age'],
                            $staff_data['discord_id'],
                            $staff_data['discord_id2'],
                            $staff_data['steam_id'],
                            $staff_data['permissions'],
                            $staff_data['created_at'], // created_at از جدول اصلی معادل joined_at است
                            $reason,
                            $deleted_by
                        );
                        $stmt_insert->execute();

                        // مرحله ۳: حذف استف از جدول اصلی
                        $stmt_delete = $conn->prepare("DELETE FROM `staff-manage` WHERE id = ?");
                        $stmt_delete->bind_param("i", $staffId);
                        $stmt_delete->execute();

                        // اگر همه چیز موفق بود، تراکنش را تایید کن
                        $conn->commit();
                        echo json_encode(['success' => true, 'message' => 'استف با موفقیت دیموت و آرشیو شد']);

                    } else {
                        throw new Exception('استف مورد نظر برای آرشیو یافت نشد.');
                    }
                } catch (Exception $e) {
                    // اگر خطایی رخ داد، تمام تغییرات را لغو کن
                    $conn->rollback();
                    echo json_encode(['success' => false, 'message' => 'خطا در عملیات دیتابیس: ' . $e->getMessage()]);
                }

            } else {
                echo json_encode(['success' => false, 'message' => 'اطلاعات ناقص است.']);
            }
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'عملیات نامعتبر']);
    }
    exit();
}

// --- بخش نمایش صفحه ---


require_once __DIR__.'/../includes/header.php'; 


$currentPage = 'staff_management'; // ۱. تعریف صفحه فعلی برای فعال شدن لینک صحیح در سایدبار
$conn = getDbConnection();
$staff_result = $conn->query("SELECT * FROM `staff-manage` ORDER BY created_at DESC");
$pending_requests_count = $conn->query("SELECT COUNT(id) as count FROM `registration_requests` WHERE status = 'pending'")->fetch_assoc()['count'];
?>
    <title>مدیریت استف‌ها | پنل مدیریت</title>
    
    <link rel="stylesheet" href="/../assets/css/admin.css">
    <link rel="stylesheet" href="/../assets/css/admin_dashboard_redesign.css">
    <link rel="stylesheet" href="/../assets/css/custom-dialog.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/assets/fonts/Vazirmatn-font-face.css">    
    <style>
        .status-badges-group { display: flex; flex-direction: column; gap: 5px; align-items: flex-start; }
        .permission-badge.staff-perm { background-color: rgba(111, 66, 193, 0.2); color: #6f42c1; }
        .action-btn.disabled { opacity: 0.5; cursor: not-allowed !important; }
        .modal-body .form-group { margin-bottom: 15px; }
        .modal-body .form-group label { display: block; margin-bottom: 5px; font-weight: 500; }
    </style>
<?php
?>

<div id="staffModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="staffModalTitle"></h3>
            <span class="close-modal">&times;</span>
        </div>
        <div class="modal-body" id="staffModalBody"></div>
    </div>
</div>

<div class="admin-layout">
    <?php include __DIR__.'/../includes/_sidebar.php'; // ۳. فراخوانی سایدبار از فایل مجزا?>


    <main class="main-content">
        <header class="main-header">
            <h1 class="header-title">مدیریت استف‌ها</h1>
        </header>
        
        <div class="admin-card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2><i class="fas fa-list-ul"></i> لیست استف‌ها</h2>
                <input type="text" id="staffSearch" placeholder="جستجوی استف..." class="form-control" style="width: 300px;">
            </div>
            
            <div class="table-container">
                <table class="user-table">
                    <thead>
                        <tr>
                            <th>استف</th>
                            <th>سطح دسترسی</th>
                            <th>وضعیت</th>
                            <th>تاریخ عضویت</th>
                            <th>عملیات</th>
                        </tr>
                    </thead>
                    <tbody id="staff-table-body">
                        <?php if ($staff_result && $staff_result->num_rows > 0): ?>
                            <?php while ($staff = $staff_result->fetch_assoc()): ?>
                            <tr data-id="<?= $staff['id'] ?>" data-search-term="<?= strtolower(htmlspecialchars($staff['fullname'] . ' ' . $staff['username'] . ' ' . $staff['email'])) ?>">
                                <td>
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <div><strong><?= htmlspecialchars($staff['fullname']) ?></strong><div class="staff-username" style="font-size: 12px; color: var(--text-muted);"><?= htmlspecialchars($staff['username']) ?></div></div>
                                    </div>
                                </td>
                                <td><span class="permission-badge staff-perm"><?= htmlspecialchars($staff['permissions'] ?? 'تعیین نشده') ?></span></td>
                                <td>
                                    <div class="status-badges-group">
                                        <span class="status-badge <?= $staff['is_active'] ? 'active' : 'suspended' ?>"><i class="fas fa-power-off fa-fw"></i> <?= $staff['is_active'] ? 'فعال' : 'غیرفعال' ?></span>
                                        <span class="status-badge <?= ($staff['is_verify'] ?? 0) ? 'approved' : 'pending' ?>"><i class="fas fa-user-check fa-fw"></i> <?= ($staff['is_verify'] ?? 0) ? 'تایید هویت' : 'عدم تایید' ?></span>
                                        <span class="status-badge <?= ($staff['discord_conn'] ?? 0) ? 'active' : 'rejected' ?>"><i class="fab fa-discord fa-fw"></i> <?= ($staff['discord_conn'] ?? 0) ? 'متصل' : 'عدم اتصال' ?></span>
                                    </div>
                                </td>
                                <td><?= date('Y/m/d', strtotime($staff['created_at'])) ?></td>
                                <td class="actions">
                                    <button class="action-btn view-details" title="مشاهده" data-id="<?= $staff['id'] ?>"><i class="fas fa-eye"></i></button>
                                    <button class="action-btn edit-btn" title="ویرایش" data-id="<?= $staff['id'] ?>"><i class="fas fa-edit"></i></button>
                                    <button class="action-btn reset-btn" title="ریست پسورد" data-id="<?= $staff['id'] ?>"><i class="fas fa-key"></i></button>
                                    <button class="action-btn verify-btn <?= ($staff['is_verify'] ?? 0) ? 'disabled' : '' ?>" title="احراز هویت" data-id="<?= $staff['id'] ?>" <?= ($staff['is_verify'] ?? 0) ? 'disabled' : '' ?>><i class="fas fa-user-shield"></i></button>
                                    <?php if ($staff['is_active']): ?>
                                        <button class="action-btn suspend-btn" title="غیرفعال کردن" data-id="<?= $staff['id'] ?>"><i class="fas fa-user-slash"></i></button>
                                    <?php else: ?>
                                        <button class="action-btn activate-btn" title="فعال کردن" data-id="<?= $staff['id'] ?>"><i class="fas fa-user-check"></i></button>
                                    <?php endif; ?>
                                    <button class="action-btn delete-btn" title="دیموت استف" data-id="<?= $staff['id'] ?>"><i class="fas fa-trash"></i></button>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="5" style="text-align: center; padding: 30px;">هیچ استفی یافت نشد.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<div class="notification-container top-right"></div>

<script src="/../assets/js/custom-dialog.js"></script>
<script> const currentAdminUsername = "<?= htmlspecialchars($_SESSION['username'] ?? 'مدیر سیستم') ?>"; </script>
<script>
// دیگر به این متغیر برای دیموت نیازی نیست، اما ممکن است جاهای دیگر استفاده شو
document.addEventListener('DOMContentLoaded', function() {
    // --- مدیریت مودال اصلی ---
    const modal = document.getElementById('staffModal');
    const modalTitle = document.getElementById('staffModalTitle');
    const modalBody = document.getElementById('staffModalBody');
    const closeModalBtn = modal.querySelector('.close-modal');

    const closeModal = () => modal.style.display = 'none';
    if(closeModalBtn) closeModalBtn.addEventListener('click', closeModal);
    window.addEventListener('click', (event) => {
        if (event.target == modal) closeModal();
    });

    // --- مدیریت جستجو در جدول ---
    document.getElementById('staffSearch').addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase();
        document.querySelectorAll('#staff-table-body tr').forEach(row => {
            row.style.display = row.dataset.searchTerm.includes(searchTerm) ? '' : 'none';
        });
    });

    // --- مدیریت تمام دکمه‌های عملیات ---
    document.getElementById('staff-table-body').addEventListener('click', async (e) => {
        const button = e.target.closest('.action-btn');
        if (!button || button.classList.contains('disabled')) return;

        const staffId = button.dataset.id;
        const staffRow = button.closest('tr');
        const staffName = staffRow ? staffRow.querySelector('.staff-username').textContent : 'استف';

        if (button.classList.contains('view-details')) {
            showStaffDetails(staffId);
        } else if (button.classList.contains('edit-btn')) {
            showEditStaffModal(staffId);
        } else if (button.classList.contains('reset-btn')) {
            showResetPasswordModal(staffId, staffName);
        } else if (button.classList.contains('delete-btn')) {
            showDemoteModal(staffId, staffName);
        } else if (button.classList.contains('activate-btn')) {
            if (await Dialog.confirm('فعال کردن استف', `آیا از فعال کردن "${staffName}" اطمینان دارید؟`)) {
                performAjaxAction({ action: 'activate_staff', staff_id: staffId }, true, true);
            }
        } else if (button.classList.contains('suspend-btn')) {
            if (await Dialog.confirm('غیرفعال کردن استف', `آیا از غیرفعال کردن "${staffName}" اطمینان دارید؟`)) {
                performAjaxAction({ action: 'suspend_staff', staff_id: staffId }, true, true);
            }
        } else if (button.classList.contains('verify-btn')) {
            if (await Dialog.confirm('احراز هویت استف', `آیا هویت "${staffName}" را تایید می‌کنید؟`)) {
                performAjaxAction({ action: 'verify_staff', staff_id: staffId }, true, true);
            }
        }
    });

    // --- توابع کمکی برای نمایش مودال‌ها ---

    // ✅ تابع دیموت با فیلدهای قابل ویرایش
    function showDemoteModal(staffId, staffName) {
        modalTitle.textContent = `دیموت استف: ${staffName}`;
        modalBody.innerHTML = `
            <form id="demoteStaffForm">
                <p>شما در حال دیموت کردن <strong>${staffName}</strong> هستید.</p>
                <div class="form-group" style="margin-top: 15px;">
                    <label for="demote-by">نام دیموت کننده:</label>
                    <input type="text" id="demote-by" class="form-control" required placeholder="نام خود را وارد کنید...">
                </div>
                <div class="form-group">
                    <label for="demote-reason">دلیل دیموت:</label>
                    <textarea id="demote-reason" class="form-control" rows="3" required placeholder="مثال: عدم فعالیت، تخلف از قوانین..."></textarea>
                </div>
                <button type="submit" class="submit-btn danger-btn"><i class="fas fa-user-minus"></i> تایید و دیموت</button>
            </form>
        `;
        modal.style.display = 'block';

        document.getElementById('demoteStaffForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const deletedByName = document.getElementById('demote-by').value;
            const reason = document.getElementById('demote-reason').value;
            
            if (!reason.trim() || !deletedByName.trim()) {
                showNotification('وارد کردن هر دو فیلد الزامی است.', 'error');
                return;
            }
            // ارسال نام دیموت کننده به همراه بقیه اطلاعات
            await performAjaxAction({ action: 'archive_staff', staff_id: staffId, reason: reason, deleted_by: deletedByName }, true, true);
            closeModal();
        });
    }

    async function showStaffDetails(staffId) {
        modalTitle.textContent = 'در حال بارگذاری...';
        modalBody.innerHTML = '<div style="text-align:center; padding: 20px;"><i class="fas fa-spinner fa-spin fa-2x"></i></div>';
        modal.style.display = 'block';
        const result = await performAjaxAction({ action: 'get_staff_details', staff_id: staffId }, false, false);
        if (result && result.success) {
            const staff = result.staff;
            modalTitle.textContent = `جزئیات استف: ${staff.fullname}`;
            modalBody.innerHTML = `<div class="user-details">
                <div class="detail-row"><span>نام کاربری:</span><span>${staff.username}</span></div>
                <div class="detail-row"><span>ایمیل:</span><span>${staff.email || '-'}</span></div>
                <div class="detail-row"><span>تلفن:</span><span>${staff.phone || '-'}</span></div>
                <div class="detail-row"><span>دیسکورد:</span><span>${staff.discord_id || '-'}</span></div>
                <div class="detail-row"><span>استیم:</span><span>${staff.steam_id || '-'}</span></div>
                <div class="detail-row"><span>سن:</span><span>${staff.age || '-'}</span></div>
                <div class="detail-row"><span>دسترسی:</span><span>${staff.permissions || 'تعیین نشده'}</span></div>
                <div class="detail-row"><span>تاریخ عضویت:</span><span>${staff.created_at_formatted || '-'}</span></div>
            </div>`;
        } else {
            modalBody.innerHTML = `خطا در دریافت اطلاعات: ${result ? result.message : 'مشکل در ارتباط با سرور'}`;
        }
    }
    
    async function showEditStaffModal(staffId) {
        modalTitle.textContent = 'در حال بارگذاری فرم ویرایش...';
        modalBody.innerHTML = '<div style="text-align:center; padding: 20px;"><i class="fas fa-spinner fa-spin fa-2x"></i></div>';
        modal.style.display = 'block';
        const result = await performAjaxAction({ action: 'get_staff_details', staff_id: staffId }, false, false);
        if (result && result.success) {
            const staff = result.staff;
            modalTitle.textContent = `ویرایش اطلاعات: ${staff.fullname}`;
            modalBody.innerHTML = `
                <form id="editStaffForm">
                    <input type="hidden" name="action" value="update_staff">
                    <input type="hidden" name="staff_id" value="${staff.id}">
                    <div class="form-group"><label for="edit-fullname">نام کامل:</label><input type="text" id="edit-fullname" name="fullname" class="form-control" value="${staff.fullname || ''}" required></div>
                    <div class="form-group"><label for="edit-username">نام کاربری:</label><input type="text" id="edit-username" name="username" class="form-control" value="${staff.username || ''}" required></div>
                    <div class="form-group"><label for="edit-email">ایمیل:</label><input type="email" id="edit-email" name="email" class="form-control" value="${staff.email || ''}"></div>
                    <div class="form-group"><label for="edit-phone">تلفن:</label><input type="text" id="edit-phone" name="phone" class="form-control" value="${staff.phone || ''}"></div>
                    <div class="form-group"><label for="edit-permissions">سطح دسترسی:</label><input type="text" id="edit-permissions" name="permissions" class="form-control" value="${staff.permissions || ''}"></div>
                    <button type="submit" class="submit-btn"><i class="fas fa-save"></i> ذخیره تغییرات</button>
                </form>
            `;
            document.getElementById('editStaffForm').addEventListener('submit', async (e) => {
                e.preventDefault();
                const formData = new FormData(e.target);
                await performAjaxAction(Object.fromEntries(formData.entries()), true, true);
                closeModal();
            });
        } else {
            modalBody.innerHTML = 'خطا در بارگذاری فرم ویرایش.';
        }
    }

    function showResetPasswordModal(staffId, staffName) {
        modalTitle.textContent = `ریست پسورد برای: ${staffName}`;
        modalBody.innerHTML = `
            <form id="resetPassForm">
                <div class="form-group">
                    <label for="new-staff-password">رمز عبور جدید:</label>
                    <input type="password" id="new-staff-password" class="form-control" required minlength="6">
                </div>
                <button type="submit" class="submit-btn"><i class="fas fa-key"></i> تغییر رمز عبور</button>
            </form>`;
        modal.style.display = 'block';

        document.getElementById('resetPassForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const newPassword = document.getElementById('new-staff-password').value;
            await performAjaxAction({ action: 'reset_password_staff', staff_id: staffId, new_password: newPassword }, true, true);
            closeModal();
        });
    }

    // --- تابع اصلی برای ارسال درخواست‌های AJAX ---
    
    async function performAjaxAction(data, showSuccessNotification = true, reloadPageOnSuccess = false) {
        try {
            const response = await fetch('staff_management.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams(data)
            });
            if (!response.ok) throw new Error('Network response was not ok.');
            
            const result = await response.json();
            if (result.success) {
                if (showSuccessNotification) showNotification(result.message, 'success');
                if (reloadPageOnSuccess) setTimeout(() => location.reload(), 1000);
                return result; 
            } else {
                showNotification(result.message || 'یک خطای نامشخص رخ داد', 'error');
                return null;
            }
        } catch (error) {
            console.error('AJAX Error:', error);
            showNotification('خطا در ارتباط با سرور، ممکن است مشکلی در پاسخ سرور وجود داشته باشد.', 'error');
            return null;
        }
    }
    
    // --- تابع برای نمایش نوتیفیکیشن‌ها ---

    function showNotification(message, type = 'success', duration = 4000) {
        let container = document.querySelector('.notification-container.top-right');
        if (!container) {
            container = document.createElement('div');
            container.className = 'notification-container top-right';
            document.body.appendChild(container);
        }
        const notification = document.createElement('div');
        notification.className = `notification ${type}`;
        notification.innerHTML = `<span>${message}</span><span class="notification-close">&times;</span>`;
        notification.querySelector('.notification-close').onclick = () => notification.remove();
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