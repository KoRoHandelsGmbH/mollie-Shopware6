<?php
declare(strict_types=1);

namespace MolliePayments\Tests\Components\PaypalExpress;

use Kiener\MolliePayments\Components\PaypalExpress\Route\FinishCheckoutResponse;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Struct\ArrayStruct;

/**
 * @covers \Kiener\MolliePayments\Components\PaypalExpress\Route\FinishCheckoutResponse
 */
class FinishCheckoutResponseTest extends TestCase
{
    public function testResponseContainsTokenInGetterAndPayload(): void
    {
        $response = new FinishCheckoutResponse('sess-123', 'auth-456', 'token-789');

        /** @var ArrayStruct<array{sessionId:string,authenticateId:string,token:string}> $object */
        $object = $response->getObject();

        $this->assertSame('sess-123', $response->getSessionId());
        $this->assertSame('auth-456', $response->getAuthenticateId());
        $this->assertSame('token-789', $response->getContextToken());
        $this->assertSame('sess-123', $object['sessionId']);
        $this->assertSame('auth-456', $object['authenticateId']);
        $this->assertSame('token-789', $object['token']);
    }
}

