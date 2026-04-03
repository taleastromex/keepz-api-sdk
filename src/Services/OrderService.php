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
     * @param array $data
     * @return array
     */
    public function create(array $data): array
    {
        $encrypted = $this->client->getEncryptor()->encrypt($data);

        return $this->client->getHttp()->post(
            $this->client->getBaseUrl() . '/api/integrator/order',
            array_merge([
                'identifier' => $this->client->getIdentifier(),
            ], $encrypted)
        );
    }
}