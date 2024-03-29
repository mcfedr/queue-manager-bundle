<?php

declare(strict_types=1);

namespace Mcfedr\QueueManagerBundle\Manager;

/**
 * @internal
 */
trait PheanstalkClientTrait
{
    private string $defaultQueue;

    private function setOptions(array $options): void
    {
        $this->defaultQueue = $options['default_queue'];
    }
}
