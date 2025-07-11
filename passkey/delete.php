<?php
// File: OldSSO/passkey/delete.php

require_once __DIR__.'/../includes/database.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(400);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$credential_id = $data['credential_id'] ?? null;

if (!$credential_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Credential ID is required.']);
    exit;
}

$db = getDbConnection();
$userHandle = $_SESSION['user_type'] . '-' . $_SESSION['user_id'];

// نکته امنیتی مهم: اطمینان از اینکه کلید متعلق به کاربر لاگین کرده است
$stmt = $db->prepare("DELETE FROM passkey_credentials WHERE credential_id = ? AND user_handle = ?");
$stmt->bind_param('ss', $credential_id, $userHandle);

if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        echo json_encode(['success' => true]);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Key not found or you do not have permission to delete it.']);
    }
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Database error.']);
}