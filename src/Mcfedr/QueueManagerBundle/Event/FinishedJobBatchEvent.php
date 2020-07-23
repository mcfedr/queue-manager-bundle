<?php

declare(strict_types=1);

namespace Mcfedr\QueueManagerBundle\Event;

use Mcfedr\QueueManagerBundle\Queue\Job;
use Symfony\Contracts\EventDispatcher\Event;

class FinishedJobBatchEvent extends Event
{
    /**
     * @var Job[]
     */
    private $oks;

    /**
     * @var Job[]
     */
    private $retries;

    /**
     * @var Job[]
     */
    private $fails;

    /**
     * @var Job[]
     */
    private $outstandingJobs;

    /**
     * FinishedJobBatchEvent constructor.
     *
     * @param Job[] $oks
     * @param Job[] $retries
     * @param Job[] $fails
     * @param Job[] $outstandingJobs
     */
    public function __construct(array $oks, array $retries, array $fails, array $outstandingJobs)
    {
        $this->oks = $oks;
        $this->retries = $retries;
        $this->fails = $fails;
        $this->outstandingJobs = $outstandingJobs;
    }

    /**
     * @return Job[]
     */
    public function getOks(): array
    {
        return $this->oks;
    }

    /**
     * @return Job[]
     */
    public function getRetries(): array
    {
        return $this->retries;
    }

    /**
     * @return Job[]
     */
    public function getFails(): array
    {
        return $this->fails;
    }

    /**
     * @return Job[]
     */
    public function getOutstandingJobs(): array
    {
        return $this->outstandingJobs;
    }
}
