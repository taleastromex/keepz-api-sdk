<?php

declare(strict_types=1);

namespace KeepzSdk\Tests\Crypto;

use KeepzSdk\Crypto\Decryptor;
use KeepzSdk\Crypto\Encryptor;
use PHPUnit\Framework\TestCase;

class DecryptorTest extends TestCase
{
    /** @var string */
    private static $publicKey;

    /** @var string */
    private static $privateKey;

    public static function setUpBeforeClass(): void
    {
        $key = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        openssl_pkey_export($key, $privateKeyPem);
        $details = openssl_pkey_get_details($key);

        self::$privateKey = $privateKeyPem;
        self::$publicKey  = $details['key'];
    }

    private function makeEncryptor(): Encryptor
    {
        return new Encryptor(self::$publicKey);
    }

    private function makeDecryptor(): Decryptor
    {
        return new Decryptor(self::$privateKey);
    }

    // -------------------------------------------------------------------------
    // Round-trip
    // -------------------------------------------------------------------------

    /**
     * @dataProvider payloadProvider
     * @param array<string, mixed> $payload
     */
    public function testDecryptRevertsEncryption(array $payload): void
    {
        $envelope = $this->makeEncryptor()->encrypt($payload);
        $result   = $this->makeDecryptor()->decrypt($envelope);

        $this->assertSame($payload, $result);
    }

    // -------------------------------------------------------------------------
    // Guard — missing required fields
    // -------------------------------------------------------------------------

    public function testDecryptThrowsWhenEncryptedDataMissing(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->makeDecryptor()->decrypt(['encryptedKeys' => 'def', 'aes' => true]);
    }

    public function testDecryptThrowsWhenEncryptedKeysMissing(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->makeDecryptor()->decrypt(['encryptedData' => 'abc', 'aes' => true]);
    }

    // -------------------------------------------------------------------------
    // Error handling — bad crypto material
    // -------------------------------------------------------------------------

    public function testDecryptThrowsOnInvalidEncryptedKeys(): void
    {
        $this->expectException(\RuntimeException::class);

        $this->makeDecryptor()->decrypt([
            'encryptedData' => base64_encode('anything'),
            'encryptedKeys' => base64_encode('not-valid-rsa-ciphertext'),
            'aes'           => true,
        ]);
    }

    public function testDecryptThrowsWithInvalidPrivateKey(): void
    {
        $this->expectException(\Throwable::class);

        $decryptor = new Decryptor('not-a-valid-key');
        $decryptor->decrypt([
            'encryptedData' => base64_encode('x'),
            'encryptedKeys' => base64_encode('x'),
            'aes'           => true,
        ]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    public static function payloadProvider(): array
    {
        return [
            'minimal order' => [[
                'amount'            => 100,
                'receiverId'        => '90434fa9-46df-4c44-a4d1-da742ac815da',
                'receiverType'      => 'BRANCH',
                'integratorId'      => 'ce3a3476-a542-4e5d-a957-72fcd0e35d2c',
                'integratorOrderId' => '3fa85f64-5717-4562-b3fc-2c963f66afa6',
            ]],
            'unicode and slashes' => [[
                'description' => 'გადახდა / payment',
                'url'         => 'https://example.com/path',
            ]],
            'nested data' => [[
                'amount'       => 50,
                'splitDetails' => [
                    ['receiverType' => 'BRANCH', 'receiverIdentifier' => 'uuid-1', 'amount' => 30],
                    ['receiverType' => 'IBAN',   'receiverIdentifier' => 'GE34BG0000001234567890', 'amount' => 20],
                ],
            ]],
            'empty array' => [[]],
        ];
    }
}
