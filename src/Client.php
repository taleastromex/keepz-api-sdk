<?php

declare(strict_types=1);

namespace Taleastromex\KeepzApiSdk;

use Taleastromex\KeepzApiSdk\Crypto\Decryptor;
use Taleastromex\KeepzApiSdk\Crypto\Encryptor;
use Taleastromex\KeepzApiSdk\Http\ApiGateway;
use Taleastromex\KeepzApiSdk\Http\HttpClient;
use Taleastromex\KeepzApiSdk\Services\CardService;
use Taleastromex\KeepzApiSdk\Services\OrderService;

class Client
{
    /** @var ApiGateway */
    private $gateway;

    public function __construct(ApiGateway $gateway)
    {
        $this->gateway = $gateway;
    }

    /**
     * Convenience factory — assembles all internal dependencies from raw credentials.
     */
    public static function create(
        string $baseUrl,
        string $identifier,
        string $publicKey,
        string $privateKey
    ): self {
        return new self(new ApiGateway(
            $baseUrl,
            $identifier,
            new HttpClient(),
            new Encryptor($publicKey),
            new Decryptor($privateKey)
        ));
    }

    public function orders(): OrderService
    {
        return new OrderService($this->gateway);
    }

    public function cards(): CardService
    {
        return new CardService($this->gateway);
    }
}
