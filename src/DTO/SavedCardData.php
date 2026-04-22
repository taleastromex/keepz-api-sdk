<?php

namespace KeepzSdk\DTO;

final class SavedCardData
{
    /** @var string */
    private $token;

    /** @var string */
    private $provider;

    /** @var string */
    private $cardMask;

    /** @var string */
    private $expirationDate;

    /** @var string */
    private $cardBrand;

    public function __construct(
        string $token,
        string $provider,
        string $cardMask,
        string $expirationDate,
        string $cardBrand
    ) {
        $this->token = $token;
        $this->provider = $provider;
        $this->cardMask = $cardMask;
        $this->expirationDate = $expirationDate;
        $this->cardBrand = $cardBrand;
    }

    /**
     * @param array<string, string> $data
     */
    public static function fromArray(array $data): self
    {
        $provider = $data['provider'] ?? null;
        $token = $data['token'] ?? null;
        $cardMask = $data['cardMask'] ?? null;
        $expirationDate = $data['expirationDate'] ?? null;
        $cardBrand = $data['cardBrand'] ?? null;

        if (
            !is_string($token)
            || !is_string($provider)
            || !is_string($cardMask)
            || !is_string($expirationDate)
            || !is_string($cardBrand)
        ) {
            throw new \InvalidArgumentException(
                'Response is missing required fields: provider, token, cardMask, expirationDate, cardBrand'
            );
        }

        return new self($token, $provider, $cardMask, $expirationDate, $cardBrand);
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function getProvider(): string
    {
        return $this->provider;
    }

    public function getCardMask(): string
    {
        return $this->cardMask;
    }

    public function getExpirationDate(): string
    {
        return $this->expirationDate;
    }

    public function getCardBrand(): string
    {
        return $this->cardBrand;
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            'token' => $this->token,
            'provider' => $this->provider,
            'cardMask' => $this->cardMask,
            'expirationDate' => $this->expirationDate,
            'cardBrand' => $this->cardBrand,
        ];
    }
}