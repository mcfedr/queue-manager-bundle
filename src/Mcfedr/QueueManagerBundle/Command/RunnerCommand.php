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
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Symfony\Component\HttpKernel\Kernel;

abstract class RunnerCommand extends Command implements ContainerAwareInterface
{
    use ContainerAwareTrait {
        setContainer as setContainerInner;
    }

    private $retryLimit = 3;

    private $sleepSeconds = 5;

    /**
     * @var QueueManager
     */
    protected $queueManager;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct($name, array $options, QueueManager $queueManager)
    {
        parent::__construct($name);
        $this->queueManager = $queueManager;
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
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Only run [limit] jobs', -1)
            ->addOption('fork', null, InputOption::VALUE_NONE, 'Fork for each job');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->handleInput($input);

        $limit = $input->getOption('limit');
        $useFork = $input->getOption('fork');

        $running = true;

        if ($running && function_exists('pcntl_signal')) {
            $handle = function($sig) use (&$running) {
                $this->logger && $this->logger->debug("Received signal ($sig), stopping...");
                $running = false;
            };
            pcntl_signal(SIGTERM, $handle);
            pcntl_signal(SIGINT, $handle);
        }

        if ($useFork) {
            /** @var Kernel $kernel */
            $kernel = $this->container->get('kernel');
            $kernel->shutdown();
        }

        do {
            try {
                $job = $this->getJob();
                if ($job) {
                    if ($useFork) {
                        $this->executeJobInNewProcess($job);
                    } else {
                        $this->executeJob($job);
                    }
                } else {
                    $this->logger && $this->logger->debug('No job, sleeping...', [
                        'sleepSeconds' => $this->sleepSeconds
                    ]);
                    sleep($this->sleepSeconds);
                }
            } catch (UnexpectedJobDataException $e) {
                $this->logger && $this->logger->warning('Found unexpected job data in the queue', [
                    'message' => $e->getMessage()
                ]);
            }

            if (isset($handle)) {
                pcntl_signal_dispatch();
            }
        } while ($running && ($limit === -1 || --$limit > 0));
    }

    /**
     * Create a new process and run the given job in it
     *
     * @param Job $job
     * @throws FailedToForkException
     */
    protected function executeJobInNewProcess(Job $job)
    {
        $pid = pcntl_fork();
        if ($pid === -1) {
            throw new FailedToForkException("Failed to fork");
        } else if ($pid) {
            pcntl_wait($status);
            $code = pcntl_wexitstatus($status);
            $this->logger && $this->logger->debug('Sub process exited with status', [
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

    /**
     * Executes a single job
     *
     * @param Job $job
     */
    protected function executeJob(Job $job)
    {
        try {
            $this->container->get('mcfedr_queue_manager.job_executor')->executeJob($job);
        } catch (ServiceNotFoundException $e) {
            $this->logger && $this->logger->error('Missing worker', [
                'name' => $job->getName()
            ]);
            $this->failedJob($job, $e);
            return;
        } catch (InvalidWorkerException $e) {
            $this->logger && $this->logger->error('Invalid worker', [
                'name' => $job->getName(),
                'message' => $e->getMessage()
            ]);
            $this->failedJob($job, $e);
            return;
        } catch (UnrecoverableJobException $e) {
            $this->logger && $this->logger->warning('Job is unrecoverable', [
                'name' => $job->getName(),
                'arguments' => $job->getArguments(),
                'message' => $e->getMessage()
            ]);
            $this->failedJob($job, $e);
            return;
        } catch (\Exception $e) {
            if (!$job instanceof RetryableJob) {
                $this->logger && $this->logger->error('Job failed and is not retryable', [
                    'name' => $job->getName(),
                    'arguments' => $job->getArguments(),
                    'message' => $e->getMessage()
                ]);
                $this->failedJob($job, $e);
                return;
            }

            if (!$this->queueManager instanceof RetryingQueueManager) {
                $this->logger && $this->logger->error('Job failed and the manager does not support retries', [
                    'name' => $job->getName(),
                    'arguments' => $job->getArguments(),
                    'message' => $e->getMessage()
                ]);
                $this->failedJob($job, $e);
                return;
            }

            if ($job->getRetryCount() >= $this->retryLimit) {
                $this->logger && $this->logger->error('Job reached retry limit and won\'t be retried again', [
                    'name' => $job->getName(),
                    'arguments' => $job->getArguments(),
                    'message' => $e->getMessage(),
                    'retry_limit' => $this->retryLimit
                ]);
                $this->failedJob($job, $e);
                return;
            }

            $this->logger && $this->logger->info('Job failed and will be retried', [
                'name' => $job->getName(),
                'arguments' => $job->getArguments(),
                'message' => $e->getMessage()
            ]);

            $this->queueManager->retry($job, $e);
            $this->failedJob($job, $e);
            return;
        }
        $this->finishJob($job);
    }

    public function setContainer(ContainerInterface $container = null)
    {
        $this->setContainerInner($container);
        if ($container) {
            $this->logger = $container->get('logger', Container::NULL_ON_INVALID_REFERENCE);
        }
    }

    /**
     * @throws UnexpectedJobDataException
     * @return Job
     */
    abstract protected function getJob();

    /**
     * Called when a job is finished
     *
     * @param Job $job
     */
    protected function finishJob(Job $job)
    {
        // Allows overriding
    }

    /**
     * Called when a job failed
     *
     * @param Job $job
     */
    protected function failedJob(Job $job, \Exception $exception)
    {
        // Allows overriding
    }

    protected function handleInput(InputInterface $input)
    {
        // Allows overriding
    }
}
