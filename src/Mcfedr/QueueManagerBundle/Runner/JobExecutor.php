<?php

namespace Mcfedr\QueueManagerBundle\Runner;

use Mcfedr\QueueManagerBundle\Event\FailedJobEvent;
use Mcfedr\QueueManagerBundle\Event\FinishedJobEvent;
use Mcfedr\QueueManagerBundle\Event\StartJobEvent;
use Mcfedr\QueueManagerBundle\Exception\InvalidWorkerException;
use Mcfedr\QueueManagerBundle\Exception\UnrecoverableJobException;
use Mcfedr\QueueManagerBundle\Exception\UnrecoverableJobExceptionInterface;
use Mcfedr\QueueManagerBundle\Queue\InternalWorker;
use Mcfedr\QueueManagerBundle\Queue\Job;
use Mcfedr\QueueManagerBundle\Queue\RetryableJob;
use Mcfedr\QueueManagerBundle\Queue\Worker;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class JobExecutor implements ContainerAwareInterface
{
    const JOB_START_EVENT = 'mcfedr_queue_manager.job_start';
    const JOB_FINISHED_EVENT = 'mcfedr_queue_manager.job_finished';
    const JOB_FAILED_EVENT = 'mcfedr_queue_manager.job_failed';

    use ContainerAwareTrait {
        setContainer as setContainerInner;
    }

    /**
     * @var EventDispatcher
     */
    private $eventDispatcher;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    public function setContainer(ContainerInterface $container = null)
    {
        $this->setContainerInner($container);
    }

    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function setEventDispatcher(EventDispatcherInterface $eventDispatcher)
    {
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * @param Job $job
     * @param int $retryLimit Turn \Exception into UnrecoverableJobException when the retry limit is reached on RetryableJob
     *
     * @throws UnrecoverableJobException
     * @throws \Exception
     */
    public function executeJob(Job $job, $retryLimit = 0)
    {
        $internal = false;
        try {
            $worker = $this->container->get($job->getName());
            if (!$worker instanceof Worker) {
                throw new InvalidWorkerException("The worker {$job->getName()} is not an instance of ".Worker::class);
            }
            $internal = $worker instanceof InternalWorker;
            $this->eventDispatcher && $this->eventDispatcher->dispatch(self::JOB_START_EVENT, new StartJobEvent($job, $internal));
            $worker->execute($job->getArguments());
        } catch (ServiceNotFoundException $e) {
            $unrecoverable = new UnrecoverableJobException("Missing worker {$job->getName()}", 0, $e);
            $this->failedJob($job, $unrecoverable, $internal);

            throw $unrecoverable;
        } catch (UnrecoverableJobExceptionInterface $e) {
            $this->failedJob($job, $e, $internal);

            throw $e;
        } catch (\Exception $e) {
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
     *
     * @param Job  $job
     * @param bool $internal
     */
    protected function finishJob(Job $job, $internal)
    {
        $this->logger && $this->logger->debug('Finished a job', [
            'name' => $job->getName(),
            'arguments' => $job->getArguments(),
        ]);
        $this->eventDispatcher && $this->eventDispatcher->dispatch(self::JOB_FINISHED_EVENT, new FinishedJobEvent($job, $internal));
    }

    /**
     * Called when a job failed.
     *
     * @param Job        $job
     * @param \Exception $exception
     * @param bool       $internal
     */
    protected function failedJob(Job $job, \Exception $exception, $internal)
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
            $this->logger->error('Job failed', $context);
        }
        $this->eventDispatcher && $this->eventDispatcher->dispatch(self::JOB_FAILED_EVENT, new FailedJobEvent($job, $exception, $internal));
    }
}
