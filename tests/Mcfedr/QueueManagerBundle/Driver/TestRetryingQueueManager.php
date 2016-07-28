<?php
/**
 * Created by mcfedr on 04/02/2016 10:40
 */

namespace Mcfedr\QueueManagerBundle\Driver;

use Mcfedr\QueueManagerBundle\Exception\NoSuchJobException;
use Mcfedr\QueueManagerBundle\Exception\WrongJobException;
use Mcfedr\QueueManagerBundle\Manager\QueueManager;
use Mcfedr\QueueManagerBundle\Manager\RetryingQueueManager;
use Mcfedr\QueueManagerBundle\Queue\Job;
use Mcfedr\QueueManagerBundle\Queue\RetryableJob;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;

class TestRetryingQueueManager extends TestQueueManager implements RetryingQueueManager
{
    /**
     * Called by RunnerCommand to reschedule a job that failed
     * Responsible for increasing the retryCount in the Job
     *
     * @param RetryableJob $job
     * @param \Exception $exception The exception that caused the job to fail
     * @return mixed
     */
    public function retry(RetryableJob $job, \Exception $exception = null)
    {
        $this->info('Retrying job', [
            'name' => $job->getName()
        ]);
    }

    protected function getLogName()
    {
        return "Retrying Queue";
    }
}
