<?php

declare(strict_types=1);

namespace Taleastromex\KeepzApiSdk\Crypto;

use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Crypt\RSA;

class Encryptor
{
    /** @var string */
    private $publicKey;

    /**
     * @param string $publicKey
     */
    public function __construct($publicKey)
    {
        $this->publicKey = $publicKey;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function encrypt(array $data): array
    {
        $aesKey = random_bytes(32);
        $iv = random_bytes(16);

        $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($json === false) {
            throw new \RuntimeException('Failed to JSON-encode payload: ' . json_last_error_msg());
        }

        $encryptedData = openssl_encrypt(
            $json,
            'AES-256-CBC',
            $aesKey,
            OPENSSL_RAW_DATA,
            $iv
        );

        if ($encryptedData === false) {
            throw new \RuntimeException('AES encrypt error: ' . openssl_error_string());
        }

        $rsaPayload = base64_encode($aesKey) . '.' . base64_encode($iv);

        /** @var RSA $publicKey */
        $publicKey = PublicKeyLoader::load($this->publicKey);

        $encryptedKeys = $publicKey
            ->withPadding(RSA::ENCRYPTION_OAEP)
            ->withHash('sha256')
            ->withMGFHash('sha256')
            ->encrypt($rsaPayload);

        if ($encryptedKeys === false) {
            throw new \RuntimeException('RSA encrypt failed');
        }

        return [
            'encryptedData' => base64_encode($encryptedData),
            'encryptedKeys' => base64_encode($encryptedKeys),
            'aes' => true,
        ];
    }
}
