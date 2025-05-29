<?php
require_once __DIR__.'/includes/config.php';
require_once __DIR__.'/includes/auth_functions.php';

session_start();

// بررسی مالک بودن کاربر
if (!isset($_SESSION['is_owner']) || !$_SESSION['is_owner']) {
    header('HTTP/1.1 403 Forbidden');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_POST['user_id'] ?? 0;
    
    $conn = getDbConnection();
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND is_owner = 0");
    $stmt->bind_param("i", $user_id);
    $result = $stmt->execute();
    
    header('Content-Type: application/json');
    echo json_encode(['success' => $result]);
    exit();
}

header('HTTP/1.1 400 Bad Request');
?>