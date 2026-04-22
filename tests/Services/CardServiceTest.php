<?php

declare(strict_types=1);

namespace Taleastromex\KeepzApiSdk\Tests\Services;

use Taleastromex\KeepzApiSdk\DTO\SavedCardData;
use Taleastromex\KeepzApiSdk\Exceptions\ApiException;
use Taleastromex\KeepzApiSdk\Http\ApiGateway;
use Taleastromex\KeepzApiSdk\Services\CardService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class CardServiceTest extends TestCase
{
    private const ORDER_ID = 'order-uuid-abc123';

    // -------------------------------------------------------------------------
    // getSavedCard()
    // -------------------------------------------------------------------------

    public function testGetSavedCardGetsFromCorrectPath(): void
    {
        /** @var ApiGateway&MockObject $gateway */
        $gateway = $this->createMock(ApiGateway::class);
        $gateway->expects($this->once())
            ->method('get')
            ->with('/api/v1/integrator/card/order-id', $this->anything())
            ->willReturn($this->fakeCardResponse());

        (new CardService($gateway))->getSavedCard(self::ORDER_ID);
    }

    public function testGetSavedCardForwardsIntegratorOrderId(): void
    {
        /** @var ApiGateway&MockObject $gateway */
        $gateway = $this->createMock(ApiGateway::class);
        $gateway->expects($this->once())
            ->method('get')
            ->with($this->anything(), ['integratorOrderId' => self::ORDER_ID])
            ->willReturn($this->fakeCardResponse());

        (new CardService($gateway))->getSavedCard(self::ORDER_ID);
    }

    public function testGetSavedCardReturnsSavedCardData(): void
    {
        /** @var ApiGateway&MockObject $gateway */
        $gateway = $this->createStub(ApiGateway::class);
        $gateway->method('get')->willReturn($this->fakeCardResponse());

        $result = (new CardService($gateway))->getSavedCard(self::ORDER_ID);

        $this->assertInstanceOf(SavedCardData::class, $result);
    }

    public function testGetSavedCardMapsAllFieldsCorrectly(): void
    {
        /** @var ApiGateway&MockObject $gateway */
        $gateway = $this->createStub(ApiGateway::class);
        $gateway->method('get')->willReturn($this->fakeCardResponse());

        $card = (new CardService($gateway))->getSavedCard(self::ORDER_ID);

        $this->assertSame('token-uuid-xyz', $card->getToken());
        $this->assertSame('CREDO', $card->getProvider());
        $this->assertSame('411111******1111', $card->getCardMask());
        $this->assertSame('12/27', $card->getExpirationDate());
        $this->assertSame('VISA', $card->getCardBrand());
    }

    public function testGetSavedCardPropagatesApiException(): void
    {
        /** @var ApiGateway&MockObject $gateway */
        $gateway = $this->createStub(ApiGateway::class);
        $gateway->method('get')->willThrowException(
            new ApiException(['message' => 'Integrator card not found', 'statusCode' => 6075])
        );

        $this->expectException(ApiException::class);
        $this->expectExceptionCode(6075);

        (new CardService($gateway))->getSavedCard(self::ORDER_ID);
    }

    public function testGetSavedCardThrowsOnMissingResponseFields(): void
    {
        /** @var ApiGateway&MockObject $gateway */
        $gateway = $this->createStub(ApiGateway::class);
        $gateway->method('get')->willReturn([
            'token'    => 'token-uuid-xyz',
            'provider' => 'CREDO',
            // cardMask, expirationDate, cardBrand are intentionally missing
        ]);

        $this->expectException(\InvalidArgumentException::class);

        (new CardService($gateway))->getSavedCard(self::ORDER_ID);
    }

    // -------------------------------------------------------------------------
    // SavedCardData::toArray()
    // -------------------------------------------------------------------------

    public function testSavedCardDataToArray(): void
    {
        $card = SavedCardData::fromArray($this->fakeCardResponse());

        $this->assertSame([
            'token'          => 'token-uuid-xyz',
            'provider'       => 'CREDO',
            'cardMask'       => '411111******1111',
            'expirationDate' => '12/27',
            'cardBrand'      => 'VISA',
        ], $card->toArray());
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /** @return array<string, string> */
    private function fakeCardResponse(): array
    {
        return [
            'token'          => 'token-uuid-xyz',
            'provider'       => 'CREDO',
            'cardMask'       => '411111******1111',
            'expirationDate' => '12/27',
            'cardBrand'      => 'VISA',
        ];
    }
}
