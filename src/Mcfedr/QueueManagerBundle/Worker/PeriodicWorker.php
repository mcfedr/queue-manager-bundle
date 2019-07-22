<?php

declare(strict_types=1);

namespace Mcfedr\QueueManagerBundle\Worker;

use Carbon\Carbon;
use Mcfedr\QueueManagerBundle\Exception\UnrecoverableJobException;
use Mcfedr\QueueManagerBundle\Manager\QueueManagerRegistry;
use Mcfedr\QueueManagerBundle\Queue\InternalWorker;
use Mcfedr\QueueManagerBundle\Queue\PeriodicJob;
use Mcfedr\QueueManagerBundle\Runner\JobExecutor;

class PeriodicWorker implements InternalWorker
{
    /**
     * @var QueueManagerRegistry
     */
    private $queueManagerRegistry;

    /**
     * @var JobExecutor
     */
    private $jobExecutor;

    public function __construct(QueueManagerRegistry $queueManagerRegistry, JobExecutor $jobExecutor)
    {
        $this->queueManagerRegistry = $queueManagerRegistry;
        $this->jobExecutor = $jobExecutor;
    }

    public function execute(array $arguments): void
    {
        if (!isset($arguments['name']) || !isset($arguments['arguments']) || !isset($arguments['period']) || !isset($arguments['delay_options']) || !isset($arguments['delay_manager'])) {
            throw new UnrecoverableJobException('Missing arguments for periodic job');
        }

        if (!isset($arguments['job_tokens'])) {
            $arguments['job_tokens'] = PeriodicJob::generateJobTokens();
        }

        $job = new PeriodicJob($arguments['name'], $arguments['arguments'], $arguments['job_tokens']);
        $this->jobExecutor->executeJob($job);

        $nextJob = $job->generateNextJob();
        $arguments['job_tokens'] = $nextJob->getJobTokens();

        $nextRun = self::nextRun($arguments['period']);

        $this->queueManagerRegistry->put(self::class, $arguments, array_merge([
            'time' => $nextRun,
        ], $arguments['delay_options']), $arguments['delay_manager']);
    }

    public static function nextRun(int $periodLength): \DateTime
    {
        [$startOfNextPeriod, $endOfNextPeriod] = self::nextPeriod($periodLength);
        $time = random_int($startOfNextPeriod, $endOfNextPeriod);

        return new Carbon("@{$time}");
    }

    public static function nextPeriod(int $periodLength): array
    {
        $now = Carbon::now()->timestamp;
        $startOfNextPeriod = (int) ceil($now / $periodLength) * $periodLength;

        return [$startOfNextPeriod + 1, $startOfNextPeriod + $periodLength];
    }
}
