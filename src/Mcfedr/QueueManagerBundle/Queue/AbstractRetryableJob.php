<?php

declare(strict_types=1);

namespace Mcfedr\QueueManagerBundle\Queue;

abstract class AbstractRetryableJob extends AbstractJob implements RetryableJob
{
    /**
     * @var int
     */
    private $retryCount;

    public function __construct(string $name, array $arguments, int $retryCount = 0)
    {
        parent::__construct($name, $arguments);
        $this->retryCount = $retryCount;
    }

    /**
     * Used to count retries.
     */
    public function getRetryCount(): int
    {
        return $this->retryCount;
    }

    public function incrementRetryCount()
    {
        ++$this->retryCount;
    }
}
