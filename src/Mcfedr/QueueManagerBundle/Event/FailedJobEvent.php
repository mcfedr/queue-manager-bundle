<?php

declare(strict_types=1);

namespace Mcfedr\QueueManagerBundle\Event;

use Mcfedr\QueueManagerBundle\Queue\Job;

class FailedJobEvent extends JobEvent
{
    private \Throwable $exception;

    public function __construct(Job $job, \Throwable $exception, bool $internal)
    {
        parent::__construct($job, $internal);
        $this->exception = $exception;
    }

    public function getException(): \Throwable
    {
        return $this->exception;
    }
}
