<?php

declare(strict_types=1);

namespace Mcfedr\QueueManagerBundle\Queue;

use Mcfedr\QueueManagerBundle\Exception\UnrecoverableJobExceptionInterface;

interface Worker
{
    /**
     * Called to start the queued task.
     *
     * @param array $arguments
     *
     * @throws \Exception
     * @throws UnrecoverableJobExceptionInterface This job should not be retried
     */
    public function execute(array $arguments);
}
