<?php

declare(strict_types=1);

namespace KeepzSdk\Crypto;

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
     * @param array $data
     * @return array
     */
    public function encrypt(array $data)
    {
        $aesKey = random_bytes(32);
        $iv = random_bytes(16);

        // 1. AES encrypt payload
        $encryptedData = openssl_encrypt(
            json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'AES-256-CBC',
            $aesKey,
            OPENSSL_RAW_DATA,
            $iv
        );

        if ($encryptedData === false) {
            throw new \Exception('AES encrypt error: ' . openssl_error_string());
        }

        // 2. Prepare RSA payload (IMPORTANT: key.iv as RAW string)
        $rsaPayload = base64_encode($aesKey) . '.' . base64_encode($iv);

        // 3. Load public key via phpseclib
        $publicKey = PublicKeyLoader::load($this->publicKey);

        $encryptedKeys = $publicKey
            ->withPadding(RSA::ENCRYPTION_OAEP)
            ->withHash('sha256')
            ->withMGFHash('sha256')
            ->encrypt($rsaPayload);

        if ($encryptedKeys === false) {
            throw new \Exception('RSA encrypt failed');
        }

        // 4. Return payload
        return [
            'encryptedData' => base64_encode($encryptedData),
            'encryptedKeys' => base64_encode($encryptedKeys),
            'aes' => true,
        ];
    }
}