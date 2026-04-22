<?php

declare(strict_types=1);

namespace Taleastromex\KeepzApiSdk\DTO;

final class OrderCreatedData
{
    /** @var string */
    private $integratorOrderId;

    /** @var string */
    private $urlForQR;

    public function __construct(
        string $integratorOrderId,
        string $urlForQR
    ) {
        $this->integratorOrderId = $integratorOrderId;
        $this->urlForQR = $urlForQR;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $integratorOrderId = $data['integratorOrderId'] ?? null;
        $urlForQR = $data['urlForQR'] ?? null;

        if (!is_string($integratorOrderId) || !is_string($urlForQR)) {
            throw new \InvalidArgumentException(
                'Response is missing required fields: integratorOrderId, urlForQR'
            );
        }

        return new self($integratorOrderId, $urlForQR);
    }

    public function getIntegratorOrderId(): string
    {
        return $this->integratorOrderId;
    }

    public function getUrlForQR(): string
    {
        return $this->urlForQR;
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            'integrator_order_id' => $this->integratorOrderId,
            'url_for_qr' => $this->urlForQR,
        ];
    }
}