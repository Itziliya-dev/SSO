<?php
require_once __DIR__.'/../../includes/config.php';
require_once __DIR__.'/../../includes/database.php';

header('Content-Type: application/json');

session_start();

if (!isset($_SESSION['is_owner']) || !$_SESSION['is_owner']) {
    echo json_encode(['success' => false, 'message' => 'دسترسی غیرمجاز']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'متد درخواست نامعتبر']);
    exit();
}

try {
    $action = $_POST['action'] ?? '';
    $request_id = $_POST['id'] ?? 0;
    
    if (!in_array($action, ['approve', 'reject', 'staff'])) {
        throw new Exception('عملیات نامعتبر');
    }
    
    $conn = getDbConnection();
    
    // دریافت اطلاعات درخواست
    $stmt = $conn->prepare("SELECT * FROM registration_requests WHERE id = ?");
    $stmt->bind_param("i", $request_id);
    $stmt->execute();
    $request = $stmt->get_result()->fetch_assoc();
    
    if (!$request) {
        throw new Exception('درخواست یافت نشد');
    }
    
    if ($action === 'approve') {
        // ایجاد کاربر جدید
        $stmt = $conn->prepare("INSERT INTO users 
            (username, password, email, phone, created_at, is_owner, created_by) 
            VALUES (?, ?, ?, ?, NOW(), 0, 'manual')");
        $stmt->bind_param("ssss", 
            $request['username'], 
            $request['password'], 
            $request['email'], 
            $request['phone']
        );
        $stmt->execute();
        
        // به‌روزرسانی وضعیت درخواست
        $stmt = $conn->prepare("UPDATE registration_requests SET status = 'approved' WHERE id = ?");
        $stmt->bind_param("i", $request_id);
        $stmt->execute();
        
    } elseif ($action === 'staff') {
        // انتقال اطلاعات به جدول staff-manage
        $stmt = $conn->prepare("INSERT INTO `staff-manage` 
            (username, password, email, phone, fullname, age, discord_id, steam_id, tracking_code, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("sssssisss", 
            $request['username'],
            $request['password'],
            $request['email'],
            $request['phone'],
            $request['fullname'],
            $request['age'],
            $request['discord_id'],
            $request['steam_id'],
            $request['tracking_code']
        );
        $stmt->execute();
        
        // به‌روزرسانی وضعیت درخواست
        $stmt = $conn->prepare("UPDATE registration_requests SET status = 'staff' WHERE id = ?");
        $stmt->bind_param("i", $request_id);
        $stmt->execute();
        
    } else {
        // رد درخواست
        $stmt = $conn->prepare("UPDATE registration_requests SET status = 'rejected' WHERE id = ?");
        $stmt->bind_param("i", $request_id);
        $stmt->execute();
    }
    
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}