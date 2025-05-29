<?php
require_once __DIR__.'/includes/config.php';
require_once __DIR__.'/includes/auth_functions.php';
require_once __DIR__.'/includes/app_functions.php';

session_start();

if (!isset($_SESSION['is_owner']) || !$_SESSION['is_owner']) {
    header('Location: login.php');
    exit();
}

header('Content-Type: application/json');

if (!isset($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'شناسه کاربر ارسال نشده است']);
    exit();
}

$userId = (int)$_GET['id'];

try {
    $conn = getDbConnection();
    $stmt = $conn->prepare("SELECT * FROM registration_requests WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'کاربر یافت نشد']);
        exit();
    }
    
    $user = $result->fetch_assoc();
    echo json_encode(['success' => true, 'user' => $user]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'خطا در دریافت اطلاعات کاربر', 'error' => $e->getMessage()]);
}