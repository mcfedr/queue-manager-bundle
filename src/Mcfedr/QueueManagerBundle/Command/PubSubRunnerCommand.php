<?php

declare(strict_types=1);

namespace Mcfedr\QueueManagerBundle\Command;

use Google\Cloud\PubSub\Message;
use Google\Cloud\PubSub\PubSubClient;
use Mcfedr\QueueManagerBundle\Exception\UnexpectedJobDataException;
use Mcfedr\QueueManagerBundle\Manager\PubSubClientTrait;
use Mcfedr\QueueManagerBundle\Queue\JobBatch;
use Mcfedr\QueueManagerBundle\Queue\PubSubJob;
use Mcfedr\QueueManagerBundle\Runner\JobExecutor;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

class PubSubRunnerCommand extends RunnerCommand
{
    use PubSubClientTrait;

    /**
     * @var PubSubClient
     */
    private $pubSub;

    /**
     * @var int
     */
    private $batchSize = 10;

    public function __construct(PubSubClient $pubSubClient, string $name, array $options, JobExecutor $jobExecutor, ?LoggerInterface $logger = null)
    {
        parent::__construct($name, $options, $jobExecutor, $logger);
        $this->pubSub = $pubSubClient;
        $this->setOptions($options);
    }

    protected function configure(): void
    {
        parent::configure();
        $this
            ->addOption('queue', null, InputOption::VALUE_REQUIRED, 'The name of a queue in the config, can be a comma separated list')
            ->addOption('batch-size', null, InputOption::VALUE_REQUIRED, 'Number of messages to fetch at once', 10)
        ;
    }

    protected function getJobs(): ?JobBatch
    {
        foreach ($this->pubSubQueues as $queue) {
            $jobs = $this->getJobsFromSubscription($queue['subscription'], $queue['topic']);
            if ($jobs) {
                return $jobs;
            }
        }

        return null;
    }

    protected function finishJobs(JobBatch $batch): void
    {
        $retryJobs = $batch->getRetries();

        if (\count($retryJobs)) {
            $topic = $this->pubSub->topic($batch->getOption('topic'));

            $topic->publishBatch(
                array_map(function (PubSubJob $retryJob) {
                    $retryJob->incrementRetryCount();

                    return ['data' => $retryJob->getMessageBody()];
                }, $retryJobs)
            );
        }

        /** @var PubSubJob[] $toAcknowledge */
        $toAcknowledge = array_merge($batch->getOks(), $batch->getRetries(), $batch->getFails());
        if (\count($toAcknowledge)) {
            $toAcknowledge = array_map(function (PubSubJob $message) {
                return $pubSubMessage = (new Message(['messageId' => $message->getId()], ['ackId' => $message->getAckId()]));
            }, $toAcknowledge);

            $this->pubSub->subscription($batch->getOption('subscription'))->acknowledgeBatch($toAcknowledge);
        }
    }

    protected function handleInput(InputInterface $input): void
    {
        if ($input->getOption('queue')) {
            $queues = [];
            foreach (explode(',', $input->getOption('queue')) as $queue) {
                $queues[$queue] = $this->pubSubQueues[$queue];
            }
            $this->pubSubQueues = $queues;
        } else {
            $this->pubSubQueues = ['default' => $this->defaultQueue];
        }

        if (($batch = $input->getOption('batch-size'))) {
            $this->batchSize = (int) $batch;
        }
    }

    private function getJobsFromSubscription($subscription, $topic)
    {
        $response = $this->pubSub->subscription($subscription)->pull(['maxMessages' => $this->batchSize]);

        if (\count($response)) {
            $jobs = [];
            $exception = null;

            $toAcknowledge = [];
            /** @var Message $message */
            foreach ($response as $message) {
                if ($message->data() === null || (!\is_array($data = json_decode($message->data(), true))) || !isset($data['name']) || !isset($data['arguments']) || !isset($data['retryCount'])) {
                    $exception = new UnexpectedJobDataException('Sqs message(s) missing data fields name, arguments and retryCount');
                    $pubSubMessage = (new Message(['messageId' => $message->id()], ['ackId' => $message->ackId()]));
                    $toAcknowledge[] = $pubSubMessage;

                    continue;
                }

                $job = new PubSubJob(
                    $data['name'],
                    $data['arguments'],
                    $message->id(),
                    $data['retryCount'],
                    $message->ackId()
                );

                $jobs[] = $job;
            }

            if (\count($toAcknowledge)) {
                $this->pubSub->subscription($subscription)->acknowledgeBatch($toAcknowledge);
            }

            if ($exception) {
                if (\count($jobs)) {
                    if ($this->logger) {
                        $this->logger->error('Found unexpected job data in the queue.', [
                            'message' => $exception->getMessage(),
                        ]);
                    }
                } else {
                    throw $exception;
                }
            }

            if (\count($jobs)) {
                return new JobBatch($jobs, [], [], [], ['topic' => $topic, 'subscription' => $subscription]);
            }
        }

        return null;
    }
}
