<?php

declare(strict_types=1);

namespace KeepzSdk;

use KeepzSdk\Crypto\Decryptor;
use KeepzSdk\Crypto\Encryptor;
use KeepzSdk\Http\ApiGateway;
use KeepzSdk\Http\HttpClient;
use KeepzSdk\Services\OrderService;

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
}
