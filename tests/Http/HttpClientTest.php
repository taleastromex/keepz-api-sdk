<?php

declare(strict_types=1);

namespace Taleastromex\KeepzApiSdk\Tests\Http;

use Taleastromex\KeepzApiSdk\Http\HttpClient;
use phpmock\phpunit\PHPMock;
use PHPUnit\Framework\TestCase;

class HttpClientTest extends TestCase
{
    use PHPMock;

    private const NAMESPACE = 'Taleastromex\KeepzApiSdk\Http';

    // -------------------------------------------------------------------------
    // GET — response decoding
    // -------------------------------------------------------------------------

    public function testGetReturnsDecodedJsonResponse(): void
    {
        $expectedResponse = ['status' => 'ok', 'orderId' => 'abc-123'];
        $json = json_encode($expectedResponse);
        assert(is_string($json));

        $this->mockCurl($json);

        $result = (new HttpClient())->get('https://example.com/resource');

        $this->assertSame($expectedResponse, $result);
    }

    public function testGetAppendsQueryStringToUrl(): void
    {
        $curlInit = $this->getFunctionMock(self::NAMESPACE, 'curl_init');
        $curlInit->expects($this->once())
            ->willReturnCallback(function (string $url): mixed {
                $this->assertSame('https://example.com/resource?foo=bar&page=2', $url);
                return curl_init();
            });

        $curlSetopt = $this->getFunctionMock(self::NAMESPACE, 'curl_setopt_array');
        $curlSetopt->expects($this->once())->willReturn(true);

        $curlExec = $this->getFunctionMock(self::NAMESPACE, 'curl_exec');
        $curlExec->expects($this->once())->willReturn('{}');

        (new HttpClient())->get('https://example.com/resource', ['foo' => 'bar', 'page' => 2]);
    }

    public function testGetWithNoQueryDoesNotAppendQuestionMark(): void
    {
        $curlInit = $this->getFunctionMock(self::NAMESPACE, 'curl_init');
        $curlInit->expects($this->once())
            ->willReturnCallback(function (string $url): mixed {
                $this->assertSame('https://example.com/resource', $url);
                return curl_init();
            });

        $curlSetopt = $this->getFunctionMock(self::NAMESPACE, 'curl_setopt_array');
        $curlSetopt->expects($this->once())->willReturn(true);

        $curlExec = $this->getFunctionMock(self::NAMESPACE, 'curl_exec');
        $curlExec->expects($this->once())->willReturn('{}');

        (new HttpClient())->get('https://example.com/resource');
    }

    public function testGetSetsAcceptJsonHeader(): void
    {
        $curlSetopt = $this->getFunctionMock(self::NAMESPACE, 'curl_setopt_array');
        $curlSetopt->expects($this->once())
            ->willReturnCallback(function ($ch, array $options): bool {
                $this->assertContains('Accept: application/json', $options[CURLOPT_HTTPHEADER]);
                return true;
            });

        $this->mockCurlInitAndExec('{}');

        (new HttpClient())->get('https://example.com/resource');
    }

    public function testGetDoesNotSetCurlPostFlag(): void
    {
        $curlSetopt = $this->getFunctionMock(self::NAMESPACE, 'curl_setopt_array');
        $curlSetopt->expects($this->once())
            ->willReturnCallback(function ($ch, array $options): bool {
                $this->assertArrayNotHasKey(CURLOPT_POST, $options);
                return true;
            });

        $this->mockCurlInitAndExec('{}');

        (new HttpClient())->get('https://example.com/resource');
    }

    public function testGetEnablesReturnTransfer(): void
    {
        $curlSetopt = $this->getFunctionMock(self::NAMESPACE, 'curl_setopt_array');
        $curlSetopt->expects($this->once())
            ->willReturnCallback(function ($ch, array $options): bool {
                $this->assertTrue((bool) $options[CURLOPT_RETURNTRANSFER]);
                return true;
            });

        $this->mockCurlInitAndExec('{}');

        (new HttpClient())->get('https://example.com/resource');
    }

