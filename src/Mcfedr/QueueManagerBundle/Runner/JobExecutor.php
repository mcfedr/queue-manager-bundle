<?php

declare(strict_types=1);

namespace Mcfedr\QueueManagerBundle\Runner;

use Mcfedr\QueueManagerBundle\Event\FailedJobEvent;
use Mcfedr\QueueManagerBundle\Event\FinishedJobBatchEvent;
use Mcfedr\QueueManagerBundle\Event\FinishedJobEvent;
use Mcfedr\QueueManagerBundle\Event\StartJobBatchEvent;
use Mcfedr\QueueManagerBundle\Event\StartJobEvent;
use Mcfedr\QueueManagerBundle\Exception\InvalidWorkerException;
use Mcfedr\QueueManagerBundle\Exception\UnrecoverableJobException;
use Mcfedr\QueueManagerBundle\Exception\UnrecoverableJobExceptionInterface;
use Mcfedr\QueueManagerBundle\Queue\InternalWorker;
use Mcfedr\QueueManagerBundle\Queue\Job;
use Mcfedr\QueueManagerBundle\Queue\JobBatch;
use Mcfedr\QueueManagerBundle\Queue\RetryableJob;
use Mcfedr\QueueManagerBundle\Queue\Worker;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Contracts\EventDispatcher\Event as ContractEvent;

class JobExecutor
{
    public const JOB_START_EVENT = 'mcfedr_queue_manager.job_start';
    public const JOB_FINISHED_EVENT = 'mcfedr_queue_manager.job_finished';
    public const JOB_FAILED_EVENT = 'mcfedr_queue_manager.job_failed';

    public const JOB_BATCH_START_EVENT = 'mcfedr_queue_manager.job_batch_start';
    public const JOB_BATCH_FINISHED_EVENT = 'mcfedr_queue_manager.job_batch_finished';

    /**
     * @var ?LoggerInterface
     */
    protected $logger;

    /**
     * @var ContainerInterface
     */
    private $workersMap;

    /**
     * @var ?EventDispatcherInterface
     */
    private $eventDispatcher;

    private $batchStarted = false;
    private $triggerBatchEvents = false;

    public function __construct(ContainerInterface $workersMap, ?EventDispatcherInterface $eventDispatcher = null, ?LoggerInterface $logger = null)
    {
        $this->workersMap = $workersMap;
        $this->logger = $logger;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function startBatch(JobBatch $batch): void
    {
        $this->dispatch(self::JOB_BATCH_START_EVENT, new StartJobBatchEvent($batch->getJobs()));
        $this->batchStarted = true;
    }

    public function finishBatch(JobBatch $batch): void
    {
        $this->batchStarted = false;
        $this->dispatch(self::JOB_BATCH_FINISHED_EVENT, new FinishedJobBatchEvent($batch->getOks(), $batch->getRetries(), $batch->getFails(), $batch->getJobs()));
    }

    /**
     * @param int $retryLimit Turn \Exception into UnrecoverableJobException when the retry limit is reached on RetryableJob
     *
     * @throws UnrecoverableJobException
     * @throws \Exception
     */
    public function executeJob(Job $job, int $retryLimit = 0): void
    {
        // This is here so that if executeJob is called with out startBatch the events will still be called
        $this->triggerBatchEvents = !$this->batchStarted;
        if ($this->triggerBatchEvents) {
            $this->startBatch(new JobBatch([$job]));
        }

        $internal = false;

        if ($this->logger) {
            $this->logger->debug('Start a job.', [
                'name' => $job->getName(),
                'arguments' => $job->getArguments(),
            ]);
        }

        try {
            $worker = $this->workersMap->get($job->getName());
            if (!$worker instanceof Worker) {
                throw new InvalidWorkerException("The worker {$job->getName()} is not an instance of ".Worker::class);
            }
            $internal = $worker instanceof InternalWorker;
            $this->dispatch(self::JOB_START_EVENT, new StartJobEvent($job, $internal));
            $worker->execute($job->getArguments());
        } catch (NotFoundExceptionInterface $e) {
            $unrecoverable = new UnrecoverableJobException("Missing worker {$job->getName()}", 0, $e);
            $this->failedJob($job, $unrecoverable, $internal);

            throw $unrecoverable;
        } catch (UnrecoverableJobExceptionInterface $e) {
            $this->failedJob($job, $e, $internal);

            throw $e;
        } catch (\Throwable $e) {
            if (!$job instanceof RetryableJob) {
                $unrecoverable = new UnrecoverableJobException('Job failed and is not retryable', 0, $e);
                $this->failedJob($job, $unrecoverable, $internal);

                throw $unrecoverable;
            }

            if ($job->getRetryCount() >= $retryLimit) {
                $unrecoverable = new UnrecoverableJobException('Job reached retry limit and won\'t be retried again', 0, $e);
                $this->failedJob($job, $unrecoverable, $internal);

                throw $unrecoverable;
            }

            $this->failedJob($job, $e, $internal);

            throw $e;
        }
        $this->finishJob($job, $internal);
    }

    /**
     * Called when a job is finished.
     */
    protected function finishJob(Job $job, bool $internal): void
    {
        if ($this->logger) {
            $this->logger->info('Finished a job.', [
                'name' => $job->getName(),
                'arguments' => $job->getArguments(),
            ]);
        }
        $this->dispatch(self::JOB_FINISHED_EVENT, new FinishedJobEvent($job, $internal));
        if ($this->triggerBatchEvents) {
            $this->finishBatch(new JobBatch([], [$job]));
        }
    }

    /**
     * Called when a job failed.
     */
    protected function failedJob(Job $job, \Throwable $exception, bool $internal): void
    {
        if ($this->logger) {
            $context = [
                'name' => $job->getName(),
                'arguments' => $job->getArguments(),
                'message' => $exception->getMessage(),
                'retryable' => !$exception instanceof UnrecoverableJobExceptionInterface,
                'internal' => $internal,
            ];
            if (($p = $exception->getPrevious())) {
                $context['cause'] = $p->getMessage();
            }
            $this->logger->error('Job failed.', $context);
        }
        $this->dispatch(self::JOB_FAILED_EVENT, new FailedJobEvent($job, $exception, $internal));
        if ($this->triggerBatchEvents) {
            $batch = new JobBatch([$job]);
            $batch->next();
            $batch->result($exception);
            $this->finishBatch($batch);
        }
    }

    /**
     * Provide a BC way to dispatch events.
     *
     * @param string $eventName
     * @param $event
     */
    private function dispatch(string $eventName, $event): void
    {
        if ($this->eventDispatcher) {
            $r = new \ReflectionClass(get_class($this->eventDispatcher));
            if (count($r->getMethod('dispatch')->getParameters()) == 1) {
                $this->eventDispatcher->dispatch($event, $eventName);
            } else {
                $this->eventDispatcher->dispatch($eventName, $event);
            }
        }
    }
}
