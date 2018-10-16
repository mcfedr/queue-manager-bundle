<?php

declare(strict_types=1);

namespace Mcfedr\QueueManagerBundle\Tests\Command;

use Mcfedr\QueueManagerBundle\Command\RunnerCommand;
use Mcfedr\QueueManagerBundle\Exception\TestException;
use Mcfedr\QueueManagerBundle\Exception\UnrecoverableJobException;
use Mcfedr\QueueManagerBundle\Exception\UnrecoverableJobExceptionInterface;
use Mcfedr\QueueManagerBundle\Manager\QueueManager;
use Mcfedr\QueueManagerBundle\Queue\Job;
use Mcfedr\QueueManagerBundle\Queue\RetryableJob;
use Mcfedr\QueueManagerBundle\Queue\Worker;
use Mcfedr\QueueManagerBundle\Runner\JobExecutor;
use PHPUnit\Framework\Constraint\IsInstanceOf;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\EventDispatcher\EventDispatcher;

class RunnerCommandTest extends TestCase
{
    public function testExecuteJob()
    {
        $job = $this->getMockJob(Job::class);

        $worker = $this->getMockWorker();

        $manager = $this->getMockBuilder(QueueManager::class)->getMock();
        $methods = ['getJobs', 'finishJobs'];
        $command = $this->getMockCommand($methods, $manager, [$job], $this->getJobExecutor($worker));

        $command->expects($this->once())
            ->method('finishJobs')
            ->with([$job], [], []);

        $this->executeCommand($command);
    }

    public function testExecutePermanentlyFailingJob()
    {
        $job = $this->getMockJob(Job::class);

        $exce = new UnrecoverableJobException();

        $worker = $this->getMockWorker($exce);

        $manager = $this->getMockBuilder(QueueManager::class)->getMock();
        $methods = ['getJobs', 'finishJobs'];
        $command = $this->getMockCommand($methods, $manager, [$job], $this->getJobExecutor($worker));

        $command->expects($this->once())
            ->method('finishJobs')
            ->with([], [], [$job]);

        $this->executeCommand($command);
    }

    public function testExecutePermanentlyFailingJobUsingInterface()
    {
        $job = $this->getMockJob(Job::class);

        $exce = new TestException();

        $worker = $this->getMockWorker($exce);

        $manager = $this->getMockBuilder(QueueManager::class)->getMock();
        $methods = ['getJobs', 'finishJobs'];
        $command = $this->getMockCommand($methods, $manager, [$job], $this->getJobExecutor($worker));

        $command->expects($this->once())
            ->method('finishJobs')
            ->with([], [], [$job]);

        $this->executeCommand($command);
    }

    public function testExecuteInvalidWorkerJob()
    {
        $job = $this->getMockJob(Job::class);

        $manager = $this->getMockBuilder(QueueManager::class)->getMock();
        $methods = ['getJobs', 'finishJobs'];
        $worker = new \stdClass();

        $command = $this->getMockCommand($methods, $manager, [$job], $this->getJobExecutor($worker));

        $command->expects($this->once())
            ->method('finishJobs')
            ->with([], [], [$job]);

        $this->executeCommand($command);
    }

    public function testExecuteFailingJob()
    {
        $job = $this->getMockJob(Job::class);

        $exce = new \Exception();

        $worker = $this->getMockWorker($exce);

        $manager = $this->getMockBuilder(QueueManager::class)->getMock();
        $methods = ['getJobs', 'finishJobs'];
        $command = $this->getMockCommand($methods, $manager, [$job], $this->getJobExecutor($worker));

        $command->expects($this->once())
            ->method('finishJobs')
            ->with([], [], [$job]);

        $this->executeCommand($command);
    }

    public function testExecuteFailingRetryableJob()
    {
        $job = $this->getMockJob(RetryableJob::class);

        $exce = new \Exception();

        $worker = $this->getMockWorker($exce);

        $manager = $this->getMockBuilder(QueueManager::class)->getMock();
        $methods = ['getJobs', 'finishJobs'];
        $command = $this->getMockCommand($methods, $manager, [$job], $this->getJobExecutor($worker));

        $command->expects($this->once())
            ->method('finishJobs')
            ->with([], [$job], []);

        $this->executeCommand($command);
    }