    // -------------------------------------------------------------------------
    // POST — response decoding
    // -------------------------------------------------------------------------

    public function testPostReturnsDecodedJsonResponse(): void
    {
        $expectedResponse = ['status' => 'ok', 'orderId' => 'abc-123'];
        $json = json_encode($expectedResponse);
        assert(is_string($json));

        $this->mockCurl($json);

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
        $json = json_encode($expectedResponse);
        assert(is_string($json));

        $this->mockCurl($json);

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
    // DELETE
    // -------------------------------------------------------------------------

    public function testDeleteReturnsDecodedJsonResponse(): void
    {
        $expectedResponse = ['status' => 'deleted'];
        $json = json_encode($expectedResponse);
        assert(is_string($json));

        $this->mockCurl($json);

        $result = (new HttpClient())->delete('https://example.com/resource/123');

        $this->assertSame($expectedResponse, $result);
    }

    public function testDeleteSetsCustomRequestToDelete(): void
    {
        $curlSetopt = $this->getFunctionMock(self::NAMESPACE, 'curl_setopt_array');
        $curlSetopt->expects($this->once())
            ->willReturnCallback(function ($ch, array $options): bool {
                $this->assertSame('DELETE', $options[CURLOPT_CUSTOMREQUEST]);
                return true;
            });

        $this->mockCurlInitAndExec('{}');

        (new HttpClient())->delete('https://example.com/resource/123');
    }

    public function testDeleteAppendsQueryStringToUrl(): void
    {
        $curlInit = $this->getFunctionMock(self::NAMESPACE, 'curl_init');
        $curlInit->expects($this->once())
            ->willReturnCallback(function (string $url): mixed {
                $this->assertSame('https://example.com/resource/123?identifier=abc&aes=1', $url);
                return curl_init();
            });

        $curlSetopt = $this->getFunctionMock(self::NAMESPACE, 'curl_setopt_array');
        $curlSetopt->expects($this->once())->willReturn(true);

        $curlExec = $this->getFunctionMock(self::NAMESPACE, 'curl_exec');
        $curlExec->expects($this->once())->willReturn('{}');

        (new HttpClient())->delete('https://example.com/resource/123', ['identifier' => 'abc', 'aes' => true]);
    }

    public function testDeleteWithNoQueryDoesNotAppendQuestionMark(): void
    {
        $curlInit = $this->getFunctionMock(self::NAMESPACE, 'curl_init');
        $curlInit->expects($this->once())
            ->willReturnCallback(function (string $url): mixed {
                $this->assertSame('https://example.com/resource/123', $url);
                return curl_init();
            });

        $curlSetopt = $this->getFunctionMock(self::NAMESPACE, 'curl_setopt_array');
        $curlSetopt->expects($this->once())->willReturn(true);

        $curlExec = $this->getFunctionMock(self::NAMESPACE, 'curl_exec');
        $curlExec->expects($this->once())->willReturn('{}');

        (new HttpClient())->delete('https://example.com/resource/123');
    }

    public function testDeleteDoesNotSetPostFields(): void
    {
        $curlSetopt = $this->getFunctionMock(self::NAMESPACE, 'curl_setopt_array');
        $curlSetopt->expects($this->once())
            ->willReturnCallback(function ($ch, array $options): bool {
                $this->assertArrayNotHasKey(CURLOPT_POSTFIELDS, $options);
                return true;
            });

        $this->mockCurlInitAndExec('{}');

        (new HttpClient())->delete('https://example.com/resource/123', ['foo' => 'bar']);
    }

    public function testDeleteEnablesReturnTransfer(): void
    {
        $curlSetopt = $this->getFunctionMock(self::NAMESPACE, 'curl_setopt_array');
        $curlSetopt->expects($this->once())
            ->willReturnCallback(function ($ch, array $options): bool {
                $this->assertTrue((bool) $options[CURLOPT_RETURNTRANSFER]);
                return true;
            });

        $this->mockCurlInitAndExec('{}');

        (new HttpClient())->delete('https://example.com/resource/123');
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
