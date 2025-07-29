<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__.'/../includes/config.php';
require_once __DIR__.'/../includes/database.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (empty($_SESSION['permissions']['is_owner']) || empty($_SESSION['permissions']['can_manage_permissions'])) {
    header('Location: /Dashboard/dashboard.php');
    exit();
}

$currentPage = 'manage_permissions';
$conn = getDbConnection();

// لیست تمام دسترسی‌های ممکن با ترجمه فارسی
$permissions_map = [
    'has_user_panel' => 'پنل کاربری',
    'has_developer_access' => 'دسترسی DEV',
    'is_owner' => 'ادمین (Owner)',
    'granular' => [
        'can_view_dashboard' => 'داشبورد', 
        'can_manage_users' => 'مدیریت کاربران',
        'can_manage_staff' => 'مدیریت استف‌ها', 
        'can_manage_permissions' => 'مدیریت دسترسی‌ها',
        'can_create_user' => 'ایجاد کاربر', 
        'can_manage_requests' => 'درخواست‌ها',
        'can_view_archive' => 'آرشیو', 
        'can_view_chart' => 'چارت مدیریت',
        'can_view_alerts' => 'هشدارهای امنیتی', 
        'can_manage_settings' => 'تنظیمات'
    ]
];
$all_perm_keys_str = "p.is_owner, p.has_user_panel, p.has_developer_access, " . implode(', ', array_map(fn($k) => "p.$k", array_keys($permissions_map['granular'])));

$users_query = $conn->query("SELECT u.id, u.username, u.fullname, {$all_perm_keys_str} FROM users u LEFT JOIN user_permissions p ON u.id = p.user_id ORDER BY u.id DESC");
if ($users_query === false) { die("خطا در کوئری کاربران: " . htmlspecialchars($conn->error)); }

$staff_query = $conn->query("SELECT s.id, s.username, s.fullname, {$all_perm_keys_str} FROM `staff-manage` s LEFT JOIN staff_permissions p ON s.id = p.staff_id ORDER BY s.id DESC");
if ($staff_query === false) { die("خطا در کوئری استف‌ها: " . htmlspecialchars($conn->error)); }

