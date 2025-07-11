<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

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

// بررسی وجود پارامتر ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'شناسه استاف نامعتبر است']);
    exit();
}

$staff_id = (int)$_GET['id'];

try {
    $conn = getDbConnection();

    // دریافت اطلاعات استاف از دیتابیس
    $stmt = $conn->prepare("SELECT * FROM `staff-manage` WHERE id = ?");
    $stmt->bind_param("i", $staff_id);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'استاف مورد نظر یافت نشد']);
        exit();
    }

    $staff = $result->fetch_assoc();

    // پنهان کردن رمز عبور
    unset($staff['password']);

    // فرمت‌دهی تاریخ
    $staff['created_at_formatted'] = date('Y/m/d H:i', strtotime($staff['created_at']));

    // اطلاعات اضافی اگر نیاز باشد
    $staff['additional_info'] = [
        'is_active_text' => $staff['is_active'] ? 'فعال' : 'غیرفعال',
        'status_class' => $staff['is_active'] ? 'active' : 'inactive'
    ];

    echo json_encode([
        'success' => true,
        'staff' => $staff
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'خطا در دریافت اطلاعات: ' . $e->getMessage()
    ]);
}
