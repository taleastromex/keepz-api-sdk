<?php

declare(strict_types=1);

namespace KeepzSdk;

use KeepzSdk\Http\HttpClient;
use KeepzSdk\Crypto\Decryptor;
use KeepzSdk\Crypto\Encryptor;
use KeepzSdk\Services\OrderService;

class Client
{
    /** @var string */
    private $baseUrl;

    /** @var string */
    private $identifier;

    /** @var HttpClient */
    private $http;

    /** @var Encryptor */
    private $encryptor;

    /** @var Decryptor */
    private $decryptor;

    /**
     * @param string $baseUrl
     * @param string $identifier
     * @param HttpClient $http
     * @param Encryptor $encryptor
     * @param Decryptor $decryptor
     */
    public function __construct(
        string $baseUrl,
        string $identifier,
        HttpClient $http,
        Encryptor $encryptor,
        Decryptor $decryptor
    ) {
        $this->baseUrl = $baseUrl;
        $this->identifier = $identifier;
        $this->http = $http;
        $this->encryptor = $encryptor;
        $this->decryptor = $decryptor;
    }

    public function orders(): OrderService
    {
        return new OrderService($this);
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function getHttp(): HttpClient
    {
        return $this->http;
    }

    public function getEncryptor(): Encryptor
    {
        return $this->encryptor;
    }

    public function getDecryptor(): Decryptor
    {
        return $this->decryptor;
    }
}