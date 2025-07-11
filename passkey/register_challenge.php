<?php
// File: OldSSO/passkey/register_challenge.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__.'/passkey_handler.php';
session_start();

header('Content-Type: application/json');

// بررسی اینکه آیا کاربر لاگین کرده است
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'User not logged in']);
    exit;
}

use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialUserEntity;
use Webauthn\PublicKeyCredentialRpEntity;

// اطلاعات مربوط به برنامه شما (Relying Party)
$rpEntity = new PublicKeyCredentialRpEntity(
    'Tehran Containment SSO', // نام برنامه شما
    'sso.itziliya-dev.ir',    // دامنه شما
    null
);

// اطلاعات مربوط به کاربر
$userHandle = $_SESSION['user_type'] . '-' . $_SESSION['user_id'];
$userEntity = new PublicKeyCredentialUserEntity(
    $_SESSION['username'],
    $userHandle,
    $_SESSION['username'],
    null
);

// تولید چالش (Challenge) برای ثبت کلید جدید
$publicKeyCredentialCreationOptions = PublicKeyCredentialCreationOptions::create(
    $rpEntity,
    $userEntity,
    random_bytes(16) // چالش تصادفی
);

// ذخیره اطلاعات چالش در سشن برای تایید در مرحله بعد
$_SESSION['passkey_creation_options'] = $publicKeyCredentialCreationOptions;

echo json_encode($publicKeyCredentialCreationOptions);