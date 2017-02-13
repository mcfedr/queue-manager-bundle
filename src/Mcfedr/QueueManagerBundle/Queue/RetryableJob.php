<?php

namespace Mcfedr\QueueManagerBundle\Queue;

interface RetryableJob extends Job
{
    /**
     * Used to count retries.
     *
     * @return int
     */
    public function getRetryCount();
}
