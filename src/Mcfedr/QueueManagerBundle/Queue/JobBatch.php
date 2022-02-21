<?php

declare(strict_types=1);

namespace Mcfedr\QueueManagerBundle\Queue;

use Mcfedr\QueueManagerBundle\Exception\UnrecoverableJobExceptionInterface;

class JobBatch implements \Countable
{
    /**
     * @var Job[]
     */
    private array $jobs;

    /**
     * @var Job[]
     */
    private array $oks;

    /**
     * @var Job[]
     */
    private array $fails;

    /**
     * @var Job[]
     */
    private array $retries;

    private ?Job $currentJob;

    private array $options = [];

    /**
     * @param Job[] $jobs
     * @param Job[] $oks
     * @param Job[] $fails
     * @param Job[] $retries
     */
    public function __construct(array $jobs = [], array $oks = [], array $fails = [], array $retries = [], array $options = [])
    {
        $this->jobs = $jobs;
        $this->oks = $oks;
        $this->fails = $fails;
        $this->retries = $retries;
        $this->options = $options;
    }

    public function next(): ?Job
    {
        $this->currentJob = array_shift($this->jobs);

        return $this->currentJob;
    }

    public function result(?\Throwable $result): void
    {
        if (!$this->currentJob) {
            throw new \LogicException('Tried to set a result when no current job');
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

    public function getOption(string $option): ?string
    {
        return $this->options[$option] ?? null;
    }
}
