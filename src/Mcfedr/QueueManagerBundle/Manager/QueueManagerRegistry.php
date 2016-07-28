<?php
/**
 * Created by mcfedr on 7/28/16 14:24
 */
namespace Mcfedr\QueueManagerBundle\Manager;

use Mcfedr\QueueManagerBundle\Queue\Job;

class QueueManagerRegistry
{
    /**
     * @var QueueManager[]
     */
    private $queueManagers;

    /**
     * @var string
     */
    private $default;

    /**
     * @param array $queueManagers
     * @param string $default
     */
    public function __construct(array $queueManagers, $default)
    {
        $this->queueManagers = $queueManagers;
        $this->default = $default;
    }

    /**
     * @param string $name
     * @param array $arguments
     * @param array $options
     * @param string $manager
     * @return Job
     */
    public function put($name, array $arguments = [], array $options = [], $manager = null)
    {
        return $this->queueManagers[$manager ?: $this->default]->put($name, $arguments, $options);
    }

    /**
     * @param Job $job
     * @param string $manager
     */
    public function delete(Job $job, $manager = null)
    {
        $this->queueManagers[$manager ?: $this->default]->delete($job);
    }
}
