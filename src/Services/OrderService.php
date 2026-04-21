<?php

declare(strict_types=1);

namespace KeepzSdk\Services;

use KeepzSdk\DTO\OrderCreatedData;
use KeepzSdk\DTO\OrderStatusData;
use KeepzSdk\Exceptions\ApiException;
use KeepzSdk\Http\ApiGateway;

class OrderService
{
    /** @var ApiGateway */
    private $gateway;

    public function __construct(ApiGateway $gateway)
    {
        $this->gateway = $gateway;
    }

    /**
     * @param array<string, mixed> $data
     * @throws ApiException
     */
    public function create(array $data): OrderCreatedData
    {
        return OrderCreatedData::fromArray(
            $this->gateway->post('/api/integrator/order', $data)
        );
    }

    /**
     * @param array<string, mixed> $orderData
     * @param array<int, array<string, mixed>> $distributions
     * @return OrderCreatedData
     * @throws ApiException
     */
    public function createSplit(array $orderData, array $distributions): OrderCreatedData
    {
        $orderData['splitDetails'] = $distributions;

        return $this->create($orderData);
    }

    /**
     * @param string $integratorId
     * @param string $integratorOrderId
     * @return OrderStatusData
     * @throws ApiException
     */
    public function getOrderStatus(string $integratorId, string $integratorOrderId): OrderStatusData
    {
        return OrderStatusData::fromArray(
            $this->gateway->get('/api/integrator/order/status', [
                'integratorId' => $integratorId,
                'integratorOrderId' => $integratorOrderId,
            ])
        );
    }

    /**
     * @param string $integratorId
     * @param string $integratorOrderId
     * @throws ApiException
     * @return OrderStatusData
     */
    public function cancel(string $integratorId, string $integratorOrderId): OrderStatusData
    {
        return OrderStatusData::fromArray(
            $this->gateway->delete('/api/integrator/order/cancel', [
                'integratorId' => $integratorId,
                'integratorOrderId' => $integratorOrderId,
            ])
        );
    }
}
