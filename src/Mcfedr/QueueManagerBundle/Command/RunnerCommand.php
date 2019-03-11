<?php

declare(strict_types=1);

namespace Mcfedr\QueueManagerBundle\Command;

use Mcfedr\QueueManagerBundle\Exception\UnexpectedJobDataException;
use Mcfedr\QueueManagerBundle\Exception\UnrecoverableJobExceptionInterface;
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
    private const OK = 0;
    private const FAIL = 1;
    private const RETRY = 2;

    /**
     * @var ?LoggerInterface
     */
    protected $logger;

    /**
     * @var int
     */
    private $retryLimit = 3;

    /**
     * @var int
     */
    private $sleepSeconds = 5;

    /**
     * @var ?Process
     */
    private $process;

    /**
     * @var JobExecutor
     */
    private $jobExecutor;

    public function __construct(string $name, array $options, JobExecutor $jobExecutor, ?LoggerInterface $logger = null)
    {
        parent::__construct($name);
        if (\array_key_exists('retry_limit', $options)) {
            $this->retryLimit = $options['retry_limit'];
        }
        if (\array_key_exists('sleep_seconds', $options)) {
            $this->sleepSeconds = $options['sleep_seconds'];
        }
        $this->jobExecutor = $jobExecutor;
        $this->logger = $logger;
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Run a queue runner')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Only run [limit] batches of jobs', 0)
            ->addOption('process-isolation', null, InputOption::VALUE_NONE, 'New processes for each job')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): void
    {
        $this->handleInput($input);

        $limit = (int) $input->getOption('limit');
        $ignoreLimit = 0 === $limit;
        $running = true;

        if (\function_exists('pcntl_signal')) {
            $handle = function ($sig) use (&$running): void {
                if ($this->logger) {
                    $this->logger->debug("Received signal (${sig}), stopping...");
                }
                $running = false;
            };
            pcntl_signal(SIGTERM, $handle);
            pcntl_signal(SIGINT, $handle);
        }

        do {
            if ($input->getOption('process-isolation')) {
                $this->executeBatchWithProcess($input, $output);
            } else {
                $this->executeBatch();
            }

            if (\function_exists('pcntl_signal')) {
                pcntl_signal_dispatch();
            }

            gc_collect_cycles();
        } while ($running && ($ignoreLimit || --$limit > 0));
    }

    protected function executeBatch(): void
    {
        try {
            $jobs = $this->getJobs();
            if (\count($jobs)) {
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
                if ($this->logger) {
                    $this->logger->debug('No jobs, sleeping...', [
                        'sleepSeconds' => $this->sleepSeconds,
                    ]);
                }
                sleep($this->sleepSeconds);
            }
        } catch (UnexpectedJobDataException $e) {
            if ($this->logger) {
                $this->logger->warning('Found unexpected job data in the queue', [
                    'message' => $e->getMessage(),
                ]);
            }
        }
    }

    protected function executeBatchWithProcess(InputInterface $input, OutputInterface $output): void
    {
        /** @var Process $process */
        $process = $this->getProcess($input);

        $process->mustRun(function ($type, $data) use ($output): void {
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
            return self::FAIL;
        } catch (\Exception $e) {
            return self::RETRY;
        }

        return self::OK;
    }

    /**
     * @throws UnexpectedJobDataException
     *
     * @return Job[]
     */
    abstract protected function getJobs(): array;

    /**
     * Called after a batch of jobs finishes.
     *
     * @param Job[] $okJobs
     * @param Job[] $retryJobs
     * @param Job[] $failedJobs
     */
    abstract protected function finishJobs(array $okJobs, array $retryJobs, array $failedJobs): void;

    protected function handleInput(InputInterface $input): void
    {
        // Allows overriding
    }

    /**
     * Get the number of seconds to delay a try.
     */
    protected function getRetryDelaySeconds(int $count): int
    {
        return $count * $count * 30;
    }

    private function getProcess(InputInterface $input): Process
    {
        if (!$this->process) {
            $finder = new PhpExecutableFinder();
            $php = $finder->find();

            $commandLine = "${php} {$_SERVER['argv'][0]}  {$this->getName()}";
            $input->setOption('limit', '1');
            $input->setOption('no-interaction', true);
            $input->setOption('no-ansi', true);

            foreach ($input->getOptions() as $key => $option) {
                if (true === $option) {
                    $commandLine .= " --${key}";

                    continue;
                }
                if (false !== $option && null !== $option) {
                    $commandLine .= " --${key}=${option}";
                }
            }
            $process = new Process($commandLine);

            $this->process = $process;
        }

        return $this->process;
    }
}
