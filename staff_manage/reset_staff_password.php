<?php

require_once '/var/www/sso-system/includes/config.php';
require_once '/var/www/sso-system/includes/database.php';
require_once '/var/www/sso-system/includes/auth_functions.php';

header('Content-Type: application/json');

session_start();

// بررسی دسترسی ادمین
if (!isset($_SESSION['is_owner']) || !$_SESSION['is_owner']) {
    echo json_encode(['success' => false, 'message' => 'دسترسی غیرمجاز']);
    exit();
}

// بررسی METHOD درخواست
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'متد درخواست نامعتبر']);
    exit();
}

// دریافت و اعتبارسنجی داده‌ها
$staff_id = $_POST['staff_id'] ?? 0;
$new_password = $_POST['new_password'] ?? '';

if (empty($staff_id) || !is_numeric($staff_id)) {
    echo json_encode(['success' => false, 'message' => 'شناسه استاف نامعتبر است']);
    exit();
}

if (strlen($new_password) < 6) {
    echo json_encode(['success' => false, 'message' => 'رمز عبور باید حداقل 6 کاراکتر باشد']);
    exit();
}

try {
    $conn = getDbConnection();

    // هش کردن رمز عبور
    $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);

    $stmt = $conn->prepare("UPDATE `staff-manage` SET 
        password = ?,
        updated_at = NOW()
        WHERE id = ?");

    $stmt->bind_param("si", $hashed_password, $staff_id);
    $stmt->execute();

    echo json_encode(['success' => true, 'message' => 'رمز عبور با موفقیت تغییر یافت']);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'خطا در تغییر رمز عبور: ' . $e->getMessage()
    ]);
}
