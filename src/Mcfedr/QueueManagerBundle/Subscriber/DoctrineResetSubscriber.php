<?php

declare(strict_types=1);

namespace Mcfedr\QueueManagerBundle\Subscriber;

use Doctrine\DBAL\Connection;
use Doctrine\Persistence\ManagerRegistry;
use Mcfedr\QueueManagerBundle\Event\FailedJobEvent;
use Mcfedr\QueueManagerBundle\Event\FinishedJobEvent;
use Mcfedr\QueueManagerBundle\Runner\JobExecutor;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class DoctrineResetSubscriber implements EventSubscriberInterface
{
    /**
     * @var ?ManagerRegistry
     */
    private $doctrine;

    public function __construct(?ManagerRegistry $doctrine = null)
    {
        $this->doctrine = $doctrine;
    }

    public function onJobFailed(FailedJobEvent $e): void
    {
        $this->reset();
    }

    public function onJobFinished(FinishedJobEvent $e): void
    {
        $this->reset();
    }

    public static function getSubscribedEvents()
    {
        return [
            JobExecutor::JOB_FINISHED_EVENT => 'onJobFinished',
            JobExecutor::JOB_FAILED_EVENT => 'onJobFailed',
        ];
    }

    private function reset(): void
    {
        if ($this->doctrine) {
            /** @var Connection $c */
            $c = $this->doctrine->getConnection();
            $c->close();
            $this->doctrine->resetManager();
        }
    }
}
