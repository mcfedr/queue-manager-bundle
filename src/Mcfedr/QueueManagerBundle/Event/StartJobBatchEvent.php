<?php

namespace Mcfedr\QueueManagerBundle\Event;

use Mcfedr\QueueManagerBundle\Queue\Job;
use Symfony\Component\EventDispatcher\Event;

class StartJobBatchEvent extends Event
{
    /**
     * @var Job[]
     */
    private $jobs;

    /**
     * StartJobBatchEvent constructor.
     *
     * @param Job[] $jobs
     */
    public function __construct(array $jobs)
    {
        $this->jobs = $jobs;
    }

    /**
     * @return Job[]
     */
    public function getJobs()
    {
        return $this->jobs;
    }
}
