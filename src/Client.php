<?php

declare(strict_types=1);

namespace KeepzSdk;

use KeepzSdk\Http\HttpClient;
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

    /**
     * @param string $baseUrl
     * @param string $identifier
     * @param HttpClient $http
     * @param Encryptor $encryptor
     */
    public function __construct(string $baseUrl, string $identifier, HttpClient $http, Encryptor $encryptor)
    {
        $this->baseUrl = $baseUrl;
        $this->identifier = $identifier;
        $this->http = $http;
        $this->encryptor = $encryptor;
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
}