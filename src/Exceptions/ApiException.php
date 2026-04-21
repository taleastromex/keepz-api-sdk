<?php

declare(strict_types=1);

namespace KeepzSdk\Exceptions;

class ApiException extends \RuntimeException
{
    /** @var int */
    private $statusCode;

    /** @var int|null */
    private $exceptionGroup;

    /** @var array<string, mixed> */
    private $rawResponse;

    /**
     * @param array<string, mixed> $response
     */
    public function __construct(array $response)
    {
        $this->statusCode = isset($response['statusCode']) ? (int) $response['statusCode'] : 0;
        $this->exceptionGroup = isset($response['exceptionGroup']) ? (int) $response['exceptionGroup'] : null;
        $this->rawResponse = $response;

        parent::__construct(
            isset($response['message']) ? (string) $response['message'] : 'Unknown API error',
            $this->statusCode
        );
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getExceptionGroup(): ?int
    {
        return $this->exceptionGroup;
    }

    /**
     * @return array<string, mixed>
     */
    public function getRawResponse(): array
    {
        return $this->rawResponse;
    }
}
