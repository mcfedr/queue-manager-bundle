<?php

declare(strict_types=1);

namespace Mcfedr\QueueManagerBundle\Manager;

use Mcfedr\QueueManagerBundle\Exception\JobNotDeletableException;
use Mcfedr\QueueManagerBundle\Exception\WrongJobException;
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

    public function __construct(array $queueManagers, string $default)
    {
        $this->queueManagers = $queueManagers;
        $this->default = $default;
    }

    /**
     * @param string $name
     * @param array  $arguments
     * @param array  $options
     * @param string $manager
     *
     * @return Job
     */
    public function put(string $name, array $arguments = [], array $options = [], ?string $manager = null): Job
    {
        return $this->queueManagers[$manager ?: $this->default]->put($name, $arguments, $options);
    }

    /**
     * @param Job    $job
     * @param string $manager
     *
     * @throws JobNotDeletableException
     */
    public function delete(Job $job, ?string $manager = null)
    {
        if ($manager) {
            $this->queueManagers[$manager]->delete($job);

            return;
        }

        foreach ($this->queueManagers as $queueManager) {
            try {
                $queueManager->delete($job);

                return;
            } catch (WrongJobException $e) {
            }
        }

        throw new WrongJobException('Cannot find a manager able to delete this job');
    }
}
