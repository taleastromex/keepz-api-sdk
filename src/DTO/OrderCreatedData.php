<?php

declare(strict_types=1);

namespace KeepzSdk\DTO;

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