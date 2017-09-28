<?php

namespace Mcfedr\QueueManagerBundle\Subscriber;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\DBAL\Connection;
use Mcfedr\QueueManagerBundle\Event\FailedJobEvent;
use Mcfedr\QueueManagerBundle\Event\FinishedJobEvent;
use Mcfedr\QueueManagerBundle\Runner\JobExecutor;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class DoctrineResetSubscriber implements EventSubscriberInterface
{
    /**
     * @var Registry
     */
    private $doctrine;

    /**
     * @param Registry $doctrine
     */
    public function __construct(Registry $doctrine = null)
    {
        $this->doctrine = $doctrine;
    }

    public function onJobFailed(FailedJobEvent $e)
    {
        $this->reset();
    }

    public function onJobFinished(FinishedJobEvent $e)
    {
        $this->reset();
    }

    private function reset()
    {
        if ($this->doctrine) {
            /** @var Connection $c */
            $c = $this->doctrine->getConnection();
            $c->close();
            $this->doctrine->resetManager();
        }
    }

    public static function getSubscribedEvents()
    {
        return [
            JobExecutor::JOB_FINISHED_EVENT => 'onJobFinished',
            JobExecutor::JOB_FAILED_EVENT => 'onJobFailed',
        ];
    }
}
