<?php

declare(strict_types=1);

namespace Mcfedr\QueueManagerBundle\Command;

use Mcfedr\QueueManagerBundle\Exception\UnexpectedJobDataException;
use Mcfedr\QueueManagerBundle\Exception\UnrecoverableJobExceptionInterface;
use Mcfedr\QueueManagerBundle\Manager\QueueManager;
use Mcfedr\QueueManagerBundle\Queue\Job;
use Mcfedr\QueueManagerBundle\Runner\JobExecutor;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

abstract class RunnerCommand extends Command
{
    const OK = 0;
    const FAIL = 1;
    const RETRY = 2;

    /**
     * @deprecated
     * @see JobExecutor::JOB_START_EVENT
     */
    const JOB_START_EVENT = 'mcfedr_queue_manager.job_start';

    /**
     * @deprecated
     * @see JobExecutor::JOB_FINISHED_EVENT
     */
    const JOB_FINISHED_EVENT = 'mcfedr_queue_manager.job_finished';

    /**
     * @deprecated
     * @see JobExecutor::JOB_FAILED_EVENT
     */
    const JOB_FAILED_EVENT = 'mcfedr_queue_manager.job_failed';

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
     * @var ?LoggerInterface
     */
    private $logger;

    /**
     * @var Process
     */
    private $process;

    /**
     * @var JobExecutor
     */
    private $jobExecutor;

    public function __construct($name, array $options, QueueManager $queueManager, JobExecutor $jobExecutor, ?LoggerInterface $logger = null)
    {
        parent::__construct($name);
        $this->queueManager = $queueManager;
        if (array_key_exists('retry_limit', $options)) {
            $this->retryLimit = $options['retry_limit'];
        }
        if (array_key_exists('sleep_seconds', $options)) {
            $this->sleepSeconds = $options['sleep_seconds'];
        }
        $this->jobExecutor = $jobExecutor;
        $this->logger = $logger;
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
            $handle = function ($sig) use (&$running) {
                $this->logger && $this->logger->debug("Received signal ($sig), stopping...");
                $running = false;
            };
            pcntl_signal(SIGTERM, $handle);
            pcntl_signal(SIGINT, $handle);
        }

        $this->handleInput($input);

        $limit = (int) $input->getOption('limit');
        $ignoreLimit = 0 === $limit;

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
                $this->jobExecutor->startBatch($jobs);
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
                $this->jobExecutor->finishBatch($oks, $retries, $fails);
            } else {
                $this->logger && $this->logger->debug('No jobs, sleeping...', [
                    'sleepSeconds' => $this->sleepSeconds,
                ]);
                sleep($this->sleepSeconds);
            }
        } catch (UnexpectedJobDataException $e) {
            $this->logger && $this->logger->warning('Found unexpected job data in the queue', [
                'message' => $e->getMessage(),
            ]);
        }
    }

    protected function executeBatchWithProcess(InputInterface $input, OutputInterface $output)
    {
        /** @var Process $process */
        $process = $this->getProcess($input);

        $process->mustRun(function ($type, $data) use ($output) {
            $output->write($data);
        });
    }

    /**
     * Executes a single job.
     */
    protected function executeJob(Job $job): int
    {
        try {
            $this->jobExecutor->executeJob($job, $this->retryLimit);
        } catch (UnrecoverableJobExceptionInterface $e) {
            $this->failedJob($job, $e);

            return self::FAIL;
        } catch (\Exception $e) {
            $this->failedJob($job, $e);

            return self::RETRY;
        }
        $this->finishJob($job);

        return self::OK;
    }

    /**
     * @throws UnexpectedJobDataException
     *
     * @return Job[]
     */
    abstract protected function getJobs();

    /**
     * Called when a job is finished.
     *
     * @param Job $job
     *
     * @deprecated
     */
    protected function finishJob(Job $job)
    {
    }

    /**
     * Called when a job failed.
     *
     * @param Job        $job
     * @param \Exception $exception
     *
     * @deprecated
     */
    protected function failedJob(Job $job, \Exception $exception)
    {
    }

    /**
     * Called after a batch of jobs finishes.
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
     *
     * @return Process
     */
    private function getProcess(InputInterface $input): Process
    {
        if (!$this->process) {
            $finder = new PhpExecutableFinder();
            $php = $finder->find();

            $commandLine = "$php {$_SERVER['argv'][0]}  {$this->getName()}";
            $input->setOption('limit', 1);
            $input->setOption('no-interaction', true);
            $input->setOption('no-ansi', true);

            foreach ($input->getOptions() as $key => $option) {
                if (true === $option) {
                    $commandLine .= " --$key";
                    continue;
                }
                if (false !== $option && null !== $option) {
                    $commandLine .= " --$key=$option";
                }
            }
            $process = new Process($commandLine);

            $this->process = $process;
        }

        return $this->process;
    }

    /**
     * Get the number of seconds to delay a try.
     *
     * @param int $count
     *
     * @return int
     */
    protected function getRetryDelaySeconds(int $count): int
    {
        return $count * $count * 30;
    }
}
