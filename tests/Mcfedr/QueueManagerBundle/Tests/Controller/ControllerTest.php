<?php

declare(strict_types=1);

namespace Mcfedr\QueueManagerBundle\Tests\Controller;

use Google\Auth\AccessToken;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * @internal
 */
final class ControllerTest extends WebTestCase
{
    /** @var KernelBrowser */
    protected $client;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();
        $this->client = static::createClient([], ['HTTPS' => true]);
        $this->client->followRedirects(false);
    }

    public function testController(): void
    {
        $accessTokenMock = $this->getMockBuilder(AccessToken::class)->onlyMethods(['verify'])->getMock();
        $accessTokenMock->method('verify')->willReturn(['payload']);
        static::$container->set(AccessToken::class, $accessTokenMock);
        $this->client->request('POST', '/pubsub', [], [], ['HTTP_AUTHORIZATION' => 'Bearer token'], '{"message":{"data":"eyJuYW1lIjoid29ya2VyX3dpdGhfYV9uYW1lIiwiYXJndW1lbnRzIjpbeyJmb28iOiAiYmFyIn1dLCJyZXRyeUNvdW50IjowfQ==","messageId":"1748167746376305","message_id":"1748167746376305","publishTime":"2020-11-19T15:10:18.439Z","publish_time":"2020-11-19T15:10:18.439Z"},"subscription":"projects/project/subscriptions/subscriptions"}');

        static::assertEquals(200, $this->client->getResponse()->getStatusCode());
    }
}
