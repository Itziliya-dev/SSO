<?php
require_once __DIR__.'/../includes/config.php';
require_once __DIR__.'/../includes/database.php';

session_start();

// آدرس بازگشت (redirect_uri) باید حتما مشخص شده باشد
if (!isset($_GET['redirect_uri'])) {
    die('خطای SSO: آدرس بازگشت مشخص نشده است.');
}
$_SESSION['sso_redirect_uri'] = $_GET['redirect_uri'];

// اگر کاربر لاگین نکرده، او را به صفحه لاگین اصلی بفرست
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

// اگر کاربر لاگین کرده بود، برایش یک توکن یک‌بار مصرف بساز
$conn = getDbConnection();
$token = bin2hex(random_bytes(32));
$expires_at = (new DateTime())->add(new DateInterval('PT5M'))->format('Y-m-d H:i:s'); // ۵ دقیقه اعتبار

$stmt = $conn->prepare("INSERT INTO sso_auth_tokens (user_id, user_type, token, redirect_uri, expires_at) VALUES (?, ?, ?, ?, ?)");
$stmt->bind_param("issss", $_SESSION['user_id'], $_SESSION['user_type'], $token, $_SESSION['sso_redirect_uri'], $expires_at);
$stmt->execute();
$conn->close();

// کاربر را با توکن به پنل مقصد برگردان
$redirect_url = $_SESSION['sso_redirect_uri'] . '?token=' . $token;
unset($_SESSION['sso_redirect_uri']);
header('Location: ' . $redirect_url);
exit();