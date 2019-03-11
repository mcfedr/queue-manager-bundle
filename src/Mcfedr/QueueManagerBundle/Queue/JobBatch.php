<?php

declare(strict_types=1);

namespace Mcfedr\QueueManagerBundle\Queue;

use Mcfedr\QueueManagerBundle\Exception\UnrecoverableJobExceptionInterface;

class JobBatch implements \Countable
{
    /**
     * @var Job[]
     */
    private $jobs;

    /**
     * @var Job[]
     */
    private $oks = [];

    /**
     * @var Job[]
     */
    private $fails = [];

    /**
     * @var Job[]
     */
    private $retries = [];

    /**
     * @var Job
     */
    private $currentJob;

    /**
     * @param Job[] $jobs
     */
    public function __construct(array $jobs)
    {
        $this->jobs = $jobs;
    }

    public function next()
    {
        $this->currentJob = array_pop($this->jobs);

        return $this->currentJob;
    }

    public function result(?\Throwable $result): void
    {
        if (!$this->currentJob) {
            return;
        }

        if (!$result) {
            $this->oks[] = $this->currentJob;
        } elseif ($result instanceof UnrecoverableJobExceptionInterface) {
            $this->fails[] = $this->currentJob;
        } else {
            $this->retries[] = $this->currentJob;
        }
        $this->currentJob = null;
    }

    public function count(): int
    {
        return \count($this->jobs);
    }

    public function current(): ?Job
    {
        return $this->currentJob;
    }

    /**
     * @return Job[]
     */
    public function getJobs(): array
    {
        return $this->jobs;
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
    public function getFails(): array
    {
        return $this->fails;
    }

    /**
     * @return Job[]
     */
    public function getRetries(): array
    {
        return $this->retries;
    }
}
