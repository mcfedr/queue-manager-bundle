<?php
/**
 * Created by mcfedr on 09/06/2016 14:53
 */

namespace Mcfedr\QueueManagerBundle\Manager;

use Mcfedr\QueueManagerBundle\Queue\RetryableJob;

interface RetryingQueueManager extends QueueManager
{
    /**
     * Called by RunnerCommand to reschedule a job that failed
     * Responsible for increasing the retryCount in the Job
     *
     * @param RetryableJob $job
     * @param \Exception $exception The exception that caused the job to fail
     * @return mixed
     */
    public function retry(RetryableJob $job, \Exception $exception = null);
}
