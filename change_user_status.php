<?php
require_once __DIR__.'/includes/config.php';
require_once __DIR__.'/includes/auth_functions.php';

session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['is_owner']) || !$_SESSION['is_owner']) {
    echo json_encode(['success' => false, 'message' => 'دسترسی غیرمجاز']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'درخواست نامعتبر']);
    exit();
}

$action = $_POST['action'] ?? '';
$userId = (int)($_POST['user_id'] ?? 0);
$reason = $_POST['reason'] ?? '';

if ($userId === 0) {
    echo json_encode(['success' => false, 'message' => 'شناسه کاربر نامعتبر است']);
    exit();
}

$conn = getDbConnection();

if ($action === 'suspend') {
    $stmt = $conn->prepare("UPDATE users SET status = 'suspended', suspended_reason = ? WHERE id = ?");
    $stmt->bind_param("si", $reason, $userId);
} elseif ($action === 'activate') {
    $stmt = $conn->prepare("UPDATE users SET status = 'active', suspended_reason = NULL WHERE id = ?");
    $stmt->bind_param("i", $userId);
} else {
    echo json_encode(['success' => false, 'message' => 'عملیات نامعتبر']);
    exit();
}

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'خطا در انجام عملیات']);
}