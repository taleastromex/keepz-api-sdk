<?php

declare(strict_types=1);

namespace KeepzSdk\Http;

class HttpClient
{
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