<?php
// File: OldSSO/passkey/register_verify.php (The actual final version)

require_once __DIR__.'/passkey_handler.php';
session_start();

use Webauthn\PublicKeyCredentialLoader;
use Webauthn\AttestationStatement\AttestationObjectLoader;
use Webauthn\AttestationStatement\AttestationStatementSupportManager;
use Webauthn\AttestationStatement\NoneAttestationStatementSupport;
use Webauthn\AuthenticationExtensions\ExtensionOutputCheckerHandler;
use Webauthn\AuthenticatorAttestationResponse;
use Webauthn\AuthenticatorAttestationResponseValidator;
use Webauthn\PublicKeyCredentialSource;

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !isset($_SESSION['passkey_creation_options'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid session state. Please start over.']);
    exit;
}

try {
    $attestationStatementSupportManager = new AttestationStatementSupportManager();
    $attestationStatementSupportManager->add(new NoneAttestationStatementSupport());
    $publicKeyCredentialSourceRepository = new PasskeyCredentialSourceRepository();
    $authenticatorAttestationResponseValidator = new AuthenticatorAttestationResponseValidator($attestationStatementSupportManager, $publicKeyCredentialSourceRepository, null, new ExtensionOutputCheckerHandler());
    $attestationObjectLoader = new AttestationObjectLoader($attestationStatementSupportManager);
    $publicKeyCredentialLoader = new PublicKeyCredentialLoader($attestationObjectLoader);
    $data = file_get_contents('php://input');
    $publicKeyCredentialCreationOptions = $_SESSION['passkey_creation_options'];
    $publicKeyCredential = $publicKeyCredentialLoader->load($data);
    $authenticatorAttestationResponse = $publicKeyCredential->getResponse();
    if (!$authenticatorAttestationResponse instanceof AuthenticatorAttestationResponse) { throw new \RuntimeException('Invalid attestation response'); }
    $publicKeyCredentialSource = $authenticatorAttestationResponseValidator->check($authenticatorAttestationResponse, $publicKeyCredentialCreationOptions, 'https://' . $_SERVER['HTTP_HOST']);
    $userHandleFromServer = $_SESSION['user_type'] . '-' . $_SESSION['user_id'];
    $publicKeyCredentialSource->setUserHandle($userHandleFromServer);
    $data_array = json_decode($data, true);
    $friendlyName = $data_array['friendly_name'] ?? 'کلید عبور جدید';
    $credentialRepository = new PasskeyCredentialSourceRepository();
    $credentialRepository->setFriendlyNameForNextSave($friendlyName);
    $credentialRepository->saveCredentialSource($publicKeyCredentialSource);
    unset($_SESSION['passkey_creation_options']);
    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    error_log('Passkey Registration Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine());
    echo json_encode(['error' => $e->getMessage()]);
}