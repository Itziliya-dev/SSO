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
$reason = $_POST['reason'] ?? '';

if (empty($staff_id) || !is_numeric($staff_id)) {
    echo json_encode(['success' => false, 'message' => 'شناسه استاف نامعتبر است']);
    exit();
}

try {
    $conn = getDbConnection();
    
    // ابتدا اطلاعات استاف را برای بایگانی ذخیره می‌کنیم
    $stmt = $conn->prepare("INSERT INTO deleted_staff 
        SELECT *, ?, NOW() FROM `staff-manage` WHERE id = ?");
    $stmt->bind_param("si", $reason, $staff_id);
    $stmt->execute();
    
    // سپس استاف را حذف می‌کنیم
    $stmt = $conn->prepare("DELETE FROM `staff-manage` WHERE id = ?");
    $stmt->bind_param("i", $staff_id);
    $stmt->execute();
    
    echo json_encode(['success' => true, 'message' => 'استاف با موفقیت حذف شد']);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'خطا در حذف استاف: ' . $e->getMessage()
    ]);
}