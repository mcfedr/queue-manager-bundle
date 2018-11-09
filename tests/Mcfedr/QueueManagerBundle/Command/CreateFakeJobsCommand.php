<?php

declare(strict_types=1);

namespace Mcfedr\QueueManagerBundle\Command;

use Mcfedr\QueueManagerBundle\Manager\QueueManagerRegistry;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CreateFakeJobsCommand extends Command
{
    /**
     * @var QueueManagerRegistry
     */
    private $queueManagerRegistry;

    public function __construct(QueueManagerRegistry $queueManagerRegistry)
    {
        $this->queueManagerRegistry = $queueManagerRegistry;
        parent::__construct();
    }

    public function configure()
    {
        parent::configure();
        $this->setName('test:create-jobs')
            ->setDescription('Create a bunch of jobs')
            ->addOption('jobs', 'j', InputOption::VALUE_REQUIRED, 'How many jobs to create', '1000');
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $jobsCount = (int) $input->getOption('jobs');

        for ($i = 0; $i < $jobsCount; ++$i) {
            $this->queueManagerRegistry->put('test_worker', [
                'job' => $i,
            ], [
                'delay' => 10,
            ], 'delay');
            $output->writeln("Job $i");
        }
    }
}
