<?php

namespace Mcfedr\QueueManagerBundle\Event;

use Mcfedr\QueueManagerBundle\Queue\Job;
use Symfony\Component\EventDispatcher\Event;

abstract class JobEvent extends Event
{
    /**
     * @var Job
     */
    private $job;

    /**
     * @var bool
     */
    private $internal;

    /**
     * @param Job  $job
     * @param bool $internal
     */
    public function __construct(Job $job, $internal)
    {
        $this->job = $job;
        $this->internal = $internal;
    }

    /**
     * @return Job
     */
    public function getJob()
    {
        return $this->job;
    }

    /**
     * @return bool
     */
    public function isInternal()
    {
        return $this->internal;
    }
}
