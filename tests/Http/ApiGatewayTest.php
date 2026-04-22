<?php

declare(strict_types=1);

namespace Taleastromex\KeepzApiSdk\Tests\Http;

use Taleastromex\KeepzApiSdk\Crypto\Decryptor;
use Taleastromex\KeepzApiSdk\Crypto\Encryptor;
use Taleastromex\KeepzApiSdk\Exceptions\ApiException;
use Taleastromex\KeepzApiSdk\Http\ApiGateway;
use Taleastromex\KeepzApiSdk\Http\HttpClientInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ApiGatewayTest extends TestCase
{
    private const BASE_URL   = 'https://gateway.dev.keepz.me/ecommerce-service';
    private const IDENTIFIER = 'test-integrator-id';

    /** @var HttpClientInterface&MockObject */
    private $http;

    /** @var Encryptor&MockObject */
    private $encryptor;

    /** @var Decryptor&MockObject */
    private $decryptor;

    protected function setUp(): void
    {
        $this->http      = $this->createMock(HttpClientInterface::class);
        $this->encryptor = $this->createMock(Encryptor::class);
        $this->decryptor = $this->createMock(Decryptor::class);

        $this->encryptor->method('encrypt')->willReturn($this->fakeEnvelope());
        $this->http->method('post')->willReturn($this->fakeEncryptedApiResponse());
        $this->http->method('get')->willReturn($this->fakeEncryptedApiResponse());
        $this->http->method('delete')->willReturn($this->fakeEncryptedApiResponse());
        $this->decryptor->method('decrypt')->willReturn($this->fakePlainData());
    }

    private function makeGateway(): ApiGateway
    {
        return new ApiGateway(
            self::BASE_URL,
            self::IDENTIFIER,
            $this->http,
            $this->encryptor,
            $this->decryptor
        );
    }

    // -------------------------------------------------------------------------
    // post()
    // -------------------------------------------------------------------------

    public function testPostBuildsCorrectUrl(): void
    {
        /** @var HttpClientInterface&MockObject $http */
        $http = $this->createMock(HttpClientInterface::class);
        $http->expects($this->once())
            ->method('post')
            ->with(self::BASE_URL . '/api/integrator/order', $this->anything())
            ->willReturn($this->fakeEncryptedApiResponse());

        $this->http = $http;
        $this->makeGateway()->post('/api/integrator/order', []);
    }

    public function testPostMergesIdentifierWithEncryptedEnvelope(): void
    {
        $envelope = $this->fakeEnvelope();
        $this->encryptor->method('encrypt')->willReturn($envelope);

        /** @var HttpClientInterface&MockObject $http */
        $http = $this->createMock(HttpClientInterface::class);
        $http->expects($this->once())
            ->method('post')
            ->with($this->anything(), [
                'identifier'    => self::IDENTIFIER,
                'encryptedData' => $envelope['encryptedData'],
                'encryptedKeys' => $envelope['encryptedKeys'],
                'aes'           => true,
            ])
            ->willReturn($this->fakeEncryptedApiResponse());

        $this->http = $http;
        $this->makeGateway()->post('/api/integrator/order', ['amount' => 100]);
    }

    public function testPostEncryptsPayloadBeforeSending(): void
    {
        $payload = ['amount' => 100];

        /** @var Encryptor&MockObject $encryptor */
        $encryptor = $this->createMock(Encryptor::class);
        $encryptor->expects($this->once())
            ->method('encrypt')
            ->with($payload)
            ->willReturn($this->fakeEnvelope());

        $this->encryptor = $encryptor;
        $this->makeGateway()->post('/api/integrator/order', $payload);
    }

    public function testPostReturnsDecryptedResponse(): void
    {
        $plainData = ['integratorOrderId' => 'uuid', 'urlForQR' => 'https://x.com'];

        $this->decryptor = $this->createMock(Decryptor::class);
        $this->decryptor->method('decrypt')->willReturn($plainData);

        $result = $this->makeGateway()->post('/api/integrator/order', []);

        $this->assertSame($plainData, $result);
    }

    public function testPostThrowsApiExceptionOnErrorResponse(): void
    {
        $this->http = $this->createMock(HttpClientInterface::class);
        $this->http->method('post')->willReturn(['message' => 'Forbidden', 'statusCode' => 403]);

        $this->expectException(ApiException::class);
        $this->expectExceptionCode(403);

        $this->makeGateway()->post('/api/integrator/order', []);
    }

    public function testPostDoesNotCallDecryptorOnErrorResponse(): void
    {
        $this->http = $this->createMock(HttpClientInterface::class);
        $this->http->method('post')->willReturn(['message' => 'Error', 'statusCode' => 500]);

        /** @var Decryptor&MockObject $decryptor */
        $decryptor = $this->createMock(Decryptor::class);
        $decryptor->expects($this->never())->method('decrypt');
        $this->decryptor = $decryptor;

        try {
            $this->makeGateway()->post('/api/integrator/order', []);
        } catch (ApiException $e) {
            // expected
        }
    }

    // -------------------------------------------------------------------------
    // get()
    // -------------------------------------------------------------------------

    public function testGetBuildsCorrectUrl(): void
    {
        /** @var HttpClientInterface&MockObject $http */
        $http = $this->createMock(HttpClientInterface::class);
        $http->expects($this->once())
            ->method('get')
            ->with(self::BASE_URL . '/api/integrator/order/status', $this->anything())
            ->willReturn($this->fakeEncryptedApiResponse());

        $this->http = $http;
        $this->makeGateway()->get('/api/integrator/order/status', []);
    }

    public function testGetMergesIdentifierAndAesFlagWithEncryptedEnvelope(): void
    {
        $envelope = $this->fakeEnvelope();
        $this->encryptor->method('encrypt')->willReturn($envelope);

        /** @var HttpClientInterface&MockObject $http */
        $http = $this->createMock(HttpClientInterface::class);
        $http->expects($this->once())
            ->method('get')
            ->with($this->anything(), [
                'identifier'    => self::IDENTIFIER,
                'aes'           => true,
                'encryptedData' => $envelope['encryptedData'],
                'encryptedKeys' => $envelope['encryptedKeys'],
            ])
            ->willReturn($this->fakeEncryptedApiResponse());

        $this->http = $http;
        $this->makeGateway()->get('/api/integrator/order/status', ['integratorOrderId' => 'x']);
    }

    public function testGetEncryptsQueryBeforeSending(): void
    {
        $query = ['integratorId' => 'id', 'integratorOrderId' => 'order-id'];

        /** @var Encryptor&MockObject $encryptor */
        $encryptor = $this->createMock(Encryptor::class);
        $encryptor->expects($this->once())
            ->method('encrypt')
            ->with($query)
            ->willReturn($this->fakeEnvelope());

        $this->encryptor = $encryptor;
        $this->makeGateway()->get('/api/integrator/order/status', $query);
    }

    public function testGetReturnsDecryptedResponse(): void
    {
        $plainData = ['integratorOrderId' => 'uuid', 'status' => 'SUCCESS'];

        $this->decryptor = $this->createMock(Decryptor::class);
        $this->decryptor->method('decrypt')->willReturn($plainData);

        $result = $this->makeGateway()->get('/api/integrator/order/status', []);

        $this->assertSame($plainData, $result);
    }

    public function testGetThrowsApiExceptionOnErrorResponse(): void
    {
        $this->http = $this->createMock(HttpClientInterface::class);
        $this->http->method('get')->willReturn(['message' => 'Not found', 'statusCode' => 404]);

        $this->expectException(ApiException::class);
        $this->expectExceptionCode(404);

        $this->makeGateway()->get('/api/integrator/order/status', []);
    }

    // -------------------------------------------------------------------------
    // delete()
    // -------------------------------------------------------------------------

    public function testDeleteBuildsCorrectUrl(): void
    {
        /** @var HttpClientInterface&MockObject $http */
        $http = $this->createMock(HttpClientInterface::class);
        $http->expects($this->once())
            ->method('delete')
            ->with(self::BASE_URL . '/api/integrator/order/cancel', $this->anything())
            ->willReturn($this->fakeEncryptedApiResponse());

        $this->http = $http;
        $this->makeGateway()->delete('/api/integrator/order/cancel', ['integratorOrderId' => 'x']);
    }

    public function testDeleteMergesIdentifierWithEncryptedEnvelope(): void
    {
        $envelope = $this->fakeEnvelope();
        $this->encryptor->method('encrypt')->willReturn($envelope);

        /** @var HttpClientInterface&MockObject $http */
        $http = $this->createMock(HttpClientInterface::class);
        $http->expects($this->once())
            ->method('delete')
            ->with($this->anything(), [
                'identifier'    => self::IDENTIFIER,
                'encryptedData' => $envelope['encryptedData'],
                'encryptedKeys' => $envelope['encryptedKeys'],
                'aes'           => true,
            ])
            ->willReturn($this->fakeEncryptedApiResponse());

        $this->http = $http;
        $this->makeGateway()->delete('/api/integrator/order/cancel', ['integratorOrderId' => 'x']);
    }

    public function testDeleteEncryptsPayloadBeforeSending(): void
    {
        $data = ['integratorId' => 'id', 'integratorOrderId' => 'order-id'];

        /** @var Encryptor&MockObject $encryptor */
        $encryptor = $this->createMock(Encryptor::class);
        $encryptor->expects($this->once())
            ->method('encrypt')
            ->with($data)
            ->willReturn($this->fakeEnvelope());

        $this->encryptor = $encryptor;
        $this->makeGateway()->delete('/api/integrator/order/cancel', $data);
    }

    public function testDeleteReturnsDecryptedResponse(): void
    {
        $plainData = ['integratorOrderId' => 'uuid', 'status' => 'CANCELLED'];

        $this->decryptor = $this->createMock(Decryptor::class);
        $this->decryptor->method('decrypt')->willReturn($plainData);

        $this->http = $this->createMock(HttpClientInterface::class);
        $this->http->method('delete')->willReturn($this->fakeEncryptedApiResponse());

        $result = $this->makeGateway()->delete('/api/integrator/order/cancel', ['integratorOrderId' => 'x']);

        $this->assertSame($plainData, $result);
    }

    public function testDeleteThrowsApiExceptionOnErrorResponse(): void
    {
        $this->http = $this->createMock(HttpClientInterface::class);
        $this->http->method('delete')->willReturn(['message' => 'Forbidden', 'statusCode' => 403]);

        $this->expectException(ApiException::class);
        $this->expectExceptionCode(403);

        $this->makeGateway()->delete('/api/integrator/order/cancel', ['integratorOrderId' => 'x']);
    }

    public function testDeleteDoesNotCallDecryptorOnErrorResponse(): void
    {
        $this->http = $this->createMock(HttpClientInterface::class);
        $this->http->method('delete')->willReturn(['message' => 'Error', 'statusCode' => 500]);

        /** @var Decryptor&MockObject $decryptor */
        $decryptor = $this->createMock(Decryptor::class);
        $decryptor->expects($this->never())->method('decrypt');
        $this->decryptor = $decryptor;

        try {
            $this->makeGateway()->delete('/api/integrator/order/cancel', ['integratorOrderId' => 'x']);
        } catch (ApiException $e) {
            // expected
        }
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /** @return array<string, mixed> */
    private function fakeEnvelope(): array
    {
        return [
            'encryptedData' => 'ZW5jcnlwdGVkRGF0YQ==',
            'encryptedKeys' => 'ZW5jcnlwdGVkS2V5cw==',
            'aes'           => true,
        ];
    }

    /** @return array<string, mixed> */
    private function fakeEncryptedApiResponse(): array
    {
        return [
            'encryptedData' => 'cmVzcG9uc2VEYXRh',
            'encryptedKeys' => 'cmVzcG9uc2VLZXlz',
            'aes'           => true,
        ];
    }

    /** @return array<string, mixed> */
    private function fakePlainData(): array
    {
        return ['integratorOrderId' => 'order-uuid-123', 'urlForQR' => 'https://qr.example.com'];
    }
}
