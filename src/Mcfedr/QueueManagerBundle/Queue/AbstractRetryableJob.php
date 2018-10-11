<?php

declare(strict_types=1);

namespace Mcfedr\QueueManagerBundle\Queue;

abstract class AbstractRetryableJob extends AbstractJob implements RetryableJob
{
    /**
     * @var int
     */
    private $retryCount;

    public function __construct($name, array $arguments, $retryCount = 0)
    {
        parent::__construct($name, $arguments);
        $this->retryCount = $retryCount;
    }

    /**
     * Used to count retries.
     *
     * @return int
     */
    public function getRetryCount()
    {
        return $this->retryCount;
    }

    public function incrementRetryCount()
    {
        ++$this->retryCount;
    }
}
