<?php

declare(strict_types=1);

namespace KeepzSdk\Http;

interface HttpClientInterface
{
    /**
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    public function get(string $url, array $query = []): array;

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function post(string $url, array $data): array;
}
