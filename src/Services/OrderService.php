<?php

declare(strict_types=1);

namespace Taleastromex\KeepzApiSdk\Services;

use Taleastromex\KeepzApiSdk\DTO\OrderCreatedData;
use Taleastromex\KeepzApiSdk\DTO\OrderStatusData;
use Taleastromex\KeepzApiSdk\Exceptions\ApiException;
use Taleastromex\KeepzApiSdk\Http\ApiGateway;

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

    /**
     * Initiates a refund for a completed order.
     *
     * Required fields in $data: integratorId, integratorOrderId, amount.
     * Optional fields: refundInitiator (INTEGRATOR|OPERATOR), refundDetails (split breakdown).
     *
     * The response always carries status REFUND_REQUESTED — the final outcome
     * (REFUNDED_BY_INTEGRATOR, PARTIALLY_REFUNDED, REFUNDED_FAILED) is resolved
     * asynchronously; use getOrderStatus() to poll for it.
     *
     * @param array<string, mixed> $data
     * @return OrderStatusData
     * @throws ApiException
     */
    public function refund(array $data): OrderStatusData
    {
        return OrderStatusData::fromArray(
            $this->gateway->post('/api/integrator/order/refund/v2', $data)
        );
    }
}
