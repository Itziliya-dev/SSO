<?php
require_once __DIR__.'/includes/config.php';
session_start();

$error = isset($_GET['error']) ? $_GET['error'] : '';
$success = isset($_GET['success']) ? $_GET['success'] : '';
?>
<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>استعلام کد پیگیری | SSO Center</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="background-image"></div>
    
    <div class="login-container">
        <div class="login-box">
            <div class="logo-wrapper">
                <div class="logo-circle">
                    <img src="assets/images/logo.png" alt="Logo" class="logo">
                </div>
                <h1>استعلام کد پیگیری</h1>
            </div>

            <?php if ($error): ?>
            <div class="alert">
                <i class="fas fa-exclamation-circle"></i>
                <?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>

            <?php if ($success): ?>
            <div class="success-message">
                <i class="fas fa-check-circle"></i>
                <?= htmlspecialchars($success) ?>
            </div>
            <?php endif; ?>

            <form action="process_track_request.php" method="POST" class="login-form">
                <div class="input-group">
                    <label for="username">
                        <i class="fas fa-user"></i>
                        نام کاربری
                    </label>
                    <input 
                        type="text" 
                        id="username" 
                        name="username" 
                        placeholder="نام کاربری خود را وارد کنید" 
                        required
                    >
                </div>

                <button type="submit" class="login-btn">
                    <span>استعلام کد</span>
                    <i class="fas fa-search"></i>
                </button>
            </form>

            <div class="register-links">
                <a href="login.php">ورود به حساب کاربری</a>
                <a href="register.php">ثبت نام جدید</a>
            </div>
        </div>
    </div>
</body>
</html>