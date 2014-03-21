<?php
/**
 * Created by mcfedr on 21/03/2014 10:58
 */

namespace mcfedr\Queue\QueueManagerBundle\Manager;

use mcfedr\Queue\QueueManagerBundle\Exception\WrongJobException;
use mcfedr\Queue\QueueManagerBundle\Queue\Job;

interface QueueManager
{
    /**
     * @param array $options
     */
    public function __construct(array $options);

    /**
     * Put a new job on a queue
     *
     * @param string $jobData
     * @param string $queue optional queue name, otherwise the default queue will be used
     * @param int $priority
     * @param \DateTime $when Optionally set a time in the future when this task should happen
     * @return Job
     */
    public function put($jobData, $queue = null, $priority = null, $when = null);

    /**
     * Get the next job from the queue
     *
     * @param string $queue optional queue name, otherwise the default queue will be used
     * @return Job
     */
    public function get($queue = null);

    /**
     * Remove a job, you should call this when you have finished processing a job
     *
     * @param $job
     * @throws WrongJobException
     */
    public function delete(Job $job);
}
