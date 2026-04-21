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
     * If the response does not carry the `aes` flag it is returned as-is,
     * so error responses (no encryption) pass through transparently.
     *
     * @param array<string, mixed> $response
     * @return array<string, mixed>
     */
    public function decrypt(array $response): array
    {
        if (empty($response['aes'])
            || !isset($response['encryptedData'], $response['encryptedKeys'])
        ) {
            return $response;
        }

        /** @var RSA $privateKey */
        $privateKey = PublicKeyLoader::load($this->privateKey);

        try {
            $rsaPayload = $privateKey
                ->withPadding(RSA::ENCRYPTION_OAEP)
                ->withHash('sha256')
                ->withMGFHash('sha256')
                ->decrypt(base64_decode((string) $response['encryptedKeys']));
        } catch (\Throwable $e) {
            throw new \RuntimeException('RSA decryption of encryptedKeys failed: ' . $e->getMessage(), 0, $e);
        }

        if ($rsaPayload === false) {
            throw new \RuntimeException('RSA decryption of encryptedKeys failed');
        }

        [$aesKeyB64, $ivB64] = explode('.', $rsaPayload, 2);
        $aesKey = base64_decode($aesKeyB64);
        $iv     = base64_decode($ivB64);

        $plaintext = openssl_decrypt(
            base64_decode((string) $response['encryptedData']),
            'AES-256-CBC',
            $aesKey,
            OPENSSL_RAW_DATA,
            $iv
        );

        if ($plaintext === false) {
            throw new \RuntimeException('AES decryption of encryptedData failed: ' . openssl_error_string());
        }

        $decoded = json_decode($plaintext, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('JSON decode failed after decryption: ' . json_last_error_msg());
        }

        return $decoded;
    }
}
