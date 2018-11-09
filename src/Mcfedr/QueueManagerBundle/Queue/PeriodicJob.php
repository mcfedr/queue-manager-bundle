<?php

declare(strict_types=1);

namespace Mcfedr\QueueManagerBundle\Queue;

use Ramsey\Uuid\Uuid;

class PeriodicJob extends AbstractJob
{
    /**
     * @var array
     */
    private $jobTokens;

    public function __construct(string $name, array $arguments, array $jobTokens)
    {
        parent::__construct($name, $arguments);
        $this->jobTokens = $jobTokens;
    }

    /**
     * Generate tokens for a new job.
     */
    public static function generateJobTokens(): array
    {
        return [
            'token' => Uuid::uuid4()->toString(),
            'next_token' => Uuid::uuid4()->toString(),
        ];
    }

    public function getJobToken(): string
    {
        return $this->jobTokens['token'];
    }

    public function getArguments(): array
    {
        return array_merge(parent::getArguments(), ['job_tokens' => $this->getJobTokens()]);
    }

    /**
     * Get the next run of this job.
     */
    public function generateNextJob(): self
    {
        $tokens = $this->getJobTokens();
        $tokens['token'] = $tokens['next_token'];
        $tokens['next_token'] = Uuid::uuid4()->toString();

        return new self($this->getName(), $this->getArguments(), $tokens);
    }

    public function getJobTokens(): array
    {
        return $this->jobTokens;
    }
}
