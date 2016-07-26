<?php
/**
 * Created by mcfedr on 05/03/2016 15:43
 */

namespace Mcfedr\QueueManagerBundle\Command;

use Mcfedr\QueueManagerBundle\Exception\FailedToForkException;
use Mcfedr\QueueManagerBundle\Exception\InvalidWorkerException;
use Mcfedr\QueueManagerBundle\Exception\UnexpectedJobDataException;
use Mcfedr\QueueManagerBundle\Exception\UnrecoverableJobException;
use Mcfedr\QueueManagerBundle\Manager\QueueManager;
use Mcfedr\QueueManagerBundle\Manager\RetryingQueueManager;
use Mcfedr\QueueManagerBundle\Queue\Job;
use Mcfedr\QueueManagerBundle\Queue\RetryableJob;
use Mcfedr\QueueManagerBundle\Queue\Worker;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Symfony\Component\HttpKernel\Kernel;

abstract class RunnerCommand extends Command implements ContainerAwareInterface
{
    use ContainerAwareTrait;

    private $retryLimit = 3;

    private $sleepSeconds = 5;

    /**
     * @var QueueManager
     */
    protected $queueManager;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    public function __construct($name, array $options, QueueManager $queueManager, LoggerInterface $logger = null)
    {
        parent::__construct($name);
        $this->queueManager = $queueManager;
        $this->logger = $logger;
        if (array_key_exists('retry_limit', $options)) {
            $this->retryLimit = $options['retry_limit'];
        }
        if (array_key_exists('sleep_seconds', $options)) {
            $this->sleepSeconds = $options['sleep_seconds'];
        }
    }

    protected function configure()
    {
        $this
            ->setDescription('Run a queue runner')
            ->addOption('once', null, InputOption::VALUE_NONE, 'Run just one job')
            ->addOption('singleProcess', null, InputOption::VALUE_NONE, 'Run all jobs in the same process');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->setInput($input);

        $once = $input->getOption('once');
        $singleProcess = $input->getOption('singleProcess');

        if (!$singleProcess) {
            /** @var Kernel $kernel */
            $kernel = $this->container->get('kernel');
            $kernel->shutdown();
        }

        do {
            try {
                $job = $this->getJob();
                if (!$job) {
                    sleep($this->sleepSeconds);
                    continue;
                }

                if ($singleProcess) {
                    $this->executeJob($job);
                } else {
                    $this->executeJobInNewProcess($job);
                }
            } catch (UnexpectedJobDataException $e) {
                $this->logger && $this->logger->warning('Found unexpected job data in the queue', [
                    'message' => $e->getMessage()
                ]);
            }
        } while (!$once);
    }

    private function executeJobInNewProcess(Job $job)
    {
        $pid = pcntl_fork();
        if ($pid === -1) {
            throw new FailedToForkException("Failed to fork");
        } else if ($pid) {
            pcntl_wait($status);
            $code = pcntl_wexitstatus($status);
            $this->logger->info('Sub proccess exited with status', [
                'code' => $code
            ]);
        } else {
            /** @var Kernel $kernel */
            $kernel = $this->container->get('kernel');
            $kernel->boot();
            $this->executeJob($job);
            $kernel->shutdown();
        }
    }

    private function executeJob(Job $job)
    {
        try {
            $worker = $this->container->get($job->getName());
            if (!$worker instanceof Worker) {
                throw new InvalidWorkerException("The worker {$job->getName()} is not an instance of " . Worker::class);
            }

            $worker->execute($job->getArguments());
        } catch (ServiceNotFoundException $e) {
            $this->logger && $this->logger->warning('Missing worker', [
                'name' => $job->getName()
            ]);
        } catch (InvalidWorkerException $e) {
            $this->logger && $this->logger->warning('Invalid worker', [
                'name' => $job->getName(),
                'message' => $e->getMessage()
            ]);
        } catch (UnrecoverableJobException $e) {
            $this->logger && $this->logger->warning('Job is unrecoverable', [
                'name' => $job->getName(),
                'arguments' => $job->getArguments(),
                'message' => $e->getMessage()
            ]);
        } catch (\Exception $e) {
            if (!$job instanceof RetryableJob) {
                $this->logger && $this->logger->info('Job failed and is not retryable', [
                    'name' => $job->getName(),
                    'arguments' => $job->getArguments(),
                    'message' => $e->getMessage()
                ]);
                return;
            }

            if (!$this->queueManager instanceof RetryingQueueManager) {
                $this->logger && $this->logger->info('Job failed and the manager does not support retries', [
                    'name' => $job->getName(),
                    'arguments' => $job->getArguments(),
                    'message' => $e->getMessage()
                ]);
                return;
            }

            if ($job->getRetryCount() >= $this->retryLimit) {
                $this->logger && $this->logger->info('Job reached retry limit and won\'t be retried again', [
                    'name' => $job->getName(),
                    'arguments' => $job->getArguments(),
                    'message' => $e->getMessage(),
                    'retry_limit' => $this->retryLimit
                ]);
                return;
            }

            $this->queueManager->retry($job, $e);
        }
    }

    /**
     * @throws UnexpectedJobDataException
     * @return Job
     */
    abstract protected function getJob();

    protected function setInput(InputInterface $input)
    {
        // Allows overriding
    }
}
