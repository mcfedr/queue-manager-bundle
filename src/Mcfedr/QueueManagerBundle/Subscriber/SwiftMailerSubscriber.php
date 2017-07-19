<?php

namespace Mcfedr\QueueManagerBundle\Subscriber;

use Mcfedr\QueueManagerBundle\Command\RunnerCommand;
use Mcfedr\QueueManagerBundle\Event\FailedJobEvent;
use Mcfedr\QueueManagerBundle\Event\FinishedJobEvent;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SwiftmailerBundle\EventListener\EmailSenderListener;
use Symfony\Component\DependencyInjection\ContainerInterface;

class SwiftMailerSubscriber extends EmailSenderListener
{
    /**
     * @var int
     */
    private $batchSize;

    /**
     * @var int
     */
    private $i = 0;

    public function __construct($batchSize, ContainerInterface $container, LoggerInterface $logger = null)
    {
        parent::__construct($container, $logger);
        $this->batchSize = $batchSize;
    }

    public function onJobFailed(FailedJobEvent $e)
    {
        if (++$this->i >= $this->batchSize) {
            $this->onTerminate();
            $this->i = 0;
        }
    }

    public function onJobFinished(FinishedJobEvent $e)
    {
        if (++$this->i >= $this->batchSize) {
            $this->onTerminate();
            $this->i = 0;
        }
    }

    public static function getSubscribedEvents()
    {
        return [
            RunnerCommand::JOB_FINISHED_EVENT => 'onJobFinished',
            RunnerCommand::JOB_FAILED_EVENT => 'onJobFailed',
        ];
    }
}
