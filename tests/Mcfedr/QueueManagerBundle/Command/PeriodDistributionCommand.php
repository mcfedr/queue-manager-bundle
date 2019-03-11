<?php

declare(strict_types=1);

namespace Mcfedr\QueueManagerBundle\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

abstract class PeriodDistributionCommand extends Command
{
    public function configure(): void
    {
        $this->addOption('period', 'p', InputOption::VALUE_REQUIRED, 'How often the job repeats (seconds)', '21600')
            ->addOption('jobs', 'j', InputOption::VALUE_REQUIRED, 'How many jobs to run', '100000')
            ->addOption('iterations', 'i', InputOption::VALUE_REQUIRED, 'How many times to run each job', '100')
        ;
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $period = (int) $input->getOption('period');
        $jobsCount = (int) $input->getOption('jobs');
        $iterations = (int) $input->getOption('iterations');

        $jobs = [];
        for ($i = 0; $i < $jobsCount; ++$i) {
            $jobs[] = $this->job($period);
        }

        for ($i = 0; $i < $iterations; ++$i) {
            foreach ($jobs as $job) {
                $time = $job();
                $output->writeln($time);
            }
        }

        return 0;
    }

    abstract protected function job($period);
}