require_once __DIR__.'/../includes/header.php';
?>
<title>مدیریت دسترسی‌ها | SSO Center</title>
<link rel="stylesheet" href="/../assets/css/admin.css">
<link rel="stylesheet" href="/../assets/css/admin_dashboard_redesign.css">
<style>
    .table-container { max-height: none; }
    .permission-table .user-info { text-align: right; font-weight: 500; }
    .modal-content { max-width: 600px; }
    .modal-body { display: flex; flex-direction: column; gap: 15px; }
    .current-perms { background: rgba(0,0,0,0.2); padding: 15px; border-radius: 8px; }
    .current-perms h4 { margin-bottom: 10px; color: var(--text-secondary); }
    .perm-list { display: flex; flex-wrap: wrap; gap: 8px; list-style: none; padding: 0; }
    .perm-list li { background: #3a3a5a; padding: 5px 12px; border-radius: 15px; font-size: 13px; font-weight: 500;}
    .perm-list li.owner-all { background-color: var(--success-color); color: white; }
    .perm-list li.owner-partial { background-color: var(--primary-color); color: white; }
    .perm-toggle { display: flex; justify-content: space-between; align-items: center; background: rgba(0,0,0,0.2); padding: 10px; border-radius: 6px; }
    .perm-toggle label { font-weight: 500; }
    .switch { position: relative; display: inline-block; width: 50px; height: 26px; }
    .switch input { opacity: 0; width: 0; height: 0; }
    .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #333; transition: .4s; border-radius: 26px; }
    .slider:before { position: absolute; content: ""; height: 20px; width: 20px; left: 3px; bottom: 3px; background-color: white; transition: .4s; border-radius: 50%; }
    input:checked + .slider { background-color: var(--primary-color); }
    input:checked + .slider:before { transform: translateX(24px); }
    .granular-permissions-container { display: none; }
    .granular-permissions-container.active { display: block; }
    .custom-select-wrapper { position: relative; }
    .custom-select-button { width: 100%; background: #3a3a5a; border: 1px solid #4a4a6a; color: var(--text-secondary); padding: 10px 15px; border-radius: 6px; text-align: right; cursor: pointer; display: flex; justify-content: space-between; align-items: center; }
    .custom-select-panel { display: none; position: absolute; top: 105%; left: 0; width: 100%; background: #2a2a3a; border: 1px solid var(--border-color); border-radius: 8px; z-index: 1000; max-height: 250px; overflow-y: auto; padding: 5px; }
    .custom-select-panel.active { display: block; }
    .custom-select-option { display: flex; align-items: center; padding: 10px; cursor: pointer; border-radius: 5px; }
    .custom-select-option:hover { background-color: rgba(124, 77, 255, 0.2); }
    .custom-select-option input { margin-left: 10px; }
    #select-all-container { border-bottom: 1px solid var(--border-color); padding-bottom: 5px; margin-bottom: 5px; }
</style>

<div class="admin-layout">
    <?php include __DIR__.'/../includes/_sidebar.php'; ?>
    <main class="main-content">
        <div class="main-header">
            <h1 class="header-title"><i class="fas fa-tasks"></i> مدیریت دسترسی‌ها</h1>
        </div>
        
        <div class="admin-card">
            <div class="tabs-nav">
                <button class="tab-link active" onclick="openTab(event, 'users')"><i class="fas fa-users"></i> کاربران</button>
                <button class="tab-link" onclick="openTab(event, 'staff')"><i class="fas fa-user-shield"></i> استف‌ها</button>
            </div>

            <div id="users" class="tab-content active">
                <div class="table-container">
                    <table class="user-table permission-table">
                        <thead><tr><th class="user-info">کاربر</th><th>وضعیت ادمین</th><th>عملیات</th></tr></thead>
                        <tbody>
                            <?php if($users_query->num_rows > 0): while($user = $users_query->fetch_assoc()): ?>
                            <tr>
                                <td class="user-info"><?= htmlspecialchars($user['fullname'] ?: $user['username']) ?></td>
                                <td><?= !empty($user['is_owner']) ? '<span class="status-badge active">فعال</span>' : '<span class="status-badge suspended">غیرفعال</span>' ?></td>
                                <td><button class="action-btn edit-btn" data-type="user" data-id="<?= $user['id'] ?>" data-permissions='<?= htmlspecialchars(json_encode($user), ENT_QUOTES, 'UTF-8') ?>'><i class="fas fa-edit"></i></button></td>
                            </tr>
                            <?php endwhile; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div id="staff" class="tab-content">
                 <div class="table-container">
                    <table class="user-table permission-table">
                        <thead><tr><th class="user-info">استف</th><th>وضعیت ادمین</th><th>عملیات</th></tr></thead>
                        <tbody>
                            <?php if($staff_query->num_rows > 0): while($staff = $staff_query->fetch_assoc()): ?>
                            <tr>
                                <td class="user-info"><?= htmlspecialchars($staff['username'] ?: $staff['username']) ?></td>
                                <td><?= !empty($staff['is_owner']) ? '<span class="status-badge active">فعال</span>' : '<span class="status-badge suspended">غیرفعال</span>' ?></td>
                                <td><button class="action-btn edit-btn" data-type="staff" data-id="<?= $staff['id'] ?>" data-permissions='<?= htmlspecialchars(json_encode($staff), ENT_QUOTES, 'UTF-8') ?>'><i class="fas fa-edit"></i></button></td>
                            </tr>
                            <?php endwhile; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
</div>

<div id="permissionModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modal-title">ویرایش دسترسی‌ها</h3>
            <span class="close-modal">&times;</span>
        </div>
        <form id="permission-modal-form">
            <input type="hidden" name="user_id" id="modal_user_id">
            <input type="hidden" name="user_type" id="modal_user_type">
            <div class="modal-body">
                <div class="current-perms">
                    <h4>دسترسی‌های فعلی:</h4>
                    <ul class="perm-list" id="modal_current_perms"></ul>
                </div>
                <hr style="border-color: var(--border-color);">
                
                <div class="perm-toggle">
                    <label for="modal_has_user_panel">پنل کاربری</label>
                    <label class="switch"><input type="checkbox" name="permissions[has_user_panel]" id="modal_has_user_panel"><span class="slider"></span></label>
                </div>
                <div class="perm-toggle">
                    <label for="modal_has_developer_access">دسترسی DEV</label>
                    <label class="switch"><input type="checkbox" name="permissions[has_developer_access]" id="modal_has_developer_access"><span class="slider"></span></label>
                </div>
                <div class="perm-toggle" style="background-color: rgba(124, 77, 255, 0.1);">
                    <label for="modal_is_owner" style="color: var(--primary-color); font-weight: bold;">ادمین</label>
                    <label class="switch"><input type="checkbox" name="permissions[is_owner]" id="modal_is_owner"><span class="slider"></span></label>
                </div>
                
                <div class="granular-permissions-container" id="modal_granular_container">
                    <div class="custom-select-wrapper">
                        <button type="button" class="custom-select-button">
                            <span class="button-text">انتخاب دسترسی‌های ادمین</span>
                            <i class="fas fa-chevron-down arrow"></i>
                        </button>
                        <div class="custom-select-panel">
                             <label class="custom-select-option" id="select-all-container">
                                <input type="checkbox" id="modal_select_all">
                                <strong>انتخاب همه</strong>
                            </label>
                            <?php foreach($permissions_map['granular'] as $key => $label): ?>
                            <label class="custom-select-option">
                                <input class="permission-item-checkbox" type="checkbox" name="permissions[sections][]" value="<?= $key ?>">
                                <span><?= $label ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer" style="text-align: left; margin-top: 20px;">
                <button type="submit" class="btn-primary" style="background-color: var(--success-color);"><i class="fas fa-save"></i> ذخیره</button>
            </div>
        </form>
    </div>
</div>
<div class="notification-container top-right"></div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('permissionModal');
    const closeModalBtn = modal.querySelector('.close-modal');
    const form = document.getElementById('permission-modal-form');
    const allPermissionsMap = <?= json_encode($permissions_map) ?>;

    // Open Modal Logic
    document.querySelectorAll('.edit-btn').forEach(button => {
        button.addEventListener('click', function() {
            const id = this.dataset.id;
            const type = this.dataset.type;
            const perms = JSON.parse(this.dataset.permissions);
            
            // Populate Modal
            modal.querySelector('#modal-title').textContent = `ویرایش دسترسی‌های ${perms.fullname || perms.username}`;
            modal.querySelector('#modal_user_id').value = id;
            modal.querySelector('#modal_user_type').value = type;

            // Display current permissions
            const currentPermsList = modal.querySelector('#modal_current_perms');
            currentPermsList.innerHTML = '';
            let hasPerms = false;
            let isFullAdmin = perms.is_owner == 1;

            if (isFullAdmin) {
                for (const key in allPermissionsMap.granular) {
                    if (perms[key] != 1) { isFullAdmin = false; break; }
                }
            }
            
            if (isFullAdmin) {
                currentPermsList.innerHTML += `<li class="owner-all">ادمین (تمام بخش‌ها)</li>`;
                hasPerms = true;
            } else if (perms.is_owner == 1) {
                currentPermsList.innerHTML += `<li class="owner-partial">ادمین</li>`;
                hasPerms = true;
            }
            
            if (perms.has_user_panel == 1) { currentPermsList.innerHTML += `<li>${allPermissionsMap.has_user_panel}</li>`; hasPerms = true; }
            if (perms.has_developer_access == 1) { currentPermsList.innerHTML += `<li>${allPermissionsMap.has_developer_access}</li>`; hasPerms = true; }
            
            if (!hasPerms) { currentPermsList.innerHTML = '<li>هیچ دسترسی خاصی ندارد.</li>'; }
            // --- پایان بخش اصلاح شده ---

            //end

            // Set form controls state
            form.querySelector('#modal_has_user_panel').checked = perms.has_user_panel == 1;
            form.querySelector('#modal_has_developer_access').checked = perms.has_developer_access == 1;
            const isOwnerToggle = form.querySelector('#modal_is_owner');
            isOwnerToggle.checked = perms.is_owner == 1;

            // Handle granular permissions dropdown
            const granularContainer = form.querySelector('#modal_granular_container');
            granularContainer.classList.toggle('active', isOwnerToggle.checked);
            
            const granularCheckboxes = granularContainer.querySelectorAll('.permission-item-checkbox');
            granularCheckboxes.forEach(cb => {
                cb.checked = perms[cb.value] == 1;
            });
            updateButtonText(); // Update dropdown text

            modal.style.display = 'flex';
        });
    });

    // Close Modal Logic
    closeModalBtn.onclick = () => modal.style.display = 'none';
    window.onclick = (event) => { if (event.target == modal) modal.style.display = 'none'; }

    // --- Dropdown Logic ---
    const granularContainer = form.querySelector('#modal_granular_container');
    const customSelectButton = granularContainer.querySelector('.custom-select-button');
    const customSelectPanel = granularContainer.querySelector('.custom-select-panel');
    const buttonText = granularContainer.querySelector('.button-text');
    const granularCheckboxes = granularContainer.querySelectorAll('.permission-item-checkbox');
    const selectAllCheckbox = granularContainer.querySelector('#modal_select_all');

    function updateButtonText() {
        const checkedCount = Array.from(granularCheckboxes).filter(cb => cb.checked).length;
        if (checkedCount === 0) buttonText.textContent = 'انتخاب دسترسی‌های ادمین';
        else if (checkedCount === granularCheckboxes.length) buttonText.textContent = 'تمام بخش‌ها';
        else buttonText.textContent = `${checkedCount} بخش انتخاب شده`;
        selectAllCheckbox.checked = checkedCount === granularCheckboxes.length;
    }

    customSelectButton.addEventListener('click', (e) => {
        e.stopPropagation();
        customSelectPanel.classList.toggle('active');
    });

    selectAllCheckbox.addEventListener('change', function() {
        granularCheckboxes.forEach(cb => cb.checked = this.checked);
        updateButtonText();
    });
    
    granularCheckboxes.forEach(cb => cb.addEventListener('change', updateButtonText));

    // Toggle granular dropdown based on is_owner
    form.querySelector('#modal_is_owner').addEventListener('change', function() {
        granularContainer.classList.toggle('active', this.checked);
    });
    
    // --- Form Submission ---
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        const submitButton = this.querySelector('button[type="submit"]');
        const originalButtonHtml = submitButton.innerHTML;
        submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> در حال ذخیره...';
        submitButton.disabled = true;

        fetch('actions/update_permissions.php', { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
            showNotification(data.message, data.success ? 'success' : 'error');
            if (data.success) {
                modal.style.display = 'none';
                setTimeout(() => location.reload(), 1500); // Reload to show changes
            }
        })
        .finally(() => {
            submitButton.innerHTML = originalButtonHtml;
            submitButton.disabled = false;
        });
    });

});

    window.openTab = function(evt, tabName) {
        document.querySelectorAll('.tab-content').forEach(tc => tc.classList.remove('active'));
        document.querySelectorAll('.tab-link').forEach(tl => tl.classList.remove('active'));
        document.getElementById(tabName).classList.add('active');
        evt.currentTarget.classList.add('active');
    }
// Other functions (openTab, showNotification) remain the same
function showNotification(message, type = 'success') {
    const container = document.querySelector('.notification-container');
    if (!container) return;
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    const iconClass = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle';
    notification.innerHTML = `<i class="fas ${iconClass}"></i><span>${message}</span>`;
    container.style.display = 'block';
    container.appendChild(notification);
    setTimeout(() => {
        notification.style.animation = 'fadeOut 0.5s forwards';
        setTimeout(() => {
            notification.remove();
            if (container.children.length === 0) {
                 container.style.display = 'none';
            }
        }, 500);
    }, 4000);
}
</script>
</body>
</html>