<?php

declare(strict_types=1);

namespace Mcfedr\QueueManagerBundle\RunnerCommand;

use Mcfedr\QueueManagerBundle\Command\RunnerCommand;
use Mcfedr\QueueManagerBundle\Queue\JobBatch;
use Mcfedr\QueueManagerBundle\Queue\TestJob;
use Mcfedr\QueueManagerBundle\Queue\TestRetryableJob;
use Mcfedr\QueueManagerBundle\Runner\JobExecutor;
use Psr\Log\LoggerInterface;

class TestRunnerCommand extends RunnerCommand
{
    private array $options;

    public function __construct($name, array $options, JobExecutor $jobExecutor, ?LoggerInterface $logger = null)
    {
        parent::__construct($name, $options, $jobExecutor, $logger);
        $this->options = $options;
    }

    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * @throws \Exception
     */
    protected function getJobs(): JobBatch
    {
        if (1 === random_int(1, 2)) {
            return new JobBatch([new TestRetryableJob('test_worker', [])]);
        }

        return new JobBatch([new TestJob('test_worker', [])]);
    }

    protected function finishJobs(JobBatch $batch): void {}
}
