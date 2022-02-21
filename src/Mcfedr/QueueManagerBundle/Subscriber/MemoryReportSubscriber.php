<?php

declare(strict_types=1);

namespace Mcfedr\QueueManagerBundle\Subscriber;

use Mcfedr\QueueManagerBundle\Event\FailedJobEvent;
use Mcfedr\QueueManagerBundle\Event\FinishedJobEvent;
use Mcfedr\QueueManagerBundle\Runner\JobExecutor;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class MemoryReportSubscriber implements EventSubscriberInterface
{
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function onJobFailed(FailedJobEvent $e): void
    {
        $this->report();
    }

    public function onJobFinished(FinishedJobEvent $e): void
    {
        if ($e->isInternal()) {
            return;
        }
        $this->report();
    }

    public static function getSubscribedEvents(): array
    {
        return [
            JobExecutor::JOB_FINISHED_EVENT => 'onJobFinished',
            JobExecutor::JOB_FAILED_EVENT => 'onJobFailed',
        ];
    }

    private function report(): void
    {
        $this->logger->info('Memory after job.', [
            'usage_bytes' => memory_get_usage(true),
            'peak_bytes' => memory_get_peak_usage(true),
        ]);
    }
}
