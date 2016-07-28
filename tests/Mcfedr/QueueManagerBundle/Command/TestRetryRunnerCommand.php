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

// Only one command can exist per class https://github.com/symfony/symfony/issues/19001#issuecomment-235312008
class TestRetryRunnerCommand extends TestRunnerCommand
{

}
