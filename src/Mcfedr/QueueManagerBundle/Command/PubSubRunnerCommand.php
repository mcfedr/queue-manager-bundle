<?php

declare(strict_types=1);

namespace Mcfedr\QueueManagerBundle\Command;

use Google\Cloud\PubSub\Message;
use Google\Cloud\PubSub\PubSubClient;
use Mcfedr\QueueManagerBundle\Exception\UnexpectedJobDataException;
use Mcfedr\QueueManagerBundle\Queue\JobBatch;
use Mcfedr\QueueManagerBundle\Queue\PubSubJob;
use Mcfedr\QueueManagerBundle\Runner\JobExecutor;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

class PubSubRunnerCommand extends RunnerCommand
{
    /**
     * @var PubSubClient
     */
    private $pubSub;

    /**
     * @var int
     */
    private $batchSize = 10;

    /**
     * @var int
     */
    private $waitTime = 20;

    /**
     * @var array
     */
    private $subscriptions = [];

    /**
     * @var string
     */
    private $defaultSubscription;

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
            ->addOption('url', null, InputOption::VALUE_REQUIRED, 'The url of SQS queue to run, can be a comma separated list')
            ->addOption('queue', null, InputOption::VALUE_REQUIRED, 'The name of a queue in the config, can be a comma separated list')
            ->addOption('batch-size', null, InputOption::VALUE_REQUIRED, 'Number of messages to fetch at once', 10)
            ->addOption('wait-time', null, InputOption::VALUE_OPTIONAL, 'Wait time between 0-20 seconds')
        ;
    }

    protected function getJobs(): ?JobBatch
    {
        foreach ($this->subscriptions as $subscription) {
            $jobs = $this->getJobsFromSubscription($subscription);
            if ($jobs) {
                return $jobs;
            }
        }

        return null;
    }

    protected function finishJobs(JobBatch $batch): void
    {
        $retryJobs = $batch->getRetries();
        //todo implement retry failed jobs
        /** @var PubSubJob[] $toDelete */
        $toDelete = array_merge($batch->getOks(), $batch->getRetries(), $batch->getFails());
        if (\count($toDelete)) {
            $subscription = $toDelete[0]->getSubscription();
            $toDelete = array_map(function (PubSubJob $message) {
                return $pubSubMessage = (new Message(['messageId' => $message->getId()], ['ackId' => $message->getAckId()]));
            }, $toDelete);

            $this->pubSub->subscription($subscription)->acknowledgeBatch($toDelete);
        }
    }

    protected function handleInput(InputInterface $input): void
    {
        if (($url = $input->getOption('url'))) {
            $this->subscriptions = explode(',', $url);
        } elseif (($queue = $input->getOption('queue'))) {
            $this->subscriptions = array_map(function ($queue) {
                return $this->subscriptions[$queue];
            }, explode(',', $queue));
        } else {
            $this->subscriptions = [$this->defaultSubscription];
        }

        if (($batch = $input->getOption('batch-size'))) {
            $this->batchSize = (int) $batch;
        }

        if (($waitTime = $input->getOption('wait-time'))) {
            $this->waitTime = (int) $waitTime;
        }
    }

    private function setOptions(array $options): void
    {
        $this->defaultSubscription = $options['default_subscription'];
        $this->subscriptions = array_map(function ($topic) {
            return $topic['subscribtion'];
        }, $options['topics']);

        if (!\array_key_exists('default', $this->subscriptions)) {
            $this->subscriptions['default'] = $this->defaultSubscription;
        }
    }

    private function getJobsFromSubscription($subscription): ?JobBatch
    {
        $response = $this->pubSub->subscription($subscription)->pull(['maxMessages' => $this->batchSize]);

        if (\count($response)) {
            $jobs = [];
            $exception = null;

            /** @var Message $message */
            foreach ($response as $message) {
                if ($message->data() === null || (!\is_array($data = json_decode($message->data(), true))) || !isset($data['name']) || !isset($data['arguments']) || !isset($data['retryCount'])) {
                    $exception = new UnexpectedJobDataException('Sqs message(s) missing data fields name, arguments and retryCount');
                    $pubSubMessage = (new Message(['messageId' => $message->id()], ['ackId' => $message->ackId()]));
                    $this->pubSub->subscription($subscription)->acknowledge($pubSubMessage);

                    continue;
                }

                $job = new PubSubJob(
                    $data['name'],
                    $data['arguments'],
                    $subscription,
                    $message->id(),
                    $data['retryCount'],
                    $message->ackId()
                );

                $jobs[] = $job;
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
                return new JobBatch($jobs);
            }
        }

        return null;
    }
}
