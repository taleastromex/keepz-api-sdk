<?php

declare(strict_types=1);

namespace KeepzSdk\Services;

use KeepzSdk\DTO\SavedCardData;
use KeepzSdk\Exceptions\ApiException;
use KeepzSdk\Http\ApiGateway;

class CardService
{
    /** @var ApiGateway */
    private $gateway;

    public function __construct(ApiGateway $gateway)
    {
        $this->gateway = $gateway;
    }

    /**
     * Retrieves information about a card previously saved during an order.
     *
     * @param string $integratorOrderId UUID of the order where the card was saved.
     * @throws ApiException
     */
    public function getSavedCard(string $integratorOrderId): SavedCardData
    {
        return SavedCardData::fromArray(
            $this->gateway->get(
                '/api/v1/integrator/card/order-id',
                ['integratorOrderId' => $integratorOrderId]
            )
        );
    }
}