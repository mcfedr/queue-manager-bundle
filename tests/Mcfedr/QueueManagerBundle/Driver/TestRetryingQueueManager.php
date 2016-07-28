<?php
/**
 * Created by mcfedr on 04/02/2016 10:40
 */

namespace Mcfedr\QueueManagerBundle\Driver;

use Mcfedr\QueueManagerBundle\Manager\RetryingQueueManager;
use Mcfedr\QueueManagerBundle\Queue\RetryableJob;

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
