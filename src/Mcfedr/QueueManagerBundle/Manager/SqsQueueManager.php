<?php

declare(strict_types=1);

namespace Mcfedr\QueueManagerBundle\Manager;

use Aws\Sqs\SqsClient;
use Mcfedr\QueueManagerBundle\Exception\NoSuchJobException;
use Mcfedr\QueueManagerBundle\Exception\NoSuchQueueException;
use Mcfedr\QueueManagerBundle\Exception\WrongJobException;
use Mcfedr\QueueManagerBundle\Queue\Job;
use Mcfedr\QueueManagerBundle\Queue\SqsJob;

class SqsQueueManager implements QueueManager
{
    use SqsClientTrait;

    private SqsClient $sqs;

    public function __construct(SqsClient $sqsClient, array $options)
    {
        $this->sqs = $sqsClient;
        $this->setOptions($options);
    }

    /**
     * @throws NoSuchQueueException
     */
    public function put(string $name, array $arguments = [], array $options = []): Job
    {
        if (\array_key_exists('url', $options)) {
            $url = $options['url'];
        } elseif (\array_key_exists('queue', $options)) {
            if (!\array_key_exists($options['queue'], $this->queues)) {
                throw new NoSuchQueueException();
            }
            $url = $this->queues[$options['queue']];
        } else {
            $url = $this->defaultUrl;
        }

        $visibilityTimeout = null;
        if (isset($options['visibilityTimeout'])) {
            $visibilityTimeout = $options['visibilityTimeout'];
        } elseif (isset($options['ttr'])) {
            $visibilityTimeout = $options['ttr'];
        }

        $sendMessage = [
            'QueueUrl' => $url,
        ];

        $delay = null;
        if (isset($options['time'])) {
            $sendMessage['DelaySeconds'] = $delay = ($options['time']->getTimestamp() - time());
        } elseif (isset($options['delay'])) {
            $sendMessage['DelaySeconds'] = $delay = $options['delay'];
        }

        $job = new SqsJob($name, $arguments, $delay, $url, null, 0, null, $visibilityTimeout);

        $sendMessage['MessageBody'] = $job->getMessageBody();

        $result = $this->sqs->sendMessage($sendMessage);
        $job->setId($result['MessageId']);

        return $job;
    }

    public function delete(Job $job): void
    {
        if (!$job instanceof SqsJob) {
            throw new WrongJobException('Sqs queue manager can only delete sqs jobs');
        }

        throw new NoSuchJobException('Sqs queue manager cannot delete jobs');
    }
}
