<?php
/**
 * Created by mcfedr on 07/03/2016 10:36
 */

namespace Mcfedr\QueueManagerBundle\Command;

use Mcfedr\QueueManagerBundle\Manager\QueueManager;
use Mcfedr\QueueManagerBundle\Queue\Job;
use Mcfedr\QueueManagerBundle\Queue\TestJob;
use Psr\Log\LoggerInterface;

class TestRunnerCommand extends RunnerCommand
{
    private $options;

    public function __construct($name, array $options, QueueManager $queueManager, LoggerInterface $logger = null)
    {
        parent::__construct($name, $options, $queueManager, $logger);
        $this->options = $options;
    }

    /**
     * @return array
     */
    public function getOptions()
    {
        return $this->options;
    }

    protected function executeJob(Job $job)
    {
        $this->container->get('logger')->info('executing job');
        sleep(2);
        $this->container->get('logger')->info('finished job');
    }

    protected function getJob()
    {
        return new TestJob("a test job", [], []);
    }
}
