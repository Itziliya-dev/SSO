<?php
// فایل: auth.php (نسخه نهایی و اصلاح‌شده برای رفع ارور)

require_once __DIR__.'/includes/config.php';
require_once __DIR__.'/includes/auth_functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$conn = null; // تعریف متغیر اتصال در خارج از بلوک try

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

    // اتصال به دیتابیس فقط یک بار در ابتدا ایجاد می‌شود
    $conn = getDbConnection();
    $ip_address = $_SERVER['REMOTE_ADDR'];

    // 1. محافظت در برابر حملات Brute-Force
    $stmt_check = $conn->prepare(
        "SELECT COUNT(*) as attempts FROM login_attempts WHERE ip_address = ? AND attempt_time > (NOW() - INTERVAL 15 MINUTE)"
    );
    $stmt_check->bind_param("s", $ip_address);
    $stmt_check->execute();
    $result = $stmt_check->get_result()->fetch_assoc();
    $stmt_check->close();

    if ($result['attempts'] > 10) {
        throw new Exception('تعداد تلاش‌های ناموفق بیش از حد مجاز است. لطفاً 15 دقیقه دیگر تلاش کنید.');
    }

    // 2. احراز هویت کاربر (ارسال $conn به عنوان پارامتر)
    $authResult = authenticateUserOrStaff($conn, $username, $password);

    if ($authResult['status'] === 'error') {
        // ثبت تلاش ناموفق (اتصال هنوز باز است و این کد به درستی کار می‌کند)
        $stmt_log = $conn->prepare("INSERT INTO login_attempts (ip_address, username) VALUES (?, ?)");
        $stmt_log->bind_param("ss", $ip_address, $username);
        $stmt_log->execute();
        $stmt_log->close();
        
        throw new Exception($authResult['message']);
    }

    // 3. ایجاد سشن امن
    session_regenerate_id(true);
    $_SESSION = [];

    $user_info = $authResult['data'];
    
    // ذخیره اطلاعات در سشن (کد بدون تغییر)
    $_SESSION['user_id'] = $user_info['id'];
    $_SESSION['username'] = $user_info['username'];
    $_SESSION['user_type'] = $user_info['type'];
    $_SESSION['is_staff'] = $user_info['is_staff'] ?? 0;
    
    if ($user_info['type'] === 'user') {
        $_SESSION['is_owner'] = $user_info['is_owner'] ?? 0;
        $_SESSION['has_user_panel'] = $user_info['has_user_panel'] ?? 1;
        $_SESSION['can_access_server_control'] = false;
    } elseif ($user_info['type'] === 'staff') {
        $_SESSION['is_owner'] = 0;
        $_SESSION['has_user_panel'] = $user_info['has_user_panel'] ?? 0;
        $_SESSION['staff_record_id'] = $user_info['staff_record_id'];
        $_SESSION['is_verify'] = $user_info['is_verify'] ?? 0;
        $_SESSION['permissions'] = $user_info['permissions'] ?? '';
        
        // خواندن آخرین ورود برای استف از دیتابیس
        $last_login_stmt = $conn->prepare("SELECT last_login FROM `staff-manage` WHERE id = ?");
        $last_login_stmt->bind_param("i", $_SESSION['staff_record_id']);
        $last_login_stmt->execute();
        $last_login_res = $last_login_stmt->get_result()->fetch_assoc();
        $_SESSION['last_login_staff'] = $last_login_res['last_login'] ?? 'نامشخص';
        $last_login_stmt->close();
        
        $staff_permissions = strtolower(trim($user_info['permissions'] ?? ''));
        $_SESSION['can_access_server_control'] = ($staff_permissions === 'dev');
    }

    // ایجاد توکن SSO (در صورت نیاز به ارتباط با سیستم‌های دیگر)
    $tokenData = [
        'user_id' => $_SESSION['user_id'],
        'username' => $_SESSION['username'],
        'user_type' => $_SESSION['user_type'],
        'created_at' => time(),
        'expires_at' => time() + 3600, // اعتبار برای 1 ساعت
        'ip' => $_SERVER['REMOTE_ADDR']
    ];
    $token = generateSsoToken($tokenData);

    // تنظیم کوکی امن
    setcookie('sso_token', $token, [
        'expires' => $tokenData['expires_at'],
        'path' => '/',
        'domain' => '.itziliya-dev.ir', // این دامنه باید با دامنه شما مطابقت داشته باشد
        'secure' => true,   // فقط روی HTTPS ارسال شود
        'httponly' => true, // فقط توسط پروتکل HTTP قابل دسترسی باشد
        'samesite' => 'Lax'
    ]);

    // هدایت کاربر به داشبورد
    header('Location: dashboard.php');
    exit();

} catch (Exception $e) {
    // لاگ کردن خطا در سمت سرور
    error_log('SSO Auth Error: ' . $e->getMessage() . ' | IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'N/A') . ' | User: ' . ($_POST['username'] ?? 'N/A'));
    
    // *** تغییر کلیدی: ذخیره خطا در سشن به جای URL ***
    $_SESSION['login_error'] = $e->getMessage();
    
    // هدایت به صفحه ورود بدون پارامتر در URL
    header('Location: login.php');
    exit();

} finally {
    if ($conn) {
        $conn->close();
    }
}
?>