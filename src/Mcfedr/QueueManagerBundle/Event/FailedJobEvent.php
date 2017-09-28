<?php

namespace Mcfedr\QueueManagerBundle\Event;

use Mcfedr\QueueManagerBundle\Queue\Job;

class FailedJobEvent extends JobEvent
{
    /**
     * @var \Exception
     */
    private $exception;

    /**
     * @param Job        $job
     * @param \Exception $exception
     * @param bool       $internal
     */
    public function __construct(Job $job, \Exception $exception, $internal)
    {
        parent::__construct($job, $internal);
        $this->exception = $exception;
    }

    /**
     * @return \Exception
     */
    public function getException()
    {
        return $this->exception;
    }
}
