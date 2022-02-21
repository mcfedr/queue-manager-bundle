<?php

declare(strict_types=1);

namespace Mcfedr\QueueManagerBundle\Event;

use Mcfedr\QueueManagerBundle\Queue\Job;
use Symfony\Contracts\EventDispatcher\Event;

abstract class JobEvent extends Event
{
    private Job $job;
    private bool $internal;

    public function __construct(Job $job, bool $internal)
    {
        $this->job = $job;
        $this->internal = $internal;
    }

    public function getJob(): Job
    {
        return $this->job;
    }

    public function isInternal(): bool
    {
        return $this->internal;
    }
}
