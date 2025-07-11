<?php
// File: OldSSO/Dashboard/passkey_management.php
require_once __DIR__.'/../includes/config.php';
require_once __DIR__.'/../includes/header.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

$username = $_SESSION['username'] ?? 'کاربر';
?>

    <title>مدیریت Passkey | SSO Center</title>
    <link rel="stylesheet" href="../assets/css/passkey_premium.css">
    <link rel="stylesheet" href="../assets/css/dashboard_premium.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<?php

?>
    <header class="dashboard-header">
        <a href="dashboard.php" class="header-logo">
            <img src="/../assets/images/logo.png" alt="Logo">
            <span>SSO Center</span>
        </a>
    </header>

    <div class="passkey-page-container">
        <div class="passkey-card">
            <h1 class="passkey-card-header"><i class="fas fa-key"></i> مدیریت کلیدهای عبور (Passkeys)</h1>
            <p class="form-hint">
                کلیدهای عبور (Passkeys) جایگزین امن و راحتی برای رمزهای عبور هستند. می‌توانید با استفاده از اثر انگشت، تشخیص چهره یا پین دستگاه خود وارد شوید.
            </p>
            <button id="add-passkey-btn" class="add-passkey-btn">
                <i class="fas fa-plus-circle"></i> افزودن کلید عبور جدید
            </button>
        </div>

        <div class="passkey-card">
            <h2 class="passkey-card-header"><i class="fas fa-list-ul"></i> کلیدهای ثبت شده</h2>
            <ul id="passkey-list" class="passkey-list">
                <li id="loading-placeholder">در حال بارگذاری... <i class="fas fa-spinner fa-spin"></i></li>
            </ul>
        </div>
    </div>
    
    <div id="confirm-modal" class="confirm-modal-overlay">
        <div class="confirm-modal-box">
            <h3>تایید حذف</h3>
            <p>آیا از حذف این کلید عبور اطمینان دارید؟ این عمل غیرقابل بازگشت است.</p>
            <div class="modal-actions">
                <button id="modal-btn-cancel" class="modal-btn btn-cancel">انصراف</button>
                <button id="modal-btn-confirm-delete" class="modal-btn btn-confirm-delete">حذف کن</button>
            </div>
        </div>
    </div>

    <script src="../assets/js/passkey_helpers.js"></script>
    <script src="../assets/js/passkey_management.js"></script>
</body>
</html>