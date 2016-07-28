<?php
/**
 * Created by mcfedr on 09/06/2016 14:53
 */

namespace Mcfedr\QueueManagerBundle\Manager;

use Mcfedr\QueueManagerBundle\Exception\WrongJobException;
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
     * @throws WrongJobException
     */
    public function retry(RetryableJob $job, \Exception $exception = null);
}
