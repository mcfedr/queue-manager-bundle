<?php

declare(strict_types=1);

namespace Mcfedr\QueueManagerBundle\Command;

use Mcfedr\QueueManagerBundle\Queue\JobBatch;
use Mcfedr\QueueManagerBundle\Queue\TestJob;
use Mcfedr\QueueManagerBundle\Runner\JobExecutor;
use Mcfedr\QueueManagerBundle\Worker\OomWorker;
use Psr\Log\LoggerInterface;

class OomRunnerCommand extends RunnerCommand
{
    private $options;

    private $once = true;

    public function __construct($name, array $options, JobExecutor $jobExecutor, ?LoggerInterface $logger = null)
    {
        parent::__construct($name, $options, $jobExecutor, $logger);
        $this->options = $options;
    }

    public function getOptions(): array
    {
        return $this->options;
    }

    protected function getJobs(): ?JobBatch
    {
        if ($this->once) {
            $this->once = false;

            return new JobBatch([new TestJob(OomWorker::class, [])]);
        }

        return null;
    }

    protected function finishJobs(JobBatch $batch): void
    {
        $this->logger->info('Finished batch', [
            'batch' => $batch,
        ]);
    }
}
