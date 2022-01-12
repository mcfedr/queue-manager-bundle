<?php

declare(strict_types=1);

namespace Mcfedr\QueueManagerBundle\Manager;

use Mcfedr\QueueManagerBundle\Exception\NoSuchJobException;
use Mcfedr\QueueManagerBundle\Exception\WrongJobException;
use Mcfedr\QueueManagerBundle\Queue\BeanstalkJob;
use Mcfedr\QueueManagerBundle\Queue\Job;
use Pheanstalk\Contract\PheanstalkInterface;
use Pheanstalk\Exception\ServerException;

class BeanstalkQueueManager implements QueueManager
{
    use PheanstalkClientTrait;

    private PheanstalkInterface $pheanstalk;

    public function __construct(PheanstalkInterface $pheanstalk, array $options)
    {
        $this->pheanstalk = $pheanstalk;
        $this->setOptions($options);
    }

    public function put(string $name, array $arguments = [], array $options = []): Job
    {
        $queue = $options['queue'] ?? $this->defaultQueue;
        $priority = $options['priority'] ?? PheanstalkInterface::DEFAULT_PRIORITY;
        if (isset($options['time'])) {
            $seconds = ($s = $options['time']->getTimestamp() - time()) > 0 ? $s : 0;
        } elseif (isset($options['delay'])) {
            $seconds = $options['delay'];
        } else {
            $seconds = PheanstalkInterface::DEFAULT_DELAY;
        }
        $ttr = $options['ttr'] ?? PheanstalkInterface::DEFAULT_TTR;

        $job = new BeanstalkJob($name, $arguments, $priority, $ttr);
        $id = $this
            ->pheanstalk
            ->useTube($queue)
            ->put($job->getData(), $priority, $seconds, $ttr)
        ;
        $job->setId($id instanceof \Pheanstalk\Job ? $id->getId() : $id);

        return $job;
    }

    public function delete(Job $job): void
    {
        if (!($job instanceof BeanstalkJob)) {
            throw new WrongJobException('Beanstalk manager can only delete beanstalk jobs');
        }

        try {
            $this->pheanstalk->delete(new \Pheanstalk\Job($job->getId(), ''));
        } catch (ServerException $e) {
            throw new NoSuchJobException('Error deleting job', 0, $e);
        }
    }
}
