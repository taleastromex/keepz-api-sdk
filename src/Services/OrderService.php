<?php

declare(strict_types=1);

namespace KeepzSdk\Services;

use KeepzSdk\Client;

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
     * @return array<string, mixed>
     */
    public function create(array $data): array
    {
        $encrypted = $this->client->getEncryptor()->encrypt($data);

        $response = $this->client->getHttp()->post(
            $this->client->getBaseUrl() . '/api/integrator/order',
            array_merge([
                'identifier' => $this->client->getIdentifier(),
            ], $encrypted)
        );

        $decryptor = $this->client->getDecryptor();
        if ($decryptor !== null) {
            return $decryptor->decrypt($response);
        }

        return $response;
    }

    /**
     * @param array<string, mixed> $orderData
     * @param array<int, array<string, mixed>> $distributions
     * @return array<string, mixed> 
     */
    public function createSplit(array $orderData, array $distributions): array
    {
        $orderData['distributions'] = $distributions;

        return $this->create($orderData);
    }
}