    public function testExecutePermanentlyFailingRetryableJob()
    {
        $job = $this->getMockJob(RetryableJob::class);

        $exce = new UnrecoverableJobException();

        $worker = $this->getMockWorker($exce);

        $manager = $this->getMockBuilder(QueueManager::class)->getMock();
        $methods = ['getJobs', 'finishJobs'];
        $command = $this->getMockCommand($methods, $manager, [$job], $this->getJobExecutor($worker));

        $command->expects($this->once())
            ->method('finishJobs')
            ->with([], [], [$job]);

        $this->executeCommand($command);
    }

    public function testExecuteManyJob()
    {
        $jobs = [$this->getMockJob(Job::class), $this->getMockJob(Job::class)];

        $worker = $this->getMockWorker(null, 2);

        $manager = $this->getMockBuilder(QueueManager::class)->getMock();
        $methods = ['getJobs', 'finishJobs'];
        $command = $this->getMockCommand($methods, $manager, $jobs, $this->getJobExecutor($worker));

        $command->expects($this->once())
            ->method('finishJobs')
            ->with($jobs, [], []);

        $this->executeCommand($command);
    }

    public function testExecuteManyFailingJob()
    {
        $jobs = [$this->getMockJob(Job::class), $this->getMockJob(Job::class)];

        $exce = new UnrecoverableJobException();

        $worker = $this->getMockWorker($exce, 2);

        $manager = $this->getMockBuilder(QueueManager::class)->getMock();
        $methods = ['getJobs', 'finishJobs'];
        $command = $this->getMockCommand($methods, $manager, $jobs, $this->getJobExecutor($worker));

        $command->expects($this->once())
            ->method('finishJobs')
            ->with([], [], $jobs);

        $this->executeCommand($command);
    }

    public function testExecuteEvents()
    {
        $jobs = [$this->getMockJob(Job::class), $this->getMockJob(Job::class)];

        $worker = $this->getMockWorker(null, 2);

        $eventDispatcher = $this->getMockBuilder(EventDispatcher::class)
            ->disableOriginalConstructor()
            ->getMock();

        $eventDispatcher->expects($this->exactly(6))
            ->method('dispatch')
            ->withConsecutive(
                [JobExecutor::JOB_BATCH_START_EVENT],
                [JobExecutor::JOB_START_EVENT],
                [JobExecutor::JOB_FINISHED_EVENT],
                [JobExecutor::JOB_START_EVENT],
                [JobExecutor::JOB_FINISHED_EVENT],
                [JobExecutor::JOB_BATCH_FINISHED_EVENT]
            );

        $manager = $this->getMockBuilder(QueueManager::class)->getMock();
        $methods = ['getJobs', 'finishJobs'];
        $command = $this->getMockCommand($methods, $manager, $jobs, $this->getJobExecutor($worker, $eventDispatcher));

        $command->expects($this->once())
            ->method('finishJobs')
            ->with($jobs, [], []);

        $this->executeCommand($command);
    }

    /**
     * @param $class
     *
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
    private function getMockJob($class)
    {
        $job = $this->getMockBuilder($class)
            ->getMock();
        $job->method('getName')
            ->willReturn('worker');
        $job->method('getArguments')
            ->willReturn([]);

        return $job;
    }

    /**
     * @param $exce
     *
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
    private function getMockWorker(\Exception $exce = null, $count = 1)
    {
        $worker = $this->getMockBuilder(Worker::class)
            ->getMock();
        $execute = $worker->expects($this->exactly($count))
            ->method('execute')
            ->with([]);
        if ($exce) {
            $execute->willThrowException($exce);
        }

        return $worker;
    }

    /**
     * @param string[]     $methods
     * @param QueueManager $manager
     * @param Job[]        $jobs
     *
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
    private function getMockCommand(array $methods, QueueManager $manager, array $jobs, $executor)
    {
        $command = $this
            ->getMockBuilder(RunnerCommand::class)
            ->setConstructorArgs(['mcfedr:queue:default-runner', [], $manager, $executor])
            ->setMethods($methods)
            ->getMock();

        $command->expects($this->once())
            ->method('getJobs')
            ->willReturn($jobs);

        return $command;
    }

    private function getJobExecutor($worker, EventDispatcher $eventDispatcher = null)
    {
        $container = new Container();
        $container->set('worker', $worker);

        return new JobExecutor($container, $eventDispatcher);
    }

    /**
     * @param $command
     * @param $worker
     */
    private function executeCommand(Command $command)
    {
        $application = new Application();
        $application->add($command);

        $command = $application->find('mcfedr:queue:default-runner');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            '--limit' => 1,
        ]);
    }
}
