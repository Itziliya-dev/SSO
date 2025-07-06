<?php
// File: OldSSO/passkey/verify_and_delete.php

require_once __DIR__.'/passkey_handler.php';
session_start();

// All required "use" statements for the correct workflow
use Webauthn\PublicKeyCredentialLoader;
use Webauthn\AttestationStatement\AttestationObjectLoader;
use Webauthn\AttestationStatement\AttestationStatementSupportManager;
use Webauthn\AuthenticatorAssertionResponse;
use Webauthn\AuthenticatorAssertionResponseValidator;
use Webauthn\AuthenticationExtensions\ExtensionOutputCheckerHandler;

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !isset($_SESSION['passkey_auth_options'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid session state. Please start over.']);
    exit;
}

try {
    // ======== 1. PREPARE SERVICES ========
    $publicKeyCredentialSourceRepository = new PasskeyCredentialSourceRepository();

    $authenticatorAssertionResponseValidator = new AuthenticatorAssertionResponseValidator(
        $publicKeyCredentialSourceRepository,
        null, // Token Binding not supported
        new ExtensionOutputCheckerHandler()
    );

    $publicKeyCredentialLoader = new PublicKeyCredentialLoader(new AttestationObjectLoader(new AttestationStatementSupportManager()));

    // ======== 2. GET DATA ========
    $data = file_get_contents('php://input');
    $publicKeyCredentialRequestOptions = $_SESSION['passkey_auth_options'];
    $data_array = json_decode($data, true);
    $credential_id_to_delete_b64 = $data_array['credential_id_to_delete'] ?? null;

    if (!$credential_id_to_delete_b64) {
        throw new \RuntimeException('Credential ID to delete is missing.');
    }

    // ======== 3. LOAD AND VALIDATE ASSERTION ========
    $publicKeyCredential = $publicKeyCredentialLoader->load($data);
    $authenticatorAssertionResponse = $publicKeyCredential->getResponse();

    if (!$authenticatorAssertionResponse instanceof AuthenticatorAssertionResponse) {
        throw new \RuntimeException('Invalid assertion response');
    }

    // Re-create the user handle from the trusted session to pass to the validator
    $userHandle = $_SESSION['user_type'] . '-' . $_SESSION['user_id'];

    // **THE FINAL FIX IS HERE:** Add the 5th argument ($userHandle) to the check() method call.
    $publicKeyCredentialSource = $authenticatorAssertionResponseValidator->check(
        $publicKeyCredential->rawId,
        $authenticatorAssertionResponse,
        $publicKeyCredentialRequestOptions,
        'https://' . $_SERVER['HTTP_HOST'],
        $userHandle // The missing 5th argument
    );

    // ======== 4. IF AUTHENTICATION IS SUCCESSFUL, DELETE THE KEY ========
    $db = getDbConnection();
    // For the DELETE query, we still use the plain user handle, as it's not encoded in the DB.
    $stmt = $db->prepare("DELETE FROM passkey_credentials WHERE credential_id = ? AND user_handle = ?");
    $stmt->bind_param('ss', $credential_id_to_delete_b64, $userHandle);
    $stmt->execute();

    if ($stmt->affected_rows === 0) {
        throw new \RuntimeException('Could not delete key. It might have been already deleted or does not belong to you.');
    }

    // ======== 5. CLEANUP AND RESPOND ========
    unset($_SESSION['passkey_auth_options']);
    echo json_encode(['success' => true]);

} catch (Throwable $e) {
    error_log('Passkey Deletion Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine());
    echo json_encode(['error' => $e->getMessage()]);
}