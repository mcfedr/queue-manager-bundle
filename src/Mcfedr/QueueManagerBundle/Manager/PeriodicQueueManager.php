<?php

declare(strict_types=1);

namespace Mcfedr\QueueManagerBundle\Manager;

use Mcfedr\QueueManagerBundle\Exception\NoSuchJobException;
use Mcfedr\QueueManagerBundle\Exception\WrongJobException;
use Mcfedr\QueueManagerBundle\Queue\Job;
use Mcfedr\QueueManagerBundle\Queue\PeriodicJob;
use Mcfedr\QueueManagerBundle\Worker\PeriodicWorker;

class PeriodicQueueManager implements QueueManager
{
    /**
     * @var QueueManagerRegistry
     */
    private $queueManagerRegistry;

    /**
     * @var string
     */
    private $delayManager;

    /**
     * @var array
     */
    private $delayManagerOptions = [];

    public function __construct(QueueManagerRegistry $queueManagerRegistry, array $options)
    {
        $this->queueManagerRegistry = $queueManagerRegistry;
        $this->delayManager = $options['delay_manager'];
        $this->delayManagerOptions = $options['delay_manager_options'];
    }

    public function put(string $name, array $arguments = [], array $options = []): Job
    {
        if (\array_key_exists('delay_manager_options', $options)) {
            $jobOptions = array_merge($this->delayManagerOptions, $options['delay_manager_options']);
        } else {
            $jobOptions = array_merge($this->delayManagerOptions, array_diff_key($options, ['period' => 1, 'time' => 1, 'delay' => 1]));
        }

        if (\array_key_exists('delay_manager', $options)) {
            $jobManager = $options['delay_manager'];
        } else {
            $jobManager = $this->delayManager;
        }

        if (isset($options['period'])) {
            $period = $options['period'];
        } else {
            return $this->queueManagerRegistry->put($name, $arguments, $jobOptions, $jobManager);
        }

        $periodicJob = new PeriodicJob($name, $arguments, PeriodicJob::generateJobTokens());

        $this->queueManagerRegistry->put(PeriodicWorker::class, [
            'name' => $name,
            'arguments' => $arguments,
            'period' => $period,
            'job_tokens' => $periodicJob->getJobTokens(),
            'delay_options' => $jobOptions,
            'delay_manager' => $jobManager,
        ], array_merge([
            'time' => PeriodicWorker::nextRun($period),
        ], $jobOptions), $jobManager);

        return $periodicJob;
    }

    public function delete(Job $job): void
    {
        if (!$job instanceof PeriodicJob) {
            throw new WrongJobException('Periodic queue manager can only delete Periodic jobs');
        }

        throw new NoSuchJobException('Periodic queue manager cannot delete jobs');
    }
}
