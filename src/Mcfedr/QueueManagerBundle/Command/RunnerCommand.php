<?php
/**
 * Created by mcfedr on 05/03/2016 15:43
 */

namespace Mcfedr\QueueManagerBundle\Command;

use Mcfedr\QueueManagerBundle\Exception\FailedToForkException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

abstract class RunnerCommand extends Command
{
    public function __construct($name, array $options)
    {
        parent::__construct($name);
    }

    protected function configure()
    {
        $this
            ->setDescription('Run a queue runner')
            ->addOption('once', null, InputOption::VALUE_NONE, 'Run just one job')
            ->addOption('singleProcess', null, InputOption::VALUE_NONE, 'Run all jobs in the same process');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $once = $input->getOption('once');
        $singleProcess = $input->getOption('singleProcess');
        do {
            $job = $this->getJob();
            if ($singleProcess) {
                $this->executeJob($job);
            } else {
                $this->executeJobInNewProcess($job);
            }
        }
        while (!$once);
    }

    protected function executeJobInNewProcess($job)
    {
        $pid = pcntl_fork();
        if ($pid === -1) {
            throw new FailedToForkException("Failed to fork");
        } else if ($pid) {
            pcntl_wait($status);
        } else {
            $this->executeJob($job);
        }
    }

    abstract protected function executeJob($job);

    abstract protected function getJob();
}
