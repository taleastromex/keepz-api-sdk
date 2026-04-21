<?php

declare(strict_types=1);

namespace KeepzSdk\Crypto;

use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Crypt\RSA;

class Decryptor
{
    /** @var string */
    private $privateKey;

    /**
     * @param string $privateKey PEM-encoded RSA private key
     */
    public function __construct(string $privateKey)
    {
        $this->privateKey = $privateKey;
    }

    /**
     * Decrypts an AES-encrypted API response envelope.
     *
     * Expects a response that has already been validated to contain `aes: true`.
     * Throws if the envelope is missing required fields or decryption fails.
     *
     * @param array<string, mixed> $response
     * @return array<string, mixed>
     */
    public function decrypt(array $response): array
    {
        $encryptedData = $response['encryptedData'] ?? null;
        $encryptedKeys = $response['encryptedKeys'] ?? null;

        if (!is_string($encryptedData) || !is_string($encryptedKeys)) {
            throw new \InvalidArgumentException('Response is missing encryptedData or encryptedKeys');
        }

        /** @var RSA $privateKey */
        $privateKey = PublicKeyLoader::load($this->privateKey);

        try {
            $rsaPayload = $privateKey
                ->withPadding(RSA::ENCRYPTION_OAEP)
                ->withHash('sha256')
                ->withMGFHash('sha256')
                ->decrypt(base64_decode($encryptedKeys));
        } catch (\Throwable $e) {
            throw new \RuntimeException('RSA decryption of encryptedKeys failed: ' . $e->getMessage(), 0, $e);
        }

        if ($rsaPayload === false) {
            throw new \RuntimeException('RSA decryption of encryptedKeys failed');
        }

        [$aesKeyB64, $ivB64] = explode('.', $rsaPayload, 2);
        $aesKey = base64_decode($aesKeyB64);
        $iv = base64_decode($ivB64);

        $plaintext = openssl_decrypt(
            base64_decode($encryptedData),
            'AES-256-CBC',
            $aesKey,
            OPENSSL_RAW_DATA,
            $iv
        );

        if ($plaintext === false) {
            throw new \RuntimeException('AES decryption of encryptedData failed: ' . openssl_error_string());
        }

        $decoded = json_decode($plaintext, true);

        if (!is_array($decoded)) {
            throw new \RuntimeException('JSON decode failed after decryption: ' . json_last_error_msg());
        }

        return $decoded;
    }
}
