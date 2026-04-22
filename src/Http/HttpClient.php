<?php

declare(strict_types=1);

namespace Taleastromex\KeepzApiSdk\Http;

final class HttpClient implements HttpClientInterface
{
    /**
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

        return $this->decodeResponse($response);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function post(string $url, array $data): array
    {
        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS     => json_encode($data),
        ]);

        $response = curl_exec($ch);

        return $this->decodeResponse($response);
    }

    /**
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    public function delete(string $url, array $query = []): array
    {
        /**
         * @see documentation https://www.developers.keepz.me/eCommerece%20integration/cancel_order#request-details
         */
        if (!empty($query)) {
            $url .= '?' . http_build_query($query);
        }

        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => 'DELETE',
        ]);

        $response = curl_exec($ch);

        return $this->decodeResponse($response);
    }

    /**
     * @param string|bool $response
     * @return array<string, mixed>
     */
    private function decodeResponse($response): array
    {
        if (!is_string($response)) {
            throw new \RuntimeException('cURL request failed: ' . curl_error(curl_init()));
        }

        $decoded = json_decode($response, true);

        if (!is_array($decoded)) {
            throw new \RuntimeException('Failed to decode API response as JSON');
        }

        return $decoded;
    }
}
