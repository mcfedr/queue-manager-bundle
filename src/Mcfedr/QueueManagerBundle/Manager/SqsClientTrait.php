<?php

declare(strict_types=1);

namespace Mcfedr\QueueManagerBundle\Manager;

/**
 * @internal
 */
trait SqsClientTrait
{
    /**
     * @var string
     */
    private $defaultUrl;

    /**
     * @var string[]
     */
    private $queues;

    private function setOptions(array $options): void
    {
        $this->defaultUrl = $options['default_url'];
        $this->queues = $options['queues'];
        if (!\array_key_exists('default', $this->queues)) {
            $this->queues['default'] = $this->defaultUrl;
        }
    }
}
