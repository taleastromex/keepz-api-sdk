<?php

declare(strict_types=1);

namespace Taleastromex\KeepzApiSdk\DTO;

final class OrderStatusData
{
    /** @var string */
    private $integratorOrderId;

    /** @var string */
    private $status;

    public function __construct(
        string $integratorOrderId,
        string $status
    ) {
        $this->integratorOrderId = $integratorOrderId;
        $this->status = $status;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $integratorOrderId = $data['integratorOrderId'] ?? null;
        $status = $data['status'] ?? null;

        if (!is_string($integratorOrderId) || !is_string($status)) {
            throw new \InvalidArgumentException(
                'Response is missing required fields: integratorOrderId, status'
            );
        }

        return new self($integratorOrderId, $status);
    }

    public function getIntegratorOrderId(): string
    {
        return $this->integratorOrderId;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            'integrator_order_id' => $this->integratorOrderId,
            'status' => $this->status,
        ];
    }
}