<?php

declare(strict_types=1);

namespace Mcfedr\QueueManagerBundle\Queue;

use Mcfedr\QueueManagerBundle\Exception\UnrecoverableJobExceptionInterface;

interface Worker
{
    /**
     * Called to start the queued task.
     *
     * @throws \Exception
     * @throws UnrecoverableJobExceptionInterface This job should not be retried
     */
    public function execute(array $arguments): void;

    // Optional method that replaces the default worker name
    // public static function getName(): string
}
