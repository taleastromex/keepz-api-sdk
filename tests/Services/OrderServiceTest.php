<?php

declare(strict_types=1);

namespace Taleastromex\KeepzApiSdk\Tests\Services;

use Taleastromex\KeepzApiSdk\DTO\OrderCreatedData;
use Taleastromex\KeepzApiSdk\DTO\OrderStatusData;
use Taleastromex\KeepzApiSdk\Exceptions\ApiException;
use Taleastromex\KeepzApiSdk\Http\ApiGateway;
use Taleastromex\KeepzApiSdk\Services\OrderService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class OrderServiceTest extends TestCase
{
    /** @var ApiGateway|MockObject */
    private $gateway;

    /** @var OrderService */
    private $service;

    protected function setUp(): void
    {
        $this->gateway = $this->createStub(ApiGateway::class);
        $this->gateway->method('post')->willReturn($this->fakeCreatedResponse());
        $this->gateway->method('get')->willReturn($this->fakeStatusResponse());

        $this->service = new OrderService($this->gateway);
    }

    // -------------------------------------------------------------------------
    // create()
    // -------------------------------------------------------------------------

    public function testCreatePostsToCorrectPath(): void
    {
        /** @var ApiGateway&MockObject $gateway */
        $gateway = $this->createMock(ApiGateway::class);
        $gateway->expects($this->once())
            ->method('post')
            ->with('/api/integrator/order', $this->anything())
            ->willReturn($this->fakeCreatedResponse());

        (new OrderService($gateway))->create($this->minimalOrder());
    }

    public function testCreateForwardsPayloadToGateway(): void
    {
        $payload = $this->minimalOrder();

        /** @var ApiGateway&MockObject $gateway */
        $gateway = $this->createMock(ApiGateway::class);
        $gateway->expects($this->once())
            ->method('post')
            ->with($this->anything(), $payload)
            ->willReturn($this->fakeCreatedResponse());

        (new OrderService($gateway))->create($payload);
    }

    public function testCreateReturnsOrderCreatedData(): void
    {
        $result = $this->service->create($this->minimalOrder());

        $this->assertInstanceOf(OrderCreatedData::class, $result);
        $this->assertSame('order-uuid-123', $result->getIntegratorOrderId());
        $this->assertSame('https://qr.example.com', $result->getUrlForQR());
    }

    public function testCreatePropagatesApiException(): void
    {
        /** @var ApiGateway&MockObject $gateway */
        $gateway = $this->createStub(ApiGateway::class);
        $gateway->method('post')->willThrowException(
            new ApiException(['message' => 'Permission denied', 'statusCode' => 6031])
        );

        $this->expectException(ApiException::class);
        $this->expectExceptionCode(6031);

        (new OrderService($gateway))->create($this->minimalOrder());
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

        /** @var ApiGateway&MockObject $gateway */
        $gateway = $this->createMock(ApiGateway::class);
        $gateway->expects($this->once())
            ->method('post')
            ->with($this->anything(), array_merge($orderData, ['splitDetails' => $distributions]))
            ->willReturn($this->fakeCreatedResponse());

        (new OrderService($gateway))->createSplit($orderData, $distributions);
    }

    public function testCreateSplitReturnsOrderCreatedData(): void
    {
        $result = $this->service->createSplit($this->minimalOrder(), []);

        $this->assertInstanceOf(OrderCreatedData::class, $result);
    }

    public function testCreateSplitWithEmptyDistributions(): void
    {
        $orderData = $this->minimalOrder();

        /** @var ApiGateway&MockObject $gateway */
        $gateway = $this->createMock(ApiGateway::class);
        $gateway->expects($this->once())
            ->method('post')
            ->with($this->anything(), array_merge($orderData, ['splitDetails' => []]))
            ->willReturn($this->fakeCreatedResponse());

        (new OrderService($gateway))->createSplit($orderData, []);
    }

    // -------------------------------------------------------------------------
    // getOrderStatus()
    // -------------------------------------------------------------------------

    public function testGetOrderStatusGetsFromCorrectPath(): void
    {
        /** @var ApiGateway&MockObject $gateway */
        $gateway = $this->createMock(ApiGateway::class);
        $gateway->expects($this->once())
            ->method('get')
            ->with('/api/integrator/order/status', $this->anything())
            ->willReturn($this->fakeStatusResponse());

        (new OrderService($gateway))->getOrderStatus('integrator-id', 'order-id');
    }

    public function testGetOrderStatusForwardsCorrectQuery(): void
    {
        /** @var ApiGateway&MockObject $gateway */
        $gateway = $this->createMock(ApiGateway::class);
        $gateway->expects($this->once())
            ->method('get')
            ->with($this->anything(), [
                'integratorId'      => 'integrator-id',
                'integratorOrderId' => 'order-id',
            ])
            ->willReturn($this->fakeStatusResponse());

        (new OrderService($gateway))->getOrderStatus('integrator-id', 'order-id');
    }

    public function testGetOrderStatusReturnsOrderStatusData(): void
    {
        $result = $this->service->getOrderStatus('integrator-id', 'order-id');

        $this->assertInstanceOf(OrderStatusData::class, $result);
        $this->assertSame('order-uuid-123', $result->getIntegratorOrderId());
        $this->assertSame('SUCCESS', $result->getStatus());
    }

    public function testGetOrderStatusPropagatesApiException(): void
    {
        /** @var ApiGateway&MockObject $gateway */
        $gateway = $this->createStub(ApiGateway::class);
        $gateway->method('get')->willThrowException(
            new ApiException(['message' => 'Not found', 'statusCode' => 404])
        );

        $this->expectException(ApiException::class);
        $this->expectExceptionCode(404);

        (new OrderService($gateway))->getOrderStatus('x', 'y');
    }

    // -------------------------------------------------------------------------
    // refund()
    // -------------------------------------------------------------------------

    public function testRefundPostsToCorrectPath(): void
    {
        /** @var ApiGateway&MockObject $gateway */
        $gateway = $this->createMock(ApiGateway::class);
        $gateway->expects($this->once())
            ->method('post')
            ->with('/api/integrator/order/refund/v2', $this->anything())
            ->willReturn($this->fakeRefundResponse());

        (new OrderService($gateway))->refund($this->minimalRefund());
    }

    public function testRefundForwardsPayloadToGateway(): void
    {
        $payload = $this->minimalRefund();

        /** @var ApiGateway&MockObject $gateway */
        $gateway = $this->createMock(ApiGateway::class);
        $gateway->expects($this->once())
            ->method('post')
            ->with($this->anything(), $payload)
            ->willReturn($this->fakeRefundResponse());

        (new OrderService($gateway))->refund($payload);
    }

    public function testRefundReturnsOrderStatusDataWithRefundRequestedStatus(): void
    {
        /** @var ApiGateway&MockObject $gateway */
        $gateway = $this->createStub(ApiGateway::class);
        $gateway->method('post')->willReturn($this->fakeRefundResponse());

        $result = (new OrderService($gateway))->refund($this->minimalRefund());

        $this->assertInstanceOf(OrderStatusData::class, $result);
        $this->assertSame('order-uuid-123', $result->getIntegratorOrderId());
        $this->assertSame('REFUND_REQUESTED', $result->getStatus());
    }

    public function testRefundWithOptionalRefundInitiator(): void
    {
        $payload = array_merge($this->minimalRefund(), ['refundInitiator' => 'INTEGRATOR']);

        /** @var ApiGateway&MockObject $gateway */
        $gateway = $this->createMock(ApiGateway::class);
        $gateway->expects($this->once())
            ->method('post')
            ->with($this->anything(), $payload)
            ->willReturn($this->fakeRefundResponse());

        (new OrderService($gateway))->refund($payload);
    }

    public function testRefundWithSplitRefundDetails(): void
    {
        $payload = array_merge($this->minimalRefund(), [
            'refundDetails' => [
                ['receiverType' => 'BRANCH', 'receiverIdentifier' => 'branch-uuid', 'amount' => 60],
                ['receiverType' => 'IBAN',   'receiverIdentifier' => 'GE34BG0000001234567890', 'amount' => 40],
            ],
        ]);

        /** @var ApiGateway&MockObject $gateway */
        $gateway = $this->createMock(ApiGateway::class);
        $gateway->expects($this->once())
            ->method('post')
            ->with($this->anything(), $payload)
            ->willReturn($this->fakeRefundResponse());

        (new OrderService($gateway))->refund($payload);
    }

    public function testRefundPropagatesApiException(): void
    {
        /** @var ApiGateway&MockObject $gateway */
        $gateway = $this->createStub(ApiGateway::class);
        $gateway->method('post')->willThrowException(
            new ApiException(['message' => "You can't refund order: Order is already fully refunded", 'statusCode' => 6005])
        );

        $this->expectException(ApiException::class);
        $this->expectExceptionCode(6005);

        (new OrderService($gateway))->refund($this->minimalRefund());
    }

    // -------------------------------------------------------------------------
    // cancel()
    // -------------------------------------------------------------------------

    public function testCancelDeletesToCorrectPath(): void
    {
        /** @var ApiGateway&MockObject $gateway */
        $gateway = $this->createMock(ApiGateway::class);
        $gateway->expects($this->once())
            ->method('delete')
            ->with('/api/integrator/order/cancel', $this->anything())
            ->willReturn($this->fakeCancelResponse());

        (new OrderService($gateway))->cancel('integrator-id', 'order-id');
    }

    public function testCancelForwardsCorrectPayload(): void
    {
        /** @var ApiGateway&MockObject $gateway */
        $gateway = $this->createMock(ApiGateway::class);
        $gateway->expects($this->once())
            ->method('delete')
            ->with($this->anything(), [
                'integratorId'      => 'integrator-id',
                'integratorOrderId' => 'order-id',
            ])
            ->willReturn($this->fakeCancelResponse());

        (new OrderService($gateway))->cancel('integrator-id', 'order-id');
    }

    public function testCancelReturnsOrderStatusData(): void
    {
        /** @var ApiGateway&MockObject $gateway */
        $gateway = $this->createStub(ApiGateway::class);
        $gateway->method('delete')->willReturn($this->fakeCancelResponse());

        $result = (new OrderService($gateway))->cancel('integrator-id', 'order-id');

        $this->assertInstanceOf(OrderStatusData::class, $result);
        $this->assertSame('order-id', $result->getIntegratorOrderId());
        $this->assertSame('CANCELLED', $result->getStatus());
    }

    public function testCancelPropagatesApiException(): void
    {
        /** @var ApiGateway&MockObject $gateway */
        $gateway = $this->createStub(ApiGateway::class);
        $gateway->method('delete')->willThrowException(
            new ApiException(['message' => 'Order cannot be cancelled', 'statusCode' => 4031])
        );

        $this->expectException(ApiException::class);
        $this->expectExceptionCode(4031);

        (new OrderService($gateway))->cancel('integrator-id', 'order-id');
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /** @return array<string, mixed> */
    private function minimalOrder(): array
    {
        return [
            'amount'            => 100,
            'receiverId'        => '90434fa9-46df-4c44-a4d1-da742ac815da',
            'receiverType'      => 'BRANCH',
            'integratorId'      => 'test-integrator-id',
            'integratorOrderId' => '3fa85f64-5717-4562-b3fc-2c963f66afa6',
        ];
    }

    /** @return array<string, mixed> */
    private function fakeCreatedResponse(): array
    {
        return [
            'integratorOrderId' => 'order-uuid-123',
            'urlForQR'          => 'https://qr.example.com',
        ];
    }

    /** @return array<string, mixed> */
    private function fakeStatusResponse(): array
    {
        return [
            'integratorOrderId' => 'order-uuid-123',
            'status'            => 'SUCCESS',
        ];
    }

    /** @return array<string, mixed> */
    private function fakeCancelResponse(): array
    {
        return [
            'integratorOrderId' => 'order-id',
            'status'            => 'CANCELLED',
        ];
    }

    /** @return array<string, mixed> */
    private function fakeRefundResponse(): array
    {
        return [
            'integratorOrderId' => 'order-uuid-123',
            'status'            => 'REFUND_REQUESTED',
        ];
    }

    /** @return array<string, mixed> */
    private function minimalRefund(): array
    {
        return [
            'integratorId'      => 'integrator-uuid',
            'integratorOrderId' => 'order-uuid-123',
            'amount'            => 100,
        ];
    }
}
