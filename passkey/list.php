<?php
// File: OldSSO/passkey/list.php

require_once __DIR__.'/../includes/config.php';
require_once __DIR__.'/../includes/database.php'; // <--- این خط را اضافه کنید اگر تابع در این فایل است

session_start();

header('Content-Type: application/json');

// بررسی لاگین بودن کاربر
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit;
}

$db = getDbConnection();
$userHandle = $_SESSION['user_type'] . '-' . $_SESSION['user_id'];

$encodedUserHandle = base64_encode($userHandle);

// کوئری برای پیدا کردن کلیدهای کاربر با استفاده از user_handle کد شده
$stmt = $db->prepare("SELECT credential_id, friendly_name, created_at FROM passkey_credentials WHERE user_handle = ? ORDER BY created_at DESC");
$stmt->bind_param('s', $encodedUserHandle);
$stmt->execute();
$result = $stmt->get_result();

$keys = [];
while ($row = $result->fetch_assoc()) {
    $keys[] = $row;
}

echo json_encode($keys);