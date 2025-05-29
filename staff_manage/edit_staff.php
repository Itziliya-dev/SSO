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
$fullname = $_POST['fullname'] ?? '';
$username = $_POST['username'] ?? '';
$email = $_POST['email'] ?? '';
$phone = $_POST['phone'] ?? '';
$discord_id = $_POST['discord_id'] ?? '';
$steam_id = $_POST['steam_id'] ?? '';
$is_active = isset($_POST['is_active']) ? 1 : 0;

// اعتبارسنجی اولیه
if (empty($staff_id) || !is_numeric($staff_id)) {
    echo json_encode(['success' => false, 'message' => 'شناسه استف نامعتبر است']);
    exit();
}

if (empty($fullname) || empty($username)) {
    echo json_encode(['success' => false, 'message' => 'نام کامل و نام کاربری الزامی است']);
    exit();
}

try {
    $conn = getDbConnection();
    
    // بررسی تکراری نبودن نام کاربری
    $stmt = $conn->prepare("SELECT id FROM `staff-manage` WHERE username = ? AND id != ?");
    $stmt->bind_param("si", $username, $staff_id);
    $stmt->execute();
    
    if ($stmt->get_result()->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'نام کاربری قبلا استفاده شده است']);
        exit();
    }
    
    // آپدیت اطلاعات استف
    $stmt = $conn->prepare("UPDATE `staff-manage` SET 
        fullname = ?,
        username = ?,
        email = ?,
        phone = ?,
        discord_id = ?,
        steam_id = ?,
        is_active = ?,
        updated_at = NOW()
        WHERE id = ?");
    
    $stmt->bind_param("ssssssii", 
        $fullname,
        $username,
        $email,
        $phone,
        $discord_id,
        $steam_id,
        $is_active,
        $staff_id
    );
    
    $stmt->execute();
    
    if ($stmt->affected_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'اطلاعات استف با موفقیت به‌روزرسانی شد']);
    } else {
        echo json_encode(['success' => false, 'message' => 'هیچ تغییری اعمال نشد']);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'خطا در به‌روزرسانی اطلاعات: ' . $e->getMessage()
    ]);
}