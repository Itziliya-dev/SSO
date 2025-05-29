<?php
require_once __DIR__.'/includes/config.php';
require_once __DIR__.'/includes/auth_functions.php';

header('Content-Type: application/json');

session_start();

if (!isset($_SESSION['is_owner']) || !$_SESSION['is_owner']) {
    echo json_encode(['success' => false, 'message' => 'دسترسی غیرمجاز']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_POST['user_id'] ?? null;
    $new_password = $_POST['new_password'] ?? null;
    
    if (empty($user_id)) {
        echo json_encode(['success' => false, 'message' => 'شناسه کاربر نامعتبر است']);
        exit();
    }
    
    if (empty($new_password)) {
        echo json_encode(['success' => false, 'message' => 'رمز عبور جدید را وارد کنید']);
        exit();
    }
    
    $conn = getDbConnection();
    
    // بررسی وجود کاربر
    $check_stmt = $conn->prepare("SELECT id FROM users WHERE id = ?");
    $check_stmt->bind_param("i", $user_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'کاربر یافت نشد']);
        exit();
    }
    
    // به‌روزرسانی رمز عبور
    $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);
    $update_stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
    $update_stmt->bind_param("si", $hashed_password, $user_id);
    
    if ($update_stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'رمز عبور با موفقیت تغییر یافت']);
    } else {
        echo json_encode([
            'success' => false, 
            'message' => 'خطا در به‌روزرسانی رمز عبور',
            'error' => $conn->error
        ]);
    }
    
    $update_stmt->close();
    $conn->close();
} else {
    echo json_encode(['success' => false, 'message' => 'متد درخواست نامعتبر است']);
}