<?php
// این متغیرها باید در فایل اصلی (که این فایل را include می‌کند) تعریف شده باشند
// global $currentPage, $pending_requests_count; 
?>
<aside class="admin-sidebar">
    <div class="sidebar-header">
        <div class="sidebar-brand-container">
            <h2 class="sidebar-title">
                <i class="fas fa-user-shield"></i>
                <span>پنل مدیریت</span>
            </h2>
        </div>
    </div>
    <nav class="sidebar-nav">
        <a href="admin_panel.php" class="nav-link <?php if (isset($currentPage) && $currentPage === 'admin_panel') echo 'active'; ?>">
            <i class="fas fa-tachometer-alt"></i><span>داشبورد</span>
        </a>
        <a href="users_management.php" class="nav-link <?php if (isset($currentPage) && $currentPage === 'users_management') echo 'active'; ?>">
            <i class="fas fa-users-cog"></i><span>مدیریت کاربران</span>
        </a>
        <a href="staff_management.php" class="nav-link <?php if (isset($currentPage) && $currentPage === 'staff_management') echo 'active'; ?>">
            <i class="fa-solid fa-users-line"></i><span>مدیریت استف‌ها</span>
        </a>
        <a href="registration_requests_page.php" class="nav-link <?php if (isset($currentPage) && $currentPage === 'registration_requests') echo 'active'; ?>">
             <i class="fas fa-user-clock"></i><span>درخواست‌های ثبت‌نام</span>
             <?php if (isset($pending_requests_count) && $pending_requests_count > 0): ?>
                <span class="nav-badge"><?= htmlspecialchars($pending_requests_count) ?></span>
             <?php endif; ?>
        </a>
        <a href="staff_archive.php" class="nav-link <?php if (isset($currentPage) && $currentPage === 'staff_archive') echo 'active'; ?>">
            <i class="fas fa-archive"></i><span>آرشیو</span>
        </a>
        <a href="security_alerts.php" class="nav-link <?php if (isset($currentPage) && $currentPage === 'security_alerts') echo 'active'; ?>" id="sidebarSecurityAlertsBtn">
            <i class="fas fa-bell"></i><span>هشدارهای امنیتی</span>
            <span class="alert-badge" id="sidebarAlertBadge" style="display: none;"></span>
        </a>
        </nav>
    <div class="sidebar-footer">
        <a href="/Dashboard/dashboard.php" class="nav-link">
            <i class="fas fa-arrow-left"></i><span>بازگشت</span>
        </a>
    </div>
</aside>