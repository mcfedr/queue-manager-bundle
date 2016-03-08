<?php
/**
 * Created by mcfedr on 07/03/2016 10:36
 */

namespace Mcfedr\QueueManagerBundle\Command;

class TestRunnerCommand extends RunnerCommand
{
    private $options;

    public function __construct($name, array $options)
    {
        parent::__construct($name, $options);
        $this->options = $options;
    }

    /**
     * @return array
     */
    public function getOptions()
    {
        return $this->options;
    }

    protected function executeJob($job)
    {
        // TODO: Implement executeJob() method.
    }

    protected function getJob()
    {
        return "test job";
    }
}
