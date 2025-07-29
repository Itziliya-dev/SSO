<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__.'/includes/config.php';
require_once __DIR__.'/includes/auth_functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$conn = null;

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Location: login.php');
        exit();
    }

    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        throw new Exception('نام کاربری و رمز عبور الزامی است');
    }

    $conn = getDbConnection();
    $ip_address = $_SERVER['REMOTE_ADDR'];

    // ۱. محافظت در برابر Brute-Force (بدون تغییر)
    $stmt_check = $conn->prepare(
        "SELECT COUNT(*) as attempts FROM login_attempts WHERE ip_address = ? AND attempt_time > (NOW() - INTERVAL 15 MINUTE)"
    );
    $stmt_check->bind_param("s", $ip_address);
    $stmt_check->execute();
    $result = $stmt_check->get_result()->fetch_assoc();
    $stmt_check->close();
    if ($result['attempts'] > 3) {
        throw new Exception('تعداد تلاش‌های ناموفق بیش از حد مجاز است. لطفاً 15 دقیقه دیگر تلاش کنید.');
    }

    // ۲. احراز هویت با تابع جدید
    $authResult = authenticateUserOrStaff($conn, $username, $password);

    if ($authResult['status'] === 'error') {
        // ثبت تلاش ناموفق
        $stmt_log = $conn->prepare("INSERT INTO login_attempts (ip_address, username, attempt_time) VALUES (?, ?, NOW())");
        $stmt_log->bind_param("ss", $ip_address, $username);
        $stmt_log->execute();
        $stmt_log->close();
        throw new Exception($authResult['message']);
    }

    // ۳. ایجاد سشن امن و ذخیره اطلاعات
    session_regenerate_id(true);
    $_SESSION = []; // پاک کردن سشن قدیمی

    $user_info = $authResult['data'];

    // ===== بخش کلیدی: ذخیره تمام دسترسی‌ها در سشن =====
    $_SESSION['user_id'] = $user_info['id'];
    $_SESSION['username'] = $user_info['username'];
    $_SESSION['user_type'] = $user_info['type'];

    // کل آرایه دسترسی‌ها را در سشن ذخیره می‌کنیم
    $_SESSION['permissions'] = $user_info['permissions']; 

    // این خط را برای سازگاری با کدهای قدیمی نگه می‌داریم
    $_SESSION['is_owner'] = !empty($user_info['permissions']['is_owner']);

    // اگر کاربر استف بود، اطلاعات تکمیلی آن را نیز ذخیره کن
    if ($user_info['type'] === 'staff') {
        $_SESSION['is_verify'] = $user_info['is_verify'] ?? 0;
    }
    // ===============================================

    // ایجاد توکن SSO (بدون تغییر)
    $tokenData = [
       'user_id' => $_SESSION['user_id'],
       'username' => $_SESSION['username'],
       'user_type' => $_SESSION['user_type'],
       'created_at' => time(),
       'expires_at' => time() + 3600,
       'ip' => $_SERVER['REMOTE_ADDR']
    ];
    $token = generateSsoToken($tokenData);

    // تنظیم کوکی (بدون تغییر)
    setcookie('sso_token', $token, [
       'expires' => $tokenData['expires_at'],
       'path' => '/',
       'domain' => $_SERVER['SERVER_NAME'], // استفاده از دامنه فعلی برای سازگاری بهتر
       'secure' => true,
       'httponly' => true,
       'samesite' => 'Lax'
    ]);

    // هدایت به داشبورد
    header('Location: /Dashboard/dashboard.php');
    exit();

} catch (Exception $e) {
    error_log('SSO Auth Error: ' . $e->getMessage() . ' | IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'N/A') . ' | User: ' . ($_POST['username'] ?? 'N/A'));
    $_SESSION['login_error'] = $e->getMessage();
    header('Location: login.php');
    exit();
} finally {
    if ($conn) {
        $conn->close();
    }
}