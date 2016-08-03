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
    const OK = 0;
    const FAIL = 1;
    const RETRY = 2;

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
    protected $logger;

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

        if (function_exists('pcntl_signal')) {
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
                $jobs = $this->getJobs();
                if (count($jobs)) {
                    $oks = [];
                    $fails = [];
                    $retries = [];
                    foreach ($jobs as $job) {
                        if ($useFork) {
                            $result = $this->executeJobInNewProcess($job);
                        } else {
                            $result = $this->executeJob($job);
                        }

                        switch ($result) {
                            case self::OK:
                                $oks[] = $job;
                                break;
                            case self::FAIL:
                                $fails[] = $job;
                                break;
                            case self::RETRY:
                                $retries[] = $job;
                                break;
                        }
                    }
                    $this->finishJobs($oks, $retries, $fails);
                } else {
                    $this->logger && $this->logger->debug('No jobs, sleeping...', [
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
     * @return int
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
            return $code;
        } else {
            /** @var Kernel $kernel */
            $kernel = $this->container->get('kernel');
            $kernel->boot();
            $result = $this->executeJob($job);
            $kernel->shutdown();
            exit($result);
        }
    }

    /**
     * Executes a single job
     *
     * @param Job $job
     * @return int
     */
    protected function executeJob(Job $job)
    {
        try {
            $this->container->get('mcfedr_queue_manager.job_executor')->executeJob($job);
        } catch (ServiceNotFoundException $e) {
            $this->failedJob($job, new UnrecoverableJobException("Missing worker {$job->getName()}", 0, $e));
            return self::FAIL;
        } catch (InvalidWorkerException $e) {
            $this->failedJob($job, new UnrecoverableJobException("Invalid worker {$job->getName()}", 0, $e));
            return self::FAIL;
        } catch (UnrecoverableJobException $e) {
            $this->failedJob($job, $e);
            return self::FAIL;
        } catch (\Exception $e) {
            if (!$job instanceof RetryableJob) {
                $this->failedJob($job, new UnrecoverableJobException('Job failed and is not retryable', 0, $e));
                return self::FAIL;
            }

            if ($job->getRetryCount() >= $this->retryLimit) {
                $this->failedJob($job, new UnrecoverableJobException('Job reached retry limit and won\'t be retried again', 0, $e));
                return self::FAIL;
            }

            $this->failedJob($job, $e);
            return self::RETRY;
        }
        $this->finishJob($job);
        return self::OK;
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
     * @return Job[]
     */
    abstract protected function getJobs();

    /**
     * Called when a job is finished
     *
     * @param Job $job
     */
    protected function finishJob(Job $job)
    {
        $this->logger && $this->logger->debug('Finished a job', [
            'name' => $job->getName(),
            'arguments' => $job->getArguments()
        ]);
    }

    /**
     * Called when a job failed
     *
     * @param Job $job
     * @param \Exception $exception
     */
    protected function failedJob(Job $job, \Exception $exception)
    {
        if ($this->logger) {
            $context = [
                'name' => $job->getName(),
                'arguments' => $job->getArguments(),
                'message' => $exception->getMessage(),
                'retryable' => !$exception instanceof UnrecoverableJobException
            ];
            if (($p = $exception->getPrevious())) {
                $context['cause'] = $p->getMessage();
            }
            $this->logger->error('Job failed', $context);
        }
    }

    /**
     * Called after a batch of jobs finishes
     *
     * @param Job[] $okJobs
     * @param Job[] $retryJobs
     * @param Job[] $failedJobs
     */
    abstract protected function finishJobs(array $okJobs, array $retryJobs, array $failedJobs);

    protected function handleInput(InputInterface $input)
    {
        // Allows overriding
    }
}
