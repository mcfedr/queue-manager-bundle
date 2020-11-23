<?php

declare(strict_types=1);

namespace Mcfedr\QueueManagerBundle\Tests\Controller;

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
        $this->client->request('POST', '/pubsub', [], [], [], '{"message":{"data":"eyJuYW1lIjoid29ya2VyX3dpdGhfYV9uYW1lIiwiYXJndW1lbnRzIjpbeyJmb28iOiAiYmFyIn1dLCJyZXRyeUNvdW50IjowfQ==","messageId":"1748167746376305","message_id":"1748167746376305","publishTime":"2020-11-19T15:10:18.439Z","publish_time":"2020-11-19T15:10:18.439Z"},"subscription":"projects/project/subscriptions/subscriptions"}');

        static::assertEquals(200, $this->client->getResponse()->getStatusCode());
    }
}
