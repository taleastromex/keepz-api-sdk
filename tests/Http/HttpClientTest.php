<?php

declare(strict_types=1);

namespace KeepzSdk\Tests\Http;

use KeepzSdk\Http\HttpClient;
use phpmock\phpunit\PHPMock;
use PHPUnit\Framework\TestCase;

class HttpClientTest extends TestCase
{
    use PHPMock;

    private const NAMESPACE = 'KeepzSdk\Http';

    // -------------------------------------------------------------------------
    // Response decoding
    // -------------------------------------------------------------------------

    public function testPostReturnsDecodedJsonResponse(): void
    {
        $expectedResponse = ['status' => 'ok', 'orderId' => 'abc-123'];

        $this->mockCurl(json_encode($expectedResponse));

        $result = (new HttpClient())->post('https://example.com', ['amount' => 100]);

        $this->assertSame($expectedResponse, $result);
    }

    public function testPostReturnsNestedArrayFromJsonResponse(): void
    {
        $expectedResponse = [
            'encryptedData' => 'base64data==',
            'encryptedKeys' => 'base64keys==',
            'aes'           => true,
        ];

        $this->mockCurl(json_encode($expectedResponse));

        $result = (new HttpClient())->post('https://example.com', []);

        $this->assertSame($expectedResponse, $result);
    }

    // -------------------------------------------------------------------------
    // Request serialisation
    // -------------------------------------------------------------------------

    public function testPostSerializesPayloadAsJson(): void
    {
        $payload = ['amount' => 100, 'currency' => 'GEL'];

        $curlSetopt = $this->getFunctionMock(self::NAMESPACE, 'curl_setopt_array');
        $curlSetopt->expects($this->once())
            ->willReturnCallback(function ($ch, array $options) use ($payload): bool {
                $this->assertSame(json_encode($payload), $options[CURLOPT_POSTFIELDS]);
                return true;
            });

        $this->mockCurlInitAndExec('{}');

        (new HttpClient())->post('https://example.com', $payload);
    }

    public function testPostSetsJsonContentTypeHeader(): void
    {
        $curlSetopt = $this->getFunctionMock(self::NAMESPACE, 'curl_setopt_array');
        $curlSetopt->expects($this->once())
            ->willReturnCallback(function ($ch, array $options): bool {
                $this->assertContains('Content-Type: application/json', $options[CURLOPT_HTTPHEADER]);
                return true;
            });

        $this->mockCurlInitAndExec('{}');

        (new HttpClient())->post('https://example.com', []);
    }

    public function testPostEnablesCurlPostFlag(): void
    {
        $curlSetopt = $this->getFunctionMock(self::NAMESPACE, 'curl_setopt_array');
        $curlSetopt->expects($this->once())
            ->willReturnCallback(function ($ch, array $options): bool {
                $this->assertTrue((bool) $options[CURLOPT_POST]);
                return true;
            });

        $this->mockCurlInitAndExec('{}');

        (new HttpClient())->post('https://example.com', []);
    }

    public function testPostEnablesReturnTransfer(): void
    {
        $curlSetopt = $this->getFunctionMock(self::NAMESPACE, 'curl_setopt_array');
        $curlSetopt->expects($this->once())
            ->willReturnCallback(function ($ch, array $options): bool {
                $this->assertTrue((bool) $options[CURLOPT_RETURNTRANSFER]);
                return true;
            });

        $this->mockCurlInitAndExec('{}');

        (new HttpClient())->post('https://example.com', []);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Mock all three cURL functions for a simple happy-path response.
     */
    private function mockCurl(string $responseBody): void
    {
        $curlInit = $this->getFunctionMock(self::NAMESPACE, 'curl_init');
        $curlInit->expects($this->once())->willReturn(curl_init());

        $curlSetopt = $this->getFunctionMock(self::NAMESPACE, 'curl_setopt_array');
        $curlSetopt->expects($this->once())->willReturn(true);

        $curlExec = $this->getFunctionMock(self::NAMESPACE, 'curl_exec');
        $curlExec->expects($this->once())->willReturn($responseBody);
    }

    /**
     * Mock only curl_init and curl_exec, leaving curl_setopt_array to the assertion test.
     */
    private function mockCurlInitAndExec(string $responseBody): void
    {
        $curlInit = $this->getFunctionMock(self::NAMESPACE, 'curl_init');
        $curlInit->expects($this->once())->willReturn(curl_init());

        $curlExec = $this->getFunctionMock(self::NAMESPACE, 'curl_exec');
        $curlExec->expects($this->once())->willReturn($responseBody);
    }
}
