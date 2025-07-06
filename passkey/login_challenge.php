<?php
// File: OldSSO/passkey/login_challenge.php (Final Harmonized Version)

require_once __DIR__.'/passkey_handler.php';
session_start();

use Webauthn\PublicKeyCredentialRequestOptions;
use Webauthn\PublicKeyCredentialDescriptor;

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'User not logged in']);
    exit;
}

$userHandle = $_SESSION['user_type'] . '-' . $_SESSION['user_id']; // Plain text user handle
$db = getDbConnection();
$stmt = $db->prepare("SELECT credential_id, transports FROM passkey_credentials WHERE user_handle = ?");
$stmt->bind_param('s', $userHandle);
$stmt->execute();
$result = $stmt->get_result();

$allowedCredentials = [];
while ($row = $result->fetch_assoc()) {
    $allowedCredentials[] = new PublicKeyCredentialDescriptor('public-key', base64_decode(strtr($row['credential_id'], '-_', '+/')), json_decode($row['transports'], true) ?? []);
}

$publicKeyCredentialRequestOptions = new PublicKeyCredentialRequestOptions(random_bytes(32), 60000, $allowedCredentials, 'required');
$publicKeyCredentialRequestOptions->rpId = 'sso.itziliya-dev.ir';

$_SESSION['passkey_auth_options'] = $publicKeyCredentialRequestOptions;
echo json_encode($publicKeyCredentialRequestOptions);