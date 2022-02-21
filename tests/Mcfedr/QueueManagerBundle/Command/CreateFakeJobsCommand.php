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

    public function configure(): void
    {
        parent::configure();
        $this->setName('test:create-jobs')
            ->setDescription('Create a bunch of jobs')
            ->addOption('jobs', 'j', InputOption::VALUE_REQUIRED, 'How many jobs to create', '1000')
            ->addOption('manager', null, InputOption::VALUE_REQUIRED, 'Manager to put jobs into', 'delay')
            ->addOption('job', null, InputOption::VALUE_REQUIRED, 'Type of job to create', 'test_worker')
        ;
    }

    /**
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $jobsCount = (int) $input->getOption('jobs');

        for ($i = 0; $i < $jobsCount; ++$i) {
            $this->queueManagerRegistry->put($input->getOption('job'), [
                'job' => $i,
            ], [
                'delay' => 10,
            ], $input->getOption('manager'));
            $output->writeln("Job {$i}");
        }

        return 0;
    }
}
