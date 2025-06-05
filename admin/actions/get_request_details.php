<?php
require_once __DIR__.'/../../includes/config.php';
require_once __DIR__.'/../../includes/database.php';
require_once __DIR__.'/../../includes/auth_functions.php';

session_start();

if (!isset($_SESSION['is_owner']) || !$_SESSION['is_owner']) {
    header('Location: login.php');
    exit();
}

header('Content-Type: application/json');

try {
    $request_id = $_GET['id'] ?? 0;
    
    $conn = getDbConnection();
    $stmt = $conn->prepare("SELECT * FROM registration_requests WHERE id = ?");
    $stmt->bind_param("i", $request_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('درخواست یافت نشد');
    }
    
    $request = $result->fetch_assoc();
    $request['created_at'] = date('Y/m/d H:i', strtotime($request['created_at']));
    
    echo json_encode([
        'success' => true,
        'request' => $request
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
