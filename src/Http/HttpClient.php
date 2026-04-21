<?php

declare(strict_types=1);

namespace KeepzSdk\Http;

class HttpClient implements HttpClientInterface
{
    /**
     * @param string $url
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    public function get(string $url, array $query = []): array
    {
        if (!empty($query)) {
            $url .= '?' . http_build_query($query);
        }

        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Accept: application/json',
            ],
        ]);

        $response = curl_exec($ch);

        return json_decode($response, true);
    }

    /**
     * @param string $url
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function post(string $url, array $data): array
    {
        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json'
            ],
            CURLOPT_POSTFIELDS => json_encode($data),
        ]);

        $response = curl_exec($ch);

        return json_decode($response, true);
    }
}