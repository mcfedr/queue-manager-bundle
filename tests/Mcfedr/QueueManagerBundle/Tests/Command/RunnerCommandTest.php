<?php
/**
 * Created by mcfedr on 17/03/2016 21:39
 */


namespace Mcfedr\QueueManagerBundle\Tests\Command;

use Mcfedr\QueueManagerBundle\Command\RunnerCommand;
use Mcfedr\QueueManagerBundle\Exception\InvalidWorkerException;
use Mcfedr\QueueManagerBundle\Exception\UnrecoverableJobException;
use Mcfedr\QueueManagerBundle\Manager\QueueManager;
use Mcfedr\QueueManagerBundle\Manager\RetryingQueueManager;
use Mcfedr\QueueManagerBundle\Queue\Job;
use Mcfedr\QueueManagerBundle\Queue\RetryableJob;
use Mcfedr\QueueManagerBundle\Queue\Worker;
use Mcfedr\QueueManagerBundle\Runner\JobExecutor;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\Container;

class RunnerCommandTest extends \PHPUnit_Framework_TestCase
{
    public function testExecuteJob()
    {
        $job = $this->getMockJob(Job::class);

        $worker = $this->getMockWorker();

        $manager = $this->getMockBuilder(QueueManager::class)->getMock();
        $methods = ['getJob', 'finishJob'];
        $command = $this->getMockCommand($methods, $manager, $job);

        $command->expects($this->once())
            ->method('finishJob')
            ->with($job);

        $this->executeCommand($command, $worker);
    }

    public function testExecutePermanentlyFailingJob()
    {
        $job = $this->getMockJob(Job::class);

        $exce = new UnrecoverableJobException();

        $worker = $this->getMockWorker($exce);

        $manager = $this->getMockBuilder(QueueManager::class)->getMock();
        $methods = ['getJob', 'failedJob'];
        $command = $this->getMockCommand($methods, $manager, $job);

        $command->expects($this->once())
            ->method('failedJob')
            ->with($job, $exce);

        $this->executeCommand($command, $worker);
    }

    public function testExecuteInvalidWorkerJob()
    {
        $job = $this->getMockJob(Job::class);

        $manager = $this->getMockBuilder(QueueManager::class)->getMock();
        $methods = ['getJob', 'failedJob'];
        $command = $this->getMockCommand($methods, $manager, $job);

        $command->expects($this->once())
            ->method('failedJob')
            ->with($job, new \PHPUnit_Framework_Constraint_IsInstanceOf(InvalidWorkerException::class));

        $worker = new \stdClass();

        $this->executeCommand($command, $worker);
    }

    public function testExecuteFailingJob()
    {
        $job = $this->getMockJob(Job::class);

        $exce = new \Exception();

        $worker = $this->getMockWorker($exce);

        $manager = $this->getMockBuilder(QueueManager::class)->getMock();
        $methods = ['getJob', 'failedJob'];
        $command = $this->getMockCommand($methods, $manager, $job);

        $command->expects($this->once())
            ->method('failedJob')
            ->with($job, $exce);

        $this->executeCommand($command, $worker);
    }

    public function testExecuteFailingRetryableJob()
    {
        $job = $this->getMockJob(RetryableJob::class);

        $exce = new \Exception();

        $worker = $this->getMockWorker($exce);

        $manager = $this->getMockBuilder(QueueManager::class)->getMock();
        $methods = ['getJob', 'failedJob'];
        $command = $this->getMockCommand($methods, $manager, $job);

        $command->expects($this->once())
            ->method('failedJob')
            ->with($job, $exce);

        $this->executeCommand($command, $worker);
    }

    public function testExecutePermanentlyFailingRetryableJob()
    {
        $job = $this->getMockJob(RetryableJob::class);

        $exce = new UnrecoverableJobException();

        $worker = $this->getMockWorker($exce);

        $manager = $this->getMockBuilder(QueueManager::class)->getMock();
        $methods = ['getJob', 'failedJob'];
        $command = $this->getMockCommand($methods, $manager, $job);

        $command->expects($this->once())
            ->method('failedJob')
            ->with($job, $exce);

        $this->executeCommand($command, $worker);
    }

    public function testExecuteFailingRetryableJobWithRetryingManager()
    {
        $job = $this->getMockJob(RetryableJob::class);

        $exce = new \Exception();

        $worker = $this->getMockWorker($exce);

        $manager = $this->getMockBuilder(RetryingQueueManager::class)->getMock();
        $manager->expects($this->once())
            ->method('retry')
            ->with($job, $exce);

        $methods = ['getJob', 'failedJob'];
        $command = $this->getMockCommand($methods, $manager, $job);

        $command->expects($this->once())
            ->method('failedJob')
            ->with($job, $exce);

        $this->executeCommand($command, $worker);
    }

    public function testExecutePermanentlyFailingRetryableJobWithRetryingManager()
    {
        $job = $this->getMockJob(RetryableJob::class);

        $exce = new UnrecoverableJobException();

        $worker = $this->getMockWorker($exce);

        $manager = $this->getMockBuilder(RetryingQueueManager::class)->getMock();
        $manager->expects($this->exactly(0))
            ->method('retry');

        $methods = ['getJob', 'failedJob'];
        $command = $this->getMockCommand($methods, $manager, $job);

        $command->expects($this->once())
            ->method('failedJob')
            ->with($job, $exce);

        $this->executeCommand($command, $worker);
    }

    /**
     * @param $class
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
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
    private function getMockWorker(\Exception $exce = null)
    {
        $worker = $this->getMockBuilder(Worker::class)
            ->getMock();
        $execute = $worker->expects($this->once())
            ->method('execute')
            ->with([]);
        if ($exce) {
            $execute->willThrowException($exce);
        }

        return $worker;
    }

    /**
     * @param $methods
     * @param $manager
     * @param $job
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
    private function getMockCommand(array $methods, QueueManager $manager, Job $job)
    {
        $command = $this
            ->getMockBuilder(RunnerCommand::class)
            ->setConstructorArgs(['mcfedr:queue:default-runner', [], $manager])
            ->setMethods($methods)
            ->getMock();

        $command->expects($this->once())
            ->method('getJob')
            ->willReturn($job);
        return $command;
    }

    /**
     * @param $command
     * @param $worker
     */
    private function executeCommand(Command $command, $worker)
    {
        $executor = new JobExecutor();

        $container = new Container();
        $container->set('worker', $worker);
        $container->set('mcfedr_queue_manager.job_executor', $executor);

        $command->setContainer($container);
        $executor->setContainer($container);

        $application = new Application();
        $application->add($command);

        $command = $application->find('mcfedr:queue:default-runner');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            '--limit' => 1
        ]);
    }
}
