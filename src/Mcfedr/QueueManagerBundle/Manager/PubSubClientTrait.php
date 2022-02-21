<?php

declare(strict_types=1);

namespace Mcfedr\QueueManagerBundle\Manager;

/**
 * @internal
 */
trait PubSubClientTrait
{
    private array $defaultQueue;

    /**
     * @var array[]
     */
    private array $pubSubQueues;

    private function setOptions(array $options): void
    {
        $this->defaultQueue = ['subscription' => $options['default_subscription'], 'topic' => $options['default_topic']];

        $this->pubSubQueues = $options['pub_sub_queues'];

        if (!\array_key_exists('default', $this->pubSubQueues)) {
            $this->pubSubQueues['default'] = $this->defaultQueue;
        }
    }
}
