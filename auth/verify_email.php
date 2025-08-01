<?php
require_once __DIR__.'/../includes/config.php';
require_once __DIR__.'/../includes/database.php';

$message = '';
$message_type = 'error'; // پیش‌فرض خطا است

if (isset($_GET['token']) && !empty($_GET['token'])) {
    $token = $_GET['token'];
    $conn = getDbConnection();

    // ابتدا در جدول استف جستجو کن
    $stmt = $conn->prepare("SELECT id, token_expires_at FROM `staff-manage` WHERE verification_token = ? LIMIT 1");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    $user_table = 'staff-manage';
    
    if ($result->num_rows === 0) {
        // اگر در استف نبود، در کاربران عادی جستجو کن
        $stmt->close();
        $stmt = $conn->prepare("SELECT id, token_expires_at FROM `users` WHERE verification_token = ? LIMIT 1");
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $result = $stmt->get_result();
        $user_table = 'users';
    }

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $user_id = $user['id'];
        $token_expires_at = new DateTime($user['token_expires_at']);
        $now = new DateTime();

        if ($now > $token_expires_at) {
            $message = "این لینک تایید منقضی شده است. لطفاً از پروفایل خود دوباره درخواست دهید.";
        } else {
            // توکن معتبر است، ایمیل را تایید کن
            $update_stmt = $conn->prepare("UPDATE `$user_table` SET email_verified_at = NOW(), verification_token = NULL, token_expires_at = NULL WHERE id = ?");
            $update_stmt->bind_param("i", $user_id);
            
            if ($update_stmt->execute()) {
                $message = "ایمیل شما با موفقیت تایید شد! 🎉";
                $message_type = 'success';
            } else {
                $message = "خطایی در فرآیند تایید رخ داد. لطفاً دوباره تلاش کنید.";
            }
            $update_stmt->close();
        }
    } else {
        $message = "لینک تایید نامعتبر یا استفاده شده است.";
    }
    $stmt->close();
    $conn->close();
} else {
    $message = "توکن تایید ارائه نشده است.";
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تایید ایمیل</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="/assets/fonts/Vazirmatn-font-face.css">
    <style>@import url('https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;700&display=swap'); body { font-family: 'Vazirmatn', sans-serif; }</style>
</head>
<body class="bg-gray-100 flex items-center justify-center h-screen">
    <div class="max-w-md w-full text-center bg-white p-8 rounded-xl shadow-lg">
        <?php if ($message_type === 'success'): ?>
            <i class="fas fa-check-circle text-5xl text-green-500 mb-4"></i>
        <?php else: ?>
            <i class="fas fa-times-circle text-5xl text-red-500 mb-4"></i>
        <?php endif; ?>
        
        <h1 class="text-2xl font-bold text-gray-800 mb-2">وضعیت تایید ایمیل</h1>
        <p class="text-gray-600 mb-6"><?= htmlspecialchars($message) ?></p>
        <a href="/login.php" class="px-6 py-2 text-white bg-blue-600 rounded-md hover:bg-blue-700">بازگشت به سایت</a>
    </div>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>
</body>
</html>