<?php

require_once __DIR__.'/config.php';
require_once __DIR__.'/auth_functions.php';

session_start();

if (!isset($_SESSION['is_owner']) || !$_SESSION['is_owner']) {
    header('HTTP/1.1 403 Forbidden');
    exit();
}

$conn = getDbConnection();
$result = $conn->query("
    SELECT COUNT(*) as count FROM login_attempts 
    WHERE viewed = 0 AND attempt_time > (NOW() - INTERVAL 24 HOUR)
");
$data = $result->fetch_assoc();

header('Content-Type: application/json');
echo json_encode([
    'count' => $data['count'],
    'last_check' => time()
]);
