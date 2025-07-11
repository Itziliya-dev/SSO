<?php
// File: OldSSO/passkey/passkey_handler.php (Definitive Final Version)

require_once __DIR__.'/../vendor/autoload.php';
require_once __DIR__.'/../includes/config.php';
require_once __DIR__.'/../includes/database.php'; // <--- این خط را اضافه کنید اگر تابع در این فایل است


use Webauthn\PublicKeyCredentialSource;
use Webauthn\PublicKeyCredentialSourceRepository;
use Webauthn\PublicKeyCredentialUserEntity;

class PasskeyCredentialSourceRepository implements PublicKeyCredentialSourceRepository
{
    private $db;
    private $friendlyNameForNextSave;

    public function __construct()
    {
        $this->db = getDbConnection();
    }

    private function base64url_encode(string $data): string { return rtrim(strtr(base64_encode($data), '+/', '-_'), '='); }
    private function base64url_decode(string $data): string { return base64_decode(strtr($data, '-_', '+/')); }

    public function setFriendlyNameForNextSave(string $name): void { $this->friendlyNameForNextSave = $name; }

    public function saveCredentialSource(PublicKeyCredentialSource $publicKeyCredentialSource): void
    {
        // THE FINAL FIX: Use jsonSerialize() to get all data in a reliable array format.
        $data = $publicKeyCredentialSource->jsonSerialize();
        
        $stmt_insert = $this->db->prepare(
            'INSERT INTO passkey_credentials (user_handle, credential_id, public_key, attestation_type, transports, aaguid, sign_count, friendly_name) VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );

        $transports_json = json_encode($data['transports']);
        $encoded_credential_id = $this->base64url_encode($data['publicKeyCredentialId']);
        $friendlyName = $this->friendlyNameForNextSave ?? 'کلید عبور'; 

        // Get the user handle from the serialized data array. This is the correct and reliable way.
        $userHandle = $data['userHandle'];

        $stmt_insert->bind_param(
            'ssssssis',
            $userHandle,
            $encoded_credential_id,
            $data['credentialPublicKey'],
            $data['attestationType'],
            $transports_json,
            $data['aaguid'],
            $data['counter'],
            $friendlyName
        );
        $stmt_insert->execute();
    }
    
    public function findOneByCredentialId(string $publicKeyCredentialId): ?PublicKeyCredentialSource
    {
        $encoded_credential_id = $this->base64url_encode($publicKeyCredentialId);
        $stmt = $this->db->prepare('SELECT * FROM passkey_credentials WHERE credential_id = ?');
        $stmt->bind_param('s', $encoded_credential_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows === 0) return null;
        $data = $result->fetch_assoc();
        return PublicKeyCredentialSource::createFromArray($this->decodeData($data));
    }

    public function findAllForUserEntity(PublicKeyCredentialUserEntity $publicKeyCredentialUserEntity): array
    {
        $userHandle = $publicKeyCredentialUserEntity->getId();
        $stmt = $this->db->prepare("SELECT * FROM passkey_credentials WHERE user_handle = ?");
        $stmt->bind_param('s', $userHandle);
        $stmt->execute();
        $result = $stmt->get_result();
        $sources = [];
        while ($data = $result->fetch_assoc()) {
            $sources[] = PublicKeyCredentialSource::createFromArray($this->decodeData($data));
        }
        return $sources;
    }

    private function decodeData(array $data): array
    {
        $data['publicKeyCredentialId'] = $this->base64url_decode($data['credential_id']);
        $data['credentialPublicKey'] = $data['public_key'];
        $data['counter'] = (int)$data['sign_count'];
        $data['transports'] = json_decode($data['transports'], true) ?? [];
        $data['userHandle'] = $data['user_handle'];
        return $data;
    }
}