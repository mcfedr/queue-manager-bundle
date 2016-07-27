<?php
/**
 * Created by mcfedr on 07/03/2016 10:36
 */

namespace Mcfedr\QueueManagerBundle\Command;

use Mcfedr\QueueManagerBundle\Manager\QueueManager;
use Mcfedr\QueueManagerBundle\Queue\Job;
use Mcfedr\QueueManagerBundle\Queue\TestJob;
use Mcfedr\QueueManagerBundle\Queue\TestRetryableJob;
use Psr\Log\LoggerInterface;

class TestRunnerCommand extends RunnerCommand
{
    private $options;

    public function __construct($name, array $options, QueueManager $queueManager)
    {
        parent::__construct($name, $options, $queueManager);
        $this->options = $options;
    }

    /**
     * @return array
     */
    public function getOptions()
    {
        return $this->options;
    }

    protected function getJob()
    {
        if (rand(1, 2) == 1) {
            return new TestRetryableJob('test_worker', [], []);
        }
        return new TestJob('test_worker', [], []);
    }
}
