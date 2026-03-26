<?php
declare(strict_types=1);

namespace MolliePayments\Tests\Controller\StoreApi\ApplePayDirect\Response;

use Kiener\MolliePayments\Controller\StoreApi\ApplePayDirect\Response\PaymentResponse;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Struct\ArrayStruct;

/**
 * @covers \Kiener\MolliePayments\Controller\StoreApi\ApplePayDirect\Response\PaymentResponse
 */
class PaymentResponseTest extends TestCase
{
    public function testSuccessResponseContainsToken(): void
    {
        $response = new PaymentResponse(true, 'https://example.com/finish', '', 'order-id-123', 'session-token-abc');

        /** @var ArrayStruct<array{success:bool,url:string,message:string,orderId:string,token:string|null}> $object */
        $object = $response->getObject();

        $this->assertTrue($object['success']);
        $this->assertSame('https://example.com/finish', $object['url']);
        $this->assertSame('order-id-123', $object['orderId']);
        $this->assertSame('session-token-abc', $object['token']);
    }

    public function testErrorResponseHasNullToken(): void
    {
        $response = new PaymentResponse(false, 'https://example.com/error', 'Something went wrong', '', null);

        /** @var ArrayStruct<array{success:bool,url:string,message:string,orderId:string,token:string|null}> $object */
        $object = $response->getObject();

        $this->assertFalse($object['success']);
        $this->assertSame('https://example.com/error', $object['url']);
        $this->assertSame('Something went wrong', $object['message']);
        $this->assertNull($object['token']);
    }

    public function testTokenIsOptionalAndDefaultsToNull(): void
    {
        $response = new PaymentResponse(true, 'https://example.com/finish', '', 'order-id-123');

        /** @var ArrayStruct<array{success:bool,url:string,message:string,orderId:string,token:string|null}> $object */
        $object = $response->getObject();

        $this->assertNull($object['token']);
    }
}
