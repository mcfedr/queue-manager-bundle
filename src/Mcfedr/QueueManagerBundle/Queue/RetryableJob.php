<?php

declare(strict_types=1);

namespace Mcfedr\QueueManagerBundle\Queue;

interface RetryableJob extends Job
{
    /**
     * Used to count retries.
     */
    public function getRetryCount(): int;
}
