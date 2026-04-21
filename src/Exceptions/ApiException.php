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
        $statusCode           = $response['statusCode'] ?? null;
        $exceptionGroup       = $response['exceptionGroup'] ?? null;
        $message              = $response['message'] ?? null;

        $this->statusCode     = is_int($statusCode) ? $statusCode : 0;
        $this->exceptionGroup = is_int($exceptionGroup) ? $exceptionGroup : null;
        $this->rawResponse    = $response;

        parent::__construct(
            is_string($message) ? $message : 'Unknown API error',
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
