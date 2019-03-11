<?php

declare(strict_types=1);

namespace Mcfedr\QueueManagerBundle\Command;

use Mcfedr\QueueManagerBundle\Exception\UnexpectedJobDataException;
use Mcfedr\QueueManagerBundle\Queue\Job;
use Mcfedr\QueueManagerBundle\Queue\JobBatch;
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

    /**
     * @var string
     *
     * This is a chunk of memory that is reserved for use when handling out
     * of memory errors
     */
    private $reservedMemory;

    /**
     * @var JobBatch
     *
     * Stored for fatal exception handling, so we know what job was running
     */
    private $jobs;

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

    public function shutdown(): void
    {
        $this->reservedMemory = null;

        if (null === $error = error_get_last()) {
            return;
        }

        if (!$this->jobs || !$this->jobs->current()) {
            return;
        }

        $e = new \ErrorException(@$error['message'], 0, @$error['type'], @$error['file'], @$error['line']);
        $this->jobs->result($e);

        $this->finishJobs($this->jobs);
        $this->jobExecutor->finishBatch($this->jobs);
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

        $this->reservedMemory = str_repeat('x', 1024 * 10);
        register_shutdown_function([$this, 'shutdown']);

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
            $this->jobs = $this->getJobs();
            if ($this->jobs) {
                $this->jobExecutor->startBatch($this->jobs);
                while (($job = $this->jobs->next())) {
                    $result = $this->executeJob($job);
                    $this->jobs->result($result);
                }
                $this->finishJobs($this->jobs);
                $this->jobExecutor->finishBatch($this->jobs);
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
    protected function executeJob(Job $job): ?\Throwable
    {
        try {
            $this->jobExecutor->executeJob($job, $this->retryLimit);
        } catch (\Throwable $e) {
            return $e;
        }

        return null;
    }

    /**
     * @throws UnexpectedJobDataException
     */
    abstract protected function getJobs(): ?JobBatch;

    /**
     * Called after a batch of jobs finishes.
     */
    abstract protected function finishJobs(JobBatch $batch): void;

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
