<?php

declare(strict_types=1);

namespace Mcfedr\QueueManagerBundle\Subscriber;

use Mcfedr\QueueManagerBundle\Event\FailedJobEvent;
use Mcfedr\QueueManagerBundle\Event\FinishedJobEvent;
use Mcfedr\QueueManagerBundle\Runner\JobExecutor;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SwiftmailerBundle\EventListener\EmailSenderListener;
use Symfony\Component\DependencyInjection\ContainerInterface;

class SwiftMailerSubscriber extends EmailSenderListener
{
    private int $batchSize;
    private int $i = 0;

    public function __construct(int $batchSize, ContainerInterface $container, ?LoggerInterface $logger = null)
    {
        parent::__construct($container, $logger);
        $this->batchSize = $batchSize;
    }

    public function onJobFailed(FailedJobEvent $e): void
    {
        if ($e->isInternal()) {
            return;
        }
        if (++$this->i >= $this->batchSize) {
            $this->onTerminate();
            $this->i = 0;
        }
    }

    public function onJobFinished(FinishedJobEvent $e): void
    {
        if ($e->isInternal()) {
            return;
        }
        if (++$this->i >= $this->batchSize) {
            $this->onTerminate();
            $this->i = 0;
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            JobExecutor::JOB_FINISHED_EVENT => 'onJobFinished',
            JobExecutor::JOB_FAILED_EVENT => 'onJobFailed',
        ];
    }
}
