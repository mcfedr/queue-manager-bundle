<?php

declare(strict_types=1);

namespace Mcfedr\QueueManagerBundle\Command;

use Mcfedr\QueueManagerBundle\Queue\Job;
use Mcfedr\QueueManagerBundle\Queue\TestJob;
use Mcfedr\QueueManagerBundle\Queue\TestRetryableJob;
use Mcfedr\QueueManagerBundle\Runner\JobExecutor;
use Psr\Log\LoggerInterface;

class TestRunnerCommand extends RunnerCommand
{
    private $options;

    public function __construct($name, array $options, JobExecutor $jobExecutor, ?LoggerInterface $logger = null)
    {
        parent::__construct($name, $options, $jobExecutor, $logger);
        $this->options = $options;
    }

    /**
     * @return array
     */
    public function getOptions()
    {
        return $this->options;
    }

    protected function getJobs(): array
    {
        if (1 == rand(1, 2)) {
            return [new TestRetryableJob('test_worker', [], [])];
        }

        return [new TestJob('test_worker', [])];
    }

    /**
     * Called after a batch of jobs finishes.
     *
     * @param Job[] $okJobs
     * @param Job[] $failedJobs
     * @param Job[] $retryJobs
     */
    protected function finishJobs(array $okJobs, array $failedJobs, array $retryJobs): void
    {
    }
}
