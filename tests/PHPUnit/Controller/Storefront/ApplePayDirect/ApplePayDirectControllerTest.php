<?php
declare(strict_types=1);

namespace MolliePayments\Tests\Controller\Storefront\ApplePayDirect;

use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\FlowBuilderDispatcherAdapterInterface;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\FlowBuilderEventFactory;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\FlowBuilderFactory;
use Kiener\MolliePayments\Components\ApplePayDirect\ApplePayDirect;
use Kiener\MolliePayments\Controller\Storefront\ApplePayDirect\ApplePayDirectControllerBase;
use Kiener\MolliePayments\Service\OrderService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;

#[CoversClass(ApplePayDirectControllerBase::class)]
final class ApplePayDirectControllerTest extends TestCase
{
    public function testCreatePaymentSessionUsesCurrentRequestHost(): void
    {
        $applePay = new TestApplePayDirect();
        $controller = $this->buildController($applePay);

        $context = $this->createMock(SalesChannelContext::class);
        $request = new Request(server: ['HTTP_HOST' => 'shop.example.com:8443'], content: json_encode([
            'validationUrl' => 'https://apple-pay-gateway.apple.com/paymentservices/startSession',
        ]));

        $response = $controller->createPaymentSession($context, $request);

        $this->assertSame('https://apple-pay-gateway.apple.com/paymentservices/startSession', $applePay->validationUrl);
        $this->assertSame('shop.example.com', $applePay->domain);
        $this->assertSame('merchant-session', json_decode((string) $response->getContent(), true)['session']);
    }

    /**
     * Ensures that after prepareCustomer() returns a new context, the sw-context-token header
     * is set on the request so Shopware resolves the correct context in finishPayment().
     */
    public function testStartPaymentSetsContextTokenHeaderFromNewContext(): void
    {
        $newContext = $this->createMock(SalesChannelContext::class);
        $newContext->method('getToken')->willReturn('new-context-token-abc');
        $newContext->method('getSalesChannelId')->willReturn('sales-channel-id-123');

        $applePay = new TestApplePayDirect($newContext);
        $controller = $this->buildController($applePay);

        $oldContext = $this->createMock(SalesChannelContext::class);

        $request = new Request([], [
            'paymentToken' => 'apple-pay-token',
            'email' => 'test@example.com',
            'firstname' => 'John',
            'lastname' => 'Doe',
            'street' => 'Main St 1',
            'postalCode' => '12345',
            'city' => 'Berlin',
            'countryCode' => 'DE',
        ]);

        $controller->startPayment($oldContext, $request);

        $this->assertSame(
            'new-context-token-abc',
            $request->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN),
            'The sw-context-token header must carry the token of the new context from prepareCustomer()'
        );
    }

    /**
     * Ensures finishPayment() receives the same SalesChannelContext (and therefore token)
     * that prepareCustomer() produced – not the original browser-session context.
     */
    public function testFinishPaymentReceivesContextFromPrepareCustomer(): void
    {
        $newContext = $this->createMock(SalesChannelContext::class);
        $newContext->method('getToken')->willReturn('new-context-token-abc');
        $newContext->method('getSalesChannelId')->willReturn('sales-channel-id-123');

        $applePay = new TestApplePayDirect($newContext);
        $controller = $this->buildController($applePay);

        $oldContext = $this->createMock(SalesChannelContext::class);
        $oldContext->method('getToken')->willReturn('old-context-token-xyz');

        $request = new Request([], [
            'paymentToken' => 'apple-pay-token',
            'email' => 'test@example.com',
            'firstname' => 'John',
            'lastname' => 'Doe',
            'street' => 'Main St 1',
            'postalCode' => '12345',
            'city' => 'Berlin',
            'countryCode' => 'DE',
        ]);

        $controller->startPayment($oldContext, $request);

        $forwarded = $controller->lastForwardedAttributes;

        $this->assertArrayHasKey(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, $forwarded);

        /** @var SalesChannelContext $forwardedContext */
        $forwardedContext = $forwarded[PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT];

        $this->assertSame(
            'new-context-token-abc',
            $forwardedContext->getToken(),
            'finishPayment must use the context from prepareCustomer(), not the original browser token'
        );
        $this->assertNotSame('old-context-token-xyz', $forwardedContext->getToken());
    }

    private function buildController(TestApplePayDirect $applePay): TestableApplePayDirectControllerBase
    {
        return new TestableApplePayDirectControllerBase(
            $applePay,
            $this->createMock(RouterInterface::class),
            new NullLogger(),
            $this->createFlowBuilderFactory(),
            $this->createMock(FlowBuilderEventFactory::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(OrderService::class)
        );
    }

    private function createFlowBuilderFactory(): FlowBuilderFactory
    {
        $factory = $this->createMock(FlowBuilderFactory::class);
        $dispatcher = $this->createMock(FlowBuilderDispatcherAdapterInterface::class);
        $factory->method('createDispatcher')->willReturn($dispatcher);

        return $factory;
    }
}

/**
 * Testable subclass that intercepts forwardToRoute() to avoid requiring a full Symfony container.
 */
final class TestableApplePayDirectControllerBase extends ApplePayDirectControllerBase
{
    public array $lastForwardedAttributes = [];

    protected function forwardToRoute(string $routeName, array $attributes = [], array $routeParameters = []): Response
    {
        $this->lastForwardedAttributes = $attributes;

        return new Response();
    }
}

/**
 * Fake ApplePayDirect that captures prepareCustomer calls and returns a pre-configured context.
 */
final class TestApplePayDirect extends ApplePayDirect
{
    public string $validationUrl = '';
    public string $domain = '';

    private ?SalesChannelContext $newContext;

    public function __construct(?SalesChannelContext $newContext = null)
    {
        $this->newContext = $newContext;
    }

    public function createPaymentSession(string $validationURL, string $domain, SalesChannelContext $context): string
    {
        $this->validationUrl = $validationURL;
        $this->domain = $domain;

        return 'merchant-session';
    }

    public function prepareCustomer(
        string $firstname,
        string $lastname,
        string $email,
        string $street,
        string $zipcode,
        string $city,
        string $countryCode,
        string $phone,
        string $paymentToken,
        ?int $acceptedDataProtection,
        SalesChannelContext $context
    ): SalesChannelContext {
        return $this->newContext ?? $context;
    }
}
