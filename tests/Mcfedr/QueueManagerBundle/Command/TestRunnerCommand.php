<?php

namespace Mcfedr\QueueManagerBundle\Command;

use Mcfedr\QueueManagerBundle\Manager\QueueManager;
use Mcfedr\QueueManagerBundle\Queue\Job;
use Mcfedr\QueueManagerBundle\Queue\TestJob;
use Mcfedr\QueueManagerBundle\Queue\TestRetryableJob;

class TestRunnerCommand extends RunnerCommand
{
    private $options;

    public function __construct($name, array $options, QueueManager $queueManager)
    {
        parent::__construct($name, $options, $queueManager);
        $this->options = $options;
    }

    /**
     * @return array
     */
    public function getOptions()
    {
        return $this->options;
    }

    protected function getJobs()
    {
        if (rand(1, 2) == 1) {
            return [new TestRetryableJob('test_worker', [], [])];
        }

        return [new TestJob('test_worker', [], [])];
    }

    /**
     * Called after a batch of jobs finishes.
     *
     * @param Job[] $okJobs
     * @param Job[] $failedJobs
     * @param Job[] $retryJobs
     */
    protected function finishJobs(array $okJobs, array $failedJobs, array $retryJobs)
    {
    }
}
