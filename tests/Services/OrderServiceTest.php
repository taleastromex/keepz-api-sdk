<?php

declare(strict_types=1);

namespace KeepzSdk\Tests\Services;

use KeepzSdk\Client;
use KeepzSdk\Crypto\Encryptor;
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

    /** @var OrderService */
    private $service;

    protected function setUp(): void
    {
        $this->http      = $this->createStub(HttpClient::class);
        $this->encryptor = $this->createStub(Encryptor::class);

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
        $this->encryptor->method('encrypt')->willReturn($this->fakeEnvelope());

        /** @var HttpClient&MockObject $http */
        $http = $this->createMock(HttpClient::class);
        $http->expects($this->once())
            ->method('post')
            ->with(self::BASE_URL . '/api/integrator/order', $this->anything())
            ->willReturn([]);

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
            ->willReturn([]);

        $this->http = $http;
        $this->rebuildService();

        $this->service->create($this->minimalOrder());
    }

    public function testCreateReturnsHttpResponse(): void
    {
        $apiResponse = ['encryptedData' => 'resp==', 'encryptedKeys' => 'keys==', 'aes' => true];

        $this->encryptor->method('encrypt')->willReturn($this->fakeEnvelope());
        $this->http->method('post')->willReturn($apiResponse);

        $this->assertSame($apiResponse, $this->service->create($this->minimalOrder()));
    }

    public function testCreateReturnsErrorResponseAsIs(): void
    {
        $errorResponse = ['message' => 'Permission denied', 'statusCode' => 6031, 'exceptionGroup' => 3];

        $this->encryptor->method('encrypt')->willReturn($this->fakeEnvelope());
        $this->http->method('post')->willReturn($errorResponse);

        $this->assertSame($errorResponse, $this->service->create($this->minimalOrder()));
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
            ->with(array_merge($orderData, ['distributions' => $distributions]))
            ->willReturn($this->fakeEnvelope());

        $this->encryptor = $encryptor;
        $this->rebuildService();

        $this->service->createSplit($orderData, $distributions);
    }

    public function testCreateSplitDelegatesToCreate(): void
    {
        $this->encryptor->method('encrypt')->willReturn($this->fakeEnvelope());

        /** @var HttpClient&MockObject $http */
        $http = $this->createMock(HttpClient::class);
        $http->expects($this->once())
            ->method('post')
            ->with(self::BASE_URL . '/api/integrator/order', $this->anything())
            ->willReturn([]);

        $this->http = $http;
        $this->rebuildService();

        $this->service->createSplit($this->minimalOrder(), []);
    }

    public function testCreateSplitReturnsHttpResponse(): void
    {
        $apiResponse = ['encryptedData' => 'resp==', 'encryptedKeys' => 'keys==', 'aes' => true];

        $this->encryptor->method('encrypt')->willReturn($this->fakeEnvelope());
        $this->http->method('post')->willReturn($apiResponse);

        $this->assertSame($apiResponse, $this->service->createSplit($this->minimalOrder(), []));
    }

    public function testCreateSplitWithEmptyDistributions(): void
    {
        $orderData = $this->minimalOrder();

        /** @var Encryptor&MockObject $encryptor */
        $encryptor = $this->createMock(Encryptor::class);
        $encryptor->expects($this->once())
            ->method('encrypt')
            ->with(array_merge($orderData, ['distributions' => []]))
            ->willReturn($this->fakeEnvelope());

        $this->encryptor = $encryptor;
        $this->rebuildService();

        $this->service->createSplit($orderData, []);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function rebuildService(): void
    {
        $client        = new Client(self::BASE_URL, self::IDENTIFIER, $this->http, $this->encryptor);
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
}
