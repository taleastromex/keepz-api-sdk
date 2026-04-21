<?php

declare(strict_types=1);

namespace KeepzSdk\Services;

use KeepzSdk\Client;
use KeepzSdk\Exceptions\ApiException;
use KeepzSdk\DTO\OrderCreatedData;

class OrderService
{
    /** @var Client */
    private $client;

    /**
     * @param Client $client
     */
    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    /**
     * @param array<string, mixed> $data
     * @return OrderCreatedData
     * @throws \Exception|ApiException
     */
    public function create(array $data): OrderCreatedData
    {
        $encrypted = $this->client->getEncryptor()->encrypt($data);

        $response = $this->client->getHttp()->post(
            $this->client->getBaseUrl() . '/api/integrator/order',
            array_merge([
                'identifier' => $this->client->getIdentifier(),
            ], $encrypted)
        );

        if (empty($response['aes'])) {
            throw new ApiException($response);
        }

        $decrypted = $this->client->getDecryptor()->decrypt($response);

        return new OrderCreatedData(
            $decrypted['integratorOrderId'],
            $decrypted['urlForQR'],
        );
    }

    /**
     * @param array<string, mixed> $orderData
     * @param array<int, array<string, mixed>> $distributions
     * @return OrderCreatedData
     * @throws \Exception|ApiException
     */
    public function createSplit(array $orderData, array $distributions): OrderCreatedData
    {
        $orderData['splitDetails'] = $distributions;

        return $this->create($orderData);
    }
}