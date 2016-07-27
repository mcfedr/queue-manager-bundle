<?php
/**
 * Created by mcfedr on 04/02/2016 10:40
 */

namespace Mcfedr\QueueManagerBundle\Driver;

use Mcfedr\QueueManagerBundle\Exception\NoSuchJobException;
use Mcfedr\QueueManagerBundle\Exception\WrongJobException;
use Mcfedr\QueueManagerBundle\Manager\QueueManager;
use Mcfedr\QueueManagerBundle\Queue\Job;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;

class TestQueueManager implements QueueManager, ContainerAwareInterface
{
    use ContainerAwareTrait;

    private $options;

    /**
     * @param array $options
     */
    public function __construct(array $options)
    {
        $this->options = $options;
    }

    /**
     * @return array
     */
    public function getOptions()
    {
        return $this->options;
    }

    /**
     * Put a new job on a queue
     *
     * @param string $name The service name of the worker that implements {@link \Mcfedr\QueueManagerBundle\Queue\Worker}
     * @param array $arguments Arguments to pass to execute - must be json serializable
     * @param array $options Options for creating the job
     * @return Job
     */
    public function put($name, array $arguments = [], array $options = [])
    {
        $this->info('Putting new job', [
            'name' => $name
        ]);
    }

    /**
     * Remove a job, you should call this when you have finished processing a job
     *
     * @param $job
     * @throws WrongJobException
     * @throws NoSuchJobException
     */
    public function delete(Job $job)
    {
        $this->info('Deleting job', [
            'name' => $job->getName()
        ]);
    }

    protected function info($message, $context)
    {
        $this->container->get('logger')->info("{$this->getLogName()}: $message", $context);
    }

    protected function getLogName()
    {
        return "QueueManager";
    }
}
