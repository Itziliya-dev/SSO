<?php
require_once __DIR__.'/includes/config.php';
session_start();

if (!isset($_SESSION['is_owner']) || !$_SESSION['is_owner']) {
    header('Location: login.php');
    exit();
}
?>

<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
<meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ورود به سیستم | SSO Center</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="preload" href="assets/images/background.jpg" as="image">
    <link rel="preload" href="assets/images/logo.png" as="image">
</head>
<body>
    <div class="background-image"></div>
    
    <div class="panel-selection">
        <div class="selection-box">
            <div class="logo-wrapper">
                <div class="logo-circle">
                    <img src="assets/images/logo.png" alt="Logo" class="logo">
                </div>
                <h1>سیستم متمرکز کنترل اطلاعات</h1>
            </div>
            
            <div class="buttons">
                <a href="<?= PANEL_URL ?>" class="btn-panel">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>ورود به پنل کاربری</span>
                </a>
                
                <a href="admin_panel.php" class="btn-admin">
                    <i class="fas fa-user-shield"></i>
                    <span>پنل مدیریت SSO</span>
                </a>
            </div>
        </div>
    </div>
</body>
</html>