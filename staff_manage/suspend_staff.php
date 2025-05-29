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

if (empty($staff_id) || !is_numeric($staff_id)) {
    echo json_encode(['success' => false, 'message' => 'شناسه استف نامعتبر است']);
    exit();
}

try {
    $conn = getDbConnection();
    
    $stmt = $conn->prepare("UPDATE `staff-manage` SET 
        is_active = 0,
        updated_at = NOW()
        WHERE id = ?");
    
    $stmt->bind_param("i", $staff_id);
    $stmt->execute();
    
    echo json_encode(['success' => true, 'message' => 'استف با موفقیت غیرفعال شد']);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'خطا در غیرفعال کردن استف: ' . $e->getMessage()
    ]);
}