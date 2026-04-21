<?php

declare(strict_types=1);

namespace KeepzSdk\Http;

use KeepzSdk\Crypto\Decryptor;
use KeepzSdk\Crypto\Encryptor;
use KeepzSdk\Exceptions\ApiException;

class ApiGateway
{
    /** @var string */
    private $baseUrl;

    /** @var string */
    private $identifier;

    /** @var HttpClientInterface */
    private $http;

    /** @var Encryptor */
    private $encryptor;

    /** @var Decryptor */
    private $decryptor;

    public function __construct(
        string $baseUrl,
        string $identifier,
        HttpClientInterface $http,
        Encryptor $encryptor,
        Decryptor $decryptor
    ) {
        $this->baseUrl = $baseUrl;
        $this->identifier = $identifier;
        $this->http = $http;
        $this->encryptor = $encryptor;
        $this->decryptor = $decryptor;
    }

    /**
     * Encrypts $data, sends a POST request, validates and decrypts the response.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     * @throws ApiException
     */
    public function post(string $path, array $data): array
    {
        $encrypted = $this->encryptor->encrypt($data);

        $response = $this->http->post(
            $this->baseUrl . $path,
            array_merge(['identifier' => $this->identifier], $encrypted)
        );

        return $this->handleResponse($response);
    }

    /**
     * Encrypts $query, sends a GET request, validates and decrypts the response.
     *
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     * @throws ApiException
     */
    public function get(string $path, array $query = []): array
    {
        $encrypted = $this->encryptor->encrypt($query);

        $response = $this->http->get(
            $this->baseUrl . $path,
            array_merge(['identifier' => $this->identifier, 'aes' => true], $encrypted)
        );

        return $this->handleResponse($response);
    }

    /**
     * @param array<string, mixed> $response
     * @return array<string, mixed>
     * @throws ApiException
     */
    private function handleResponse(array $response): array
    {
        if (empty($response['aes'])) {
            throw new ApiException($response);
        }

        return $this->decryptor->decrypt($response);
    }
}
