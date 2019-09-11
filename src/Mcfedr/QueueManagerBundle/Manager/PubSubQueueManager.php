<?php

declare(strict_types=1);

namespace Mcfedr\QueueManagerBundle\Manager;

use Google\Cloud\PubSub\PubSubClient;
use Mcfedr\QueueManagerBundle\Exception\NoSuchJobException;
use Mcfedr\QueueManagerBundle\Exception\NoSuchQueueException;
use Mcfedr\QueueManagerBundle\Exception\WrongJobException;
use Mcfedr\QueueManagerBundle\Queue\Job;
use Mcfedr\QueueManagerBundle\Queue\PubSubJob;

class PubSubQueueManager implements QueueManager
{
    use PubSubClientTrait;

    /**
     * @var PubSubClient
     */
    private $pubSub;

    public function __construct(PubSubClient $pubSubClient, array $options)
    {
        $this->pubSub = $pubSubClient;
        $this->setOptions($options);
    }

    public function put(string $name, array $arguments = [], array $options = []): Job
    {
        if (\array_key_exists('queue', $options)) {
            if (!\array_key_exists($options['queue'], $this->pubSubQueues)) {
                throw new NoSuchQueueException();
            }
            $topicName = $this->pubSubQueues[$options['queue']]['topic'];
        } else {
            $topicName = $this->defaultQueue['topic'];
        }

        $topic = $this->pubSub->topic($topicName);

        $job = new PubSubJob($name, $arguments, null, 0);

        $result = $topic->publish(['data' => $job->getMessageBody()]);

        $job->setId(reset($result['messageIds']));

        return $job;
    }

    public function delete(Job $job): void
    {
        if (!$job instanceof PubSubJob) {
            throw new WrongJobException('Pub\Sub queue manager can only delete Pub\Sub jobs');
        }

        throw new NoSuchJobException('Pub\Sub queue manager cannot delete jobs');
    }
}
