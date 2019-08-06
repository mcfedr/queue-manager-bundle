<?php

declare(strict_types=1);

namespace Mcfedr\QueueManagerBundle\Manager;

/**
 * @internal
 */
trait PubSubClientTrait
{
    /**
     * @var array
     */
    private $defaultQueue;

    /**
     * @var array[]
     */
    private $queues;

    private function setOptions(array $options): void
    {
        $this->defaultQueue = [$options['default_subscription'] => $options['default_topic']];

        $this->queues = array_map(function ($topic) {
            return [$topic['subscription'] => $topic['topic']];
        }, $options['pub_sub_queues']);

        if (!\array_key_exists('default', $this->queues)) {
            $this->queues['default'] = $this->defaultQueue;
        }
    }
}
