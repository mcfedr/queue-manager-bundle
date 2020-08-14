<?php

declare(strict_types=1);

namespace Mcfedr\QueueManagerBundle\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;
use Symfony\Component\Stopwatch\Stopwatch;

class ProcessSpeedCommand extends Command
{
    /**
     * @var Process
     */
    private $process;

    public function configure(): void
    {
        parent::configure();
        $this
            ->setName('test:process-speed')
            ->setDescription('Compare the speed of running jobs using different process management')
            ->addOption('do-job', null, InputOption::VALUE_NONE, 'Do a single job')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($input->getOption('do-job')) {
            $this->job();

            return 0;
        }

        $count = 10;
        $stopwatch = new Stopwatch(true);

        foreach ([[$this, 'basic'], [$this, 'fork'], [$this, 'process']] as $kind) {
            $stopwatch->start($kind[1]);
            $kind($count);
            $event = $stopwatch->stop($kind[1]);
            $output->writeln("{$kind[1]}: {$event->getDuration()}m");
        }

        return 0;
    }

    private function basic($count): void
    {
        for ($i = 0; $i < $count; ++$i) {
            $this->job();
        }
    }

    private function fork($count): void
    {
        for ($i = 0; $i < $count; ++$i) {
            if (pcntl_fork() === 0) {
                $this->job();
                exit(0);
            }
            pcntl_wait($status);
        }
    }

    private function process($count): void
    {
        for ($i = 0; $i < $count; ++$i) {
            $this->getProcess()->run();
        }
    }

    private function getProcess(): Process
    {
        if (!$this->process) {
            $finder = new PhpExecutableFinder();
            $php = $finder->find();

            $commandLine = [
                $php,
                $_SERVER['argv'][0],
                $this->getName(),
                '--do-job',
                '--no-interaction',
                '--no-ansi',
            ];
            $process = new Process($commandLine);
            $this->process = $process;
        }

        return $this->process;
    }

    private function job(): void
    {
        usleep(300 * 1000);
    }
}
