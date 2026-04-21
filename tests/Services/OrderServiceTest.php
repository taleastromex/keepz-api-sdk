<?php

declare(strict_types=1);

namespace KeepzSdk\Tests\Services;

use KeepzSdk\Client;
use KeepzSdk\Crypto\Decryptor;
use KeepzSdk\Crypto\Encryptor;
use KeepzSdk\DTO\OrderCreatedData;
use KeepzSdk\Exceptions\ApiException;
use KeepzSdk\Http\HttpClient;
use KeepzSdk\Services\OrderService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class OrderServiceTest extends TestCase
{
    private const BASE_URL   = 'https://gateway.dev.keepz.me/ecommerce-service';
    private const IDENTIFIER = 'test-integrator-id';

    /** @var HttpClient|MockObject */
    private $http;

    /** @var Encryptor|MockObject */
    private $encryptor;

    /** @var Decryptor|MockObject */
    private $decryptor;

    /** @var OrderService */
    private $service;

    protected function setUp(): void
    {
        $this->http      = $this->createStub(HttpClient::class);
        $this->encryptor = $this->createStub(Encryptor::class);
        $this->decryptor = $this->createStub(Decryptor::class);

        $this->encryptor->method('encrypt')->willReturn($this->fakeEnvelope());
        $this->http->method('post')->willReturn($this->fakeApiResponse());
        $this->decryptor->method('decrypt')->willReturn($this->fakePlainResponse());

        $this->rebuildService();
    }

    // -------------------------------------------------------------------------
    // create()
    // -------------------------------------------------------------------------

    public function testCreateEncryptsPayload(): void
    {
        $payload = $this->minimalOrder();

        /** @var Encryptor&MockObject $encryptor */
        $encryptor = $this->createMock(Encryptor::class);
        $encryptor->expects($this->once())
            ->method('encrypt')
            ->with($payload)
            ->willReturn($this->fakeEnvelope());

        $this->encryptor = $encryptor;
        $this->rebuildService();

        $this->service->create($payload);
    }

    public function testCreatePostsToCorrectEndpoint(): void
    {
        /** @var HttpClient&MockObject $http */
        $http = $this->createMock(HttpClient::class);
        $http->expects($this->once())
            ->method('post')
            ->with(self::BASE_URL . '/api/integrator/order', $this->anything())
            ->willReturn($this->fakeApiResponse());

        $this->http = $http;
        $this->rebuildService();

        $this->service->create($this->minimalOrder());
    }

    public function testCreateMergesIdentifierWithEncryptedEnvelope(): void
    {
        $envelope = $this->fakeEnvelope();
        $this->encryptor->method('encrypt')->willReturn($envelope);

        /** @var HttpClient&MockObject $http */
        $http = $this->createMock(HttpClient::class);
        $http->expects($this->once())
            ->method('post')
            ->with($this->anything(), [
                'identifier'    => self::IDENTIFIER,
                'encryptedData' => $envelope['encryptedData'],
                'encryptedKeys' => $envelope['encryptedKeys'],
                'aes'           => true,
            ])
            ->willReturn($this->fakeApiResponse());

        $this->http = $http;
        $this->rebuildService();

        $this->service->create($this->minimalOrder());
    }

    public function testCreateReturnsOrderCreatedData(): void
    {
        $result = $this->service->create($this->minimalOrder());

        $this->assertInstanceOf(OrderCreatedData::class, $result);
        $this->assertSame('order-uuid-123', $result->getIntegratorOrderId());
        $this->assertSame('https://qr.example.com', $result->getUrlForQR());
    }

    public function testCreateThrowsApiExceptionOnErrorResponse(): void
    {
        $errorResponse  = ['message' => 'Permission denied', 'statusCode' => 6031, 'exceptionGroup' => 3];
        $this->http     = $this->createStub(HttpClient::class);
        $this->http->method('post')->willReturn($errorResponse);
        $this->rebuildService();

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Permission denied');
        $this->expectExceptionCode(6031);

        $this->service->create($this->minimalOrder());
    }

    public function testApiExceptionCarriesFullErrorDetails(): void
    {
        $errorResponse  = ['message' => 'Permission denied', 'statusCode' => 6031, 'exceptionGroup' => 3];
        $this->http     = $this->createStub(HttpClient::class);
        $this->http->method('post')->willReturn($errorResponse);
        $this->rebuildService();

        try {
            $this->service->create($this->minimalOrder());
            $this->fail('Expected ApiException was not thrown');
        } catch (ApiException $e) {
            $this->assertSame(6031, $e->getStatusCode());
            $this->assertSame(3, $e->getExceptionGroup());
            $this->assertSame($errorResponse, $e->getRawResponse());
        }
    }

    public function testCreateDoesNotCallDecryptorOnErrorResponse(): void
    {
        $this->http = $this->createStub(HttpClient::class);
        $this->http->method('post')->willReturn(['message' => 'Not found', 'statusCode' => 404]);

        /** @var Decryptor&MockObject $decryptor */
        $decryptor = $this->createMock(Decryptor::class);
        $decryptor->expects($this->never())->method('decrypt');

        $this->decryptor = $decryptor;
        $this->rebuildService();

        try {
            $this->service->create($this->minimalOrder());
        } catch (ApiException $e) {
            // expected
        }
    }

    // -------------------------------------------------------------------------
    // createSplit()
    // -------------------------------------------------------------------------

    public function testCreateSplitMergesDistributionsIntoPayload(): void
    {
        $orderData     = $this->minimalOrder();
        $distributions = [
            ['receiverType' => 'BRANCH', 'receiverIdentifier' => 'uuid-1', 'amount' => 75],
            ['receiverType' => 'IBAN',   'receiverIdentifier' => 'GE34BG0000001234567890', 'amount' => 25],
        ];

        /** @var Encryptor&MockObject $encryptor */
        $encryptor = $this->createMock(Encryptor::class);
        $encryptor->expects($this->once())
            ->method('encrypt')
            ->with(array_merge($orderData, ['splitDetails' => $distributions]))
            ->willReturn($this->fakeEnvelope());

        $this->encryptor = $encryptor;
        $this->rebuildService();

        $this->service->createSplit($orderData, $distributions);
    }

    public function testCreateSplitDelegatesToCreate(): void
    {
        /** @var HttpClient&MockObject $http */
        $http = $this->createMock(HttpClient::class);
        $http->expects($this->once())
            ->method('post')
            ->with(self::BASE_URL . '/api/integrator/order', $this->anything())
            ->willReturn($this->fakeApiResponse());

        $this->http = $http;
        $this->rebuildService();

        $this->service->createSplit($this->minimalOrder(), []);
    }

    public function testCreateSplitReturnsOrderCreatedData(): void
    {
        $result = $this->service->createSplit($this->minimalOrder(), []);

        $this->assertInstanceOf(OrderCreatedData::class, $result);
    }

    public function testCreateSplitWithEmptyDistributions(): void
    {
        $orderData = $this->minimalOrder();

        /** @var Encryptor&MockObject $encryptor */
        $encryptor = $this->createMock(Encryptor::class);
        $encryptor->expects($this->once())
            ->method('encrypt')
            ->with(array_merge($orderData, ['splitDetails' => []]))
            ->willReturn($this->fakeEnvelope());

        $this->encryptor = $encryptor;
        $this->rebuildService();

        $this->service->createSplit($orderData, []);
    }

    public function testCreateSplitThrowsApiExceptionOnErrorResponse(): void
    {
        $this->http = $this->createStub(HttpClient::class);
        $this->http->method('post')->willReturn(['message' => 'Forbidden', 'statusCode' => 403]);
        $this->rebuildService();

        $this->expectException(ApiException::class);
        $this->expectExceptionCode(403);

        $this->service->createSplit($this->minimalOrder(), []);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function rebuildService(): void
    {
        $client        = new Client(self::BASE_URL, self::IDENTIFIER, $this->http, $this->encryptor, $this->decryptor);
        $this->service = new OrderService($client);
    }

    /** @return array<string, mixed> */
    private function minimalOrder(): array
    {
        return [
            'amount'            => 100,
            'receiverId'        => '90434fa9-46df-4c44-a4d1-da742ac815da',
            'receiverType'      => 'BRANCH',
            'integratorId'      => self::IDENTIFIER,
            'integratorOrderId' => '3fa85f64-5717-4562-b3fc-2c963f66afa6',
        ];
    }

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
    private function fakeApiResponse(): array
    {
        return [
            'encryptedData' => 'cmVzcG9uc2VEYXRh',
            'encryptedKeys' => 'cmVzcG9uc2VLZXlz',
            'aes'           => true,
        ];
    }

    /** @return array<string, mixed> */
    private function fakePlainResponse(): array
    {
        return [
            'integratorOrderId' => 'order-uuid-123',
            'urlForQR'          => 'https://qr.example.com',
        ];
    }
}
