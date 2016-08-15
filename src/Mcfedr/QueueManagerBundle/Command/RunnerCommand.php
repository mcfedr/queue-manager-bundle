<?php
/**
 * Created by mcfedr on 05/03/2016 15:43
 */

namespace Mcfedr\QueueManagerBundle\Command;

use Mcfedr\QueueManagerBundle\Event\FailedJobEvent;
use Mcfedr\QueueManagerBundle\Event\FinishedJobEvent;
use Mcfedr\QueueManagerBundle\Exception\FailedToForkException;
use Mcfedr\QueueManagerBundle\Exception\InvalidWorkerException;
use Mcfedr\QueueManagerBundle\Exception\UnexpectedJobDataException;
use Mcfedr\QueueManagerBundle\Exception\UnrecoverableJobException;
use Mcfedr\QueueManagerBundle\Manager\QueueManager;
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
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\ProcessBuilder;

abstract class RunnerCommand extends Command implements ContainerAwareInterface
{
    const OK = 0;
    const FAIL = 1;
    const RETRY = 2;

    const JOB_FINISHED_EVENT = 'mcfedr_queue_manager.job_finished';
    const JOB_FAILED_EVENT = 'mcfedr_queue_manager.job_failed';

    use ContainerAwareTrait {
        setContainer as setContainerInner;
    }

    /**
     * @var int
     */
    private $retryLimit = 3;

    /**
     * @var int
     */
    private $sleepSeconds = 5;

    /**
     * @var QueueManager
     */
    protected $queueManager;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var EventDispatcher
     */
    private $eventDispatcher;

    /**
     * @var ProcessBuilder
     */
    private $processBuilder;

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
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Only run [limit] batches of jobs', 0)
            ->addOption('process-isolation', null, InputOption::VALUE_NONE, 'New processes for each job');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (function_exists('pcntl_signal')) {
            $handle = function($sig) use (&$running) {
                $this->logger && $this->logger->debug("Received signal ($sig), stopping...");
                $running = false;
            };
            pcntl_signal(SIGTERM, $handle);
            pcntl_signal(SIGINT, $handle);
        }

        $this->handleInput($input);

        $limit = (int) $input->getOption('limit');
        $ignoreLimit = $limit === 0;

        $running = true;

        do {
            if ($input->getOption('process-isolation')) {
                $this->executeBatchWithProcess($input, $output);
            } else {
                $this->executeBatch();
            }

            if (isset($handle)) {
                pcntl_signal_dispatch();
            }

            gc_collect_cycles();
        } while ($running && ($ignoreLimit || --$limit > 0));
    }

    protected function executeBatch()
    {
        try {
            $jobs = $this->getJobs();
            if (count($jobs)) {
                $oks = [];
                $fails = [];
                $retries = [];
                foreach ($jobs as $job) {
                    $result = $this->executeJob($job);

                    switch ($result) {
                        case self::OK:
                            $oks[] = $job;
                            break;
                        case self::FAIL:
                            $fails[] = $job;
                            break;
                        default:
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
    }

    protected function executeBatchWithProcess(InputInterface $input, OutputInterface $output)
    {
        $pb = $this->getProcessBuilder($input, $output);

        /** @var Process $process */
        $process = $pb->getProcess();

        $process->mustRun(function ($type, $data) use ($output) {
            $output->write($data);
        });
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
            $this->eventDispatcher = $container->get('event_dispatcher', Container::NULL_ON_INVALID_REFERENCE);
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
        $this->eventDispatcher && $this->eventDispatcher->dispatch(self::JOB_FINISHED_EVENT, new FinishedJobEvent($job));
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
        $this->eventDispatcher && $this->eventDispatcher->dispatch(self::JOB_FAILED_EVENT, new FailedJobEvent($job, $exception));
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

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return ProcessBuilder
     */
    private function getProcessBuilder(InputInterface $input, OutputInterface $output)
    {
        if (!$this->processBuilder) {
            $finder = new PhpExecutableFinder();
            $php = $finder->find();

            $pb = new ProcessBuilder();

            $pb
                ->add($php)
                ->add($_SERVER['argv'][0])
                ->add($this->getName())
                ->inheritEnvironmentVariables(true)
                ->add('--limit=1')
                ->add('--no-interaction')
                ->add('--no-ansi');

            $skip = [
                'limit',
                'process-isolation',
                'verbose',
                'quiet',
                'ansi',
                'no-ansi',
                'no-interaction'
            ];

            foreach ($input->getOptions() as $key => $option) {
                if (in_array($key, $skip)) {
                    continue;
                }
                if ($option === true) {
                    $pb->add("--$key");
                    continue;
                }
                if ($option !== false && $option !== null) {
                    $pb->add("--$key=$option");
                }
            }

            switch ($output->getVerbosity()) {
                case OutputInterface::VERBOSITY_QUIET:
                    $pb->add('-q');
                    break;
                case OutputInterface::VERBOSITY_VERBOSE:
                    $pb->add('-v');
                    break;
                case OutputInterface::VERBOSITY_VERY_VERBOSE:
                    $pb->add('-vv');
                    break;
                case OutputInterface::VERBOSITY_DEBUG:
                    $pb->add('-vvv');
                    break;
            }

            foreach ($input->getArguments() as $key => $argument) {
                if ($key == 'command') {
                    continue;
                }
                $pb->add($argument);
            }
            $this->processBuilder = $pb;
        }
        return $this->processBuilder;
    }

    /**
     * Get the number of seconds to delay a try
     * 
     * @param int $count
     * @return int
     */
    protected function getRetryDelaySeconds($count)
    {
        return $count * $count * 30;
    }
}
