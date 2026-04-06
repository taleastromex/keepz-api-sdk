<?php

declare(strict_types=1);

namespace KeepzSdk\Tests\Crypto;

use KeepzSdk\Crypto\Encryptor;
use PHPUnit\Framework\TestCase;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Crypt\RSA;

class EncryptorTest extends TestCase
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
        self::$publicKey = $details['key'];
    }

    private function makeEncryptor(): Encryptor
    {
        return new Encryptor(self::$publicKey);
    }

    // -------------------------------------------------------------------------
    // Structure
    // -------------------------------------------------------------------------

    public function testEncryptReturnsThreeKeys(): void
    {
        $result = $this->makeEncryptor()->encrypt(['amount' => 100]);

        $this->assertArrayHasKey('encryptedData', $result);
        $this->assertArrayHasKey('encryptedKeys', $result);
        $this->assertArrayHasKey('aes', $result);
    }

    public function testAesFlagIsTrue(): void
    {
        $result = $this->makeEncryptor()->encrypt(['amount' => 100]);

        $this->assertTrue($result['aes']);
    }

    // -------------------------------------------------------------------------
    // Base64 validity
    // -------------------------------------------------------------------------

    public function testEncryptedDataIsValidBase64(): void
    {
        $result = $this->makeEncryptor()->encrypt(['amount' => 100]);

        $decoded = base64_decode($result['encryptedData'], true);
        $this->assertNotFalse($decoded, 'encryptedData is not valid base64');
        $this->assertNotEmpty($decoded);
    }

    public function testEncryptedKeysIsValidBase64(): void
    {
        $result = $this->makeEncryptor()->encrypt(['amount' => 100]);

        $decoded = base64_decode($result['encryptedKeys'], true);
        $this->assertNotFalse($decoded, 'encryptedKeys is not valid base64');
        $this->assertNotEmpty($decoded);
    }

    // -------------------------------------------------------------------------
    // Randomness — same input must not produce the same ciphertext
    // -------------------------------------------------------------------------

    public function testTwoEncryptionsOfSameDataProduceDifferentCiphertext(): void
    {
        $encryptor = $this->makeEncryptor();
        $data = ['amount' => 100, 'currency' => 'GEL'];

        $first = $encryptor->encrypt($data);
        $second = $encryptor->encrypt($data);

        $this->assertNotSame($first['encryptedData'], $second['encryptedData']);
        $this->assertNotSame($first['encryptedKeys'], $second['encryptedKeys']);
    }

    // -------------------------------------------------------------------------
    // Round-trip — decrypt and verify the original payload is recovered
    // -------------------------------------------------------------------------

    /**
     * @dataProvider payloadProvider
     * @param array<string, mixed> $payload
     */
    public function testRoundTripDecryptionRecoverOriginalPayload(array $payload): void
    {
        $result = $this->makeEncryptor()->encrypt($payload);

        // 1. Decrypt encryptedKeys with the RSA private key to recover AES key+IV
        /** @var RSA $privateKey */
        $privateKey = PublicKeyLoader::load(self::$privateKey);
        $rsaPayload = $privateKey
            ->withPadding(RSA::ENCRYPTION_OAEP)
            ->withHash('sha256')
            ->withMGFHash('sha256')
            ->decrypt(base64_decode($result['encryptedKeys']));

        $this->assertNotFalse($rsaPayload, 'RSA decryption of encryptedKeys failed');

        [$aesKeyB64, $ivB64] = explode('.', $rsaPayload, 2);
        $aesKey = base64_decode($aesKeyB64);
        $iv = base64_decode($ivB64);

        // 2. Decrypt encryptedData with the recovered AES key+IV
        $plaintext = openssl_decrypt(
            base64_decode($result['encryptedData']),
            'AES-256-CBC',
            $aesKey,
            OPENSSL_RAW_DATA,
            $iv
        );

        $this->assertNotFalse($plaintext, 'AES decryption of encryptedData failed');
        $this->assertSame($payload, json_decode($plaintext, true));
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    public static function payloadProvider(): array
    {
        return [
            'minimal order' => [[
                'amount' => 100,
                'receiverId' => '90434fa9-46df-4c44-a4d1-da742ac815da',
                'receiverType' => 'BRANCH',
                'integratorId' => 'ce3a3476-a542-4e5d-a957-72fcd0e35d2c',
                'integratorOrderId' => '3fa85f64-5717-4562-b3fc-2c963f66afa6',
            ]],
            'unicode and slashes' => [[
                'description' => 'გადახდა / payment',
                'url' => 'https://example.com/path',
            ]],
            'nested data' => [[
                'amount' => 50,
                'splitDetails' => [
                    ['receiverType' => 'BRANCH', 'receiverIdentifier' => 'uuid-1', 'amount' => 30],
                    ['receiverType' => 'IBAN',   'receiverIdentifier' => 'GE34BG0000001234567890', 'amount' => 20],
                ],
            ]],
            'empty array' => [[]],
        ];
    }

    // -------------------------------------------------------------------------
    // Edge cases
    // -------------------------------------------------------------------------

    public function testEncryptWithInvalidPublicKeyThrows(): void
    {
        $this->expectException(\Throwable::class);

        $encryptor = new Encryptor('not-a-valid-key');
        $encryptor->encrypt(['amount' => 1]);
    }
}
