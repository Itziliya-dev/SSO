<?php
header('Content-Type: application/json');
require_once __DIR__.'/../includes/config.php';
require_once __DIR__.'/../includes/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['token'])) {
    echo json_encode(['success' => false, 'message' => 'درخواست نامعتبر.']);
    exit();
}

$token = $_POST['token'];
$conn = getDbConnection();

// ۱. توکن را پیدا و اعتبارسنجی کن
$stmt = $conn->prepare("SELECT user_id, user_type, expires_at FROM sso_auth_tokens WHERE token = ? AND is_used = 0 LIMIT 1");
$stmt->bind_param("s", $token);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'توکن نامعتبر یا استفاده شده است.']);
    exit();
}

$token_data = $result->fetch_assoc();
if (new DateTime() > new DateTime($token_data['expires_at'])) {
    echo json_encode(['success' => false, 'message' => 'توکن منقضی شده است.']);
    exit();
}

// ۲. توکن را "استفاده شده" علامت بزن
$update_stmt = $conn->prepare("UPDATE sso_auth_tokens SET is_used = 1 WHERE token = ?");
$update_stmt->bind_param("s", $token);
$update_stmt->execute();

// ۳. اطلاعات کاربر را بر اساس نوع او واکشی کن
$user_id = $token_data['user_id'];
$user_type = $token_data['user_type'];
$user_info = null;

if ($user_type === 'staff') {
    $stmt = $conn->prepare("SELECT id, username FROM `staff-manage` WHERE id = ?");
} else { // user
    $stmt = $conn->prepare("SELECT id, username FROM `users` WHERE id = ?");
}
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_info = $stmt->get_result()->fetch_assoc();
$conn->close();

if (!$user_info) {
     echo json_encode(['success' => false, 'message' => 'کاربر پیدا نشد.']);
     exit();
}

// ۴. پاسخ نهایی را به صورت JSON برگردان
echo json_encode([
    'success' => true,
    'user_data' => [
        'id' => $user_info['id'],
        'username' => $user_info['username'],
        'type' => $user_type
    ]
]);
exit();