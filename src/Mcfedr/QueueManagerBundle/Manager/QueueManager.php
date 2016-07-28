<?php
/**
 * Created by mcfedr on 21/03/2014 10:58
 */

namespace Mcfedr\QueueManagerBundle\Manager;

use Mcfedr\QueueManagerBundle\Exception\NoSuchJobException;
use Mcfedr\QueueManagerBundle\Exception\WrongJobException;
use Mcfedr\QueueManagerBundle\Queue\Job;

interface QueueManager
{
    /**
     * @param array $options
     */
    public function __construct(array $options);

    /**
     * Put a new job on a queue
     *
     * @param string $name The service name of the worker that implements {@link \Mcfedr\QueueManagerBundle\Queue\Worker}
     * @param array $arguments Arguments to pass to execute - must be json serializable
     * @param array $options Options for creating the job - these depend on the driver used
     * @return Job
     */
    public function put($name, array $arguments = [], array $options = []);

    /**
     * Remove a job from the queue
     *
     * @param $job
     * @throws WrongJobException When this manager doesn't know how to delete the given job
     * @throws NoSuchJobException When this manager is unable to delete the given job
     */
    public function delete(Job $job);
}
