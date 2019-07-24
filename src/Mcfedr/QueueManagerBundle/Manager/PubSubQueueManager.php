<?php

declare(strict_types=1);

namespace Mcfedr\QueueManagerBundle\Manager;

use Google\Cloud\PubSub\PubSubClient;
use Mcfedr\QueueManagerBundle\Exception\NoSuchJobException;
use Mcfedr\QueueManagerBundle\Queue\Job;
use Mcfedr\QueueManagerBundle\Queue\PubSubJob;

class PubSubQueueManager implements QueueManager
{
    /**
     * @var PubSubClient
     */
    private $pubSub;

    /**
     * @var string
     */
    private $defaultTopic;

    /**
     * @var array
     */
    private $topics;

    public function __construct(PubSubClient $pubSubClient, array $options)
    {
        $this->pubSub = $pubSubClient;
        $this->setOptions($options);
    }

    public function put(string $name, array $arguments = [], array $options = []): Job
    {
        if (\array_key_exists('queue', $options)) {
            $topicName = $this->topics[$options['queue']];
        } else {
            $topicName = $this->defaultTopic;
        }

        $topic = $this->pubSub->topic($topicName);

        $job = new PubSubJob($name, $arguments, null, null, 0);

        $result = $topic->publish(['data' => $job->getMessageBody()]);

        $job->setId(reset($result['messageIds']));

        return $job;
    }

    public function delete(Job $job): void
    {
        throw new NoSuchJobException('Pub\Sub queue manager cannot delete jobs');
    }

    private function setOptions(array $options): void
    {
        $this->defaultTopic = $options['default_topic'];
        $this->topics = array_map(function ($topic) {
            return $topic['topic'];
        }, $options['topics']);
        if (!\array_key_exists('default', $this->topics)) {
            $this->topics['default'] = $this->defaultTopic;
        }
    }
}
