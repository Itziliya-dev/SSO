<?php
// اطمینان حاصل می‌کنیم که سشن شروع شده و متغیرها در دسترس هستند
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// متغیرهای لازم را از سشن یا به صورت پیش‌فرض تعریف می‌کنیم
$currentPage = $currentPage ?? '';
$perms = $_SESSION['permissions'] ?? [];
$pending_requests_count = $pending_requests_count ?? 0; // برای جلوگیری از خطا اگر تعریف نشده بود
?>
<aside class="admin-sidebar">
    <div class="sidebar-header">
        <div class="sidebar-brand-container">
            <h2 class="sidebar-title">
                <a href="/admin/admin_panel.php" class="sidebar-brand-link">
                    <i class="fas fa-user-shield"></i>
                    <span>پنل مدیریت</span>
                </a>
            </h2>
        </div>
    </div>
    <nav class="sidebar-nav">
        <?php if (!empty($perms['can_view_dashboard'])): ?>
            <a href="admin_panel.php" class="nav-link <?= ($currentPage === 'admin_panel') ? 'active' : '' ?>">
                <i class="fas fa-tachometer-alt"></i><span>داشبورد</span>
            </a>
        <?php endif; ?>

        <?php if (!empty($perms['can_manage_users'])): ?>
            <a href="users_management.php" class="nav-link <?= ($currentPage === 'users_management') ? 'active' : '' ?>">
                <i class="fas fa-users-cog"></i><span>مدیریت کاربران</span>
            </a>
        <?php endif; ?>

        <?php if (!empty($perms['can_manage_staff'])): ?>
            <a href="staff_management.php" class="nav-link <?= ($currentPage === 'staff_management') ? 'active' : '' ?>">
                <i class="fa-solid fa-users-line"></i><span>مدیریت استف‌ها</span>
            </a>
        <?php endif; ?>
        
        <?php if (!empty($perms['can_manage_permissions'])): ?>
            <a href="manage_permissions.php" class="nav-link <?= ($currentPage === 'manage_permissions') ? 'active' : '' ?>">
                <i class="fas fa-key"></i><span>مدیریت دسترسی ها</span>
            </a>
        <?php endif; ?>

        <?php if (!empty($perms['can_create_user'])): ?>
            <a href="create_user.php" class="nav-link <?= ($currentPage === 'create_user') ? 'active' : '' ?>">
                <i class="fas fa-user-plus"></i><span>ایجاد کاربر</span>
            </a>
        <?php endif; ?>

        <?php if (!empty($perms['can_manage_requests'])): ?>
            <a href="registration_requests_page.php" class="nav-link <?= ($currentPage === 'registration_requests') ? 'active' : '' ?>">
                <i class="fas fa-user-clock"></i><span>درخواست‌های ثبت‌نام</span>
                <?php if ($pending_requests_count > 0): ?>
                    <span class="nav-badge"><?= htmlspecialchars($pending_requests_count) ?></span>
                <?php endif; ?>
            </a>
        <?php endif; ?>
        <?php if (!empty($perms['can_view_archive'])): ?>
            <a href="staff_archive.php" class="nav-link <?= ($currentPage === 'staff_archive') ? 'active' : '' ?>">
                <i class="fas fa-archive"></i><span>آرشیو</span>
            </a>
        <?php endif; ?>

        <?php if (!empty($perms['can_view_chart'])): ?>
            <a href="management_chart.php" class="nav-link <?= ($currentPage === 'management_chart') ? 'active' : '' ?>">
                <i class="fas fa-sitemap"></i><span>چارت مدیریت</span>
            </a>
        <?php endif; ?>
        
        <?php if (!empty($perms['can_view_alerts'])): ?>
            <a href="security_alerts.php" class="nav-link <?= ($currentPage === 'security_alerts') ? 'active' : '' ?>" id="sidebarSecurityAlertsBtn">
                <i class="fas fa-bell"></i><span>هشدارهای امنیتی</span>
                <span class="alert-badge" id="sidebarAlertBadge" style="display: none;"></span>
            </a>
        <?php endif; ?>

        <?php if (!empty($perms['can_manage_settings'])): ?>
            <a href="settings_page.php" class="nav-link <?= ($currentPage === 'settings') ? 'active' : '' ?>">
                <i class="fas fa-cog"></i><span>تنظیمات</span>
            </a>
        <?php endif; ?>
    </nav>

    <div class="sidebar-version">
        <span>Vui_v2.1</span>
    </div>

    <div class="sidebar-footer">
        <a href="/Dashboard/dashboard.php" class="nav-link">
            <i class="fas fa-arrow-left"></i><span>بازگشت</span>
        </a>
    </div>
</aside>