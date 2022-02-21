<?php

declare(strict_types=1);

namespace Mcfedr\QueueManagerBundle\Queue;

use Pheanstalk\Job;

class BeanstalkJob extends AbstractRetryableJob
{
    private ?int $id;
    private ?Job $job;
    private int $priority;
    private int $ttr;

    public function __construct(string $name, array $arguments, int $priority, int $ttr, ?int $id = null, int $retryCount = 0, ?Job $job = null)
    {
        parent::__construct($name, $arguments, $retryCount);
        $this->id = $id;
        $this->job = $job;
        $this->priority = $priority;
        $this->ttr = $ttr;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function getTtr(): int
    {
        return $this->ttr;
    }

    public function getJob(): ?Job
    {
        return $this->job;
    }

    public function getData(): string
    {
        return json_encode([
            'name' => $this->getName(),
            'arguments' => $this->getArguments(),
            'retryCount' => $this->getRetryCount(),
            'priority' => $this->getPriority(),
            'ttr' => $this->getTtr(),
        ]);
    }
}
