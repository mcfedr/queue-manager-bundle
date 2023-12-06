<?php

declare(strict_types=1);

namespace Mcfedr\QueueManagerBundle\Tests\Command;

use Mcfedr\QueueManagerBundle\Command\RunnerCommand;
use Mcfedr\QueueManagerBundle\Event\FinishedJobBatchEvent;
use Mcfedr\QueueManagerBundle\Event\FinishedJobEvent;
use Mcfedr\QueueManagerBundle\Event\StartJobBatchEvent;
use Mcfedr\QueueManagerBundle\Event\StartJobEvent;
use Mcfedr\QueueManagerBundle\Exception\TestException;
use Mcfedr\QueueManagerBundle\Exception\UnrecoverableJobException;
use Mcfedr\QueueManagerBundle\Queue\Job;
use Mcfedr\QueueManagerBundle\Queue\JobBatch;
use Mcfedr\QueueManagerBundle\Queue\RetryableJob;
use Mcfedr\QueueManagerBundle\Queue\Worker;
use Mcfedr\QueueManagerBundle\Runner\JobExecutor;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
final class RunnerCommandTest extends TestCase
{
    public function testExecuteJob(): void
    {
        $job = $this->getMockJob(Job::class);

        $worker = $this->getMockWorker();

        $methods = ['getJobs', 'finishJobs'];
        $command = $this->getMockCommand($methods, new JobBatch([$job]), $this->getJobExecutor($worker));

        $command->expects(self::once())
            ->method('finishJobs')
            ->with(self::callback(function (JobBatch $batch) use ($job): bool {
                $this->assertEquals([$job], $batch->getOks());

                return true;
            }))
        ;

        $this->executeCommand($command);
    }

    public function testExecutePermanentlyFailingJob(): void
    {
        $job = $this->getMockJob(Job::class);

        $exce = new UnrecoverableJobException();

        $worker = $this->getMockWorker($exce);

        $methods = ['getJobs', 'finishJobs'];
        $command = $this->getMockCommand($methods, new JobBatch([$job]), $this->getJobExecutor($worker));

        $command->expects(self::once())
            ->method('finishJobs')
            ->with(self::callback(function (JobBatch $batch) use ($job): bool {
                $this->assertEquals([$job], $batch->getFails());

                return true;
            }))
        ;

        $this->executeCommand($command);
    }

    public function testExecutePermanentlyFailingJobUsingInterface(): void
    {
        $job = $this->getMockJob(Job::class);

        $exce = new TestException();

        $worker = $this->getMockWorker($exce);

        $methods = ['getJobs', 'finishJobs'];
        $command = $this->getMockCommand($methods, new JobBatch([$job]), $this->getJobExecutor($worker));

        $command->expects(self::once())
            ->method('finishJobs')
            ->with(self::callback(function (JobBatch $batch) use ($job): bool {
                $this->assertEquals([$job], $batch->getFails());

                return true;
            }))
        ;

        $this->executeCommand($command);
    }

    public function testExecuteInvalidWorkerJob(): void
    {
        $job = $this->getMockJob(Job::class);

        $methods = ['getJobs', 'finishJobs'];
        $worker = new \stdClass();

        $command = $this->getMockCommand($methods, new JobBatch([$job]), $this->getJobExecutor($worker));

        $command->expects(self::once())
            ->method('finishJobs')
            ->with(self::callback(function (JobBatch $batch) use ($job): bool {
                $this->assertEquals([$job], $batch->getFails());

                return true;
            }))
        ;

        $this->executeCommand($command);
    }

    public function testExecuteFailingJob(): void
    {
        $job = $this->getMockJob(Job::class);

        $exce = new \Exception();

        $worker = $this->getMockWorker($exce);

        $methods = ['getJobs', 'finishJobs'];
        $command = $this->getMockCommand($methods, new JobBatch([$job]), $this->getJobExecutor($worker));

        $command->expects(self::once())
            ->method('finishJobs')
            ->with(self::callback(function (JobBatch $batch) use ($job): bool {
                $this->assertEquals([$job], $batch->getFails());

                return true;
            }))
        ;

        $this->executeCommand($command);
    }

    public function testExecuteFailingRetryableJob(): void
    {
        $job = $this->getMockJob(RetryableJob::class);

        $exce = new \Exception();

        $worker = $this->getMockWorker($exce);

        $methods = ['getJobs', 'finishJobs'];
        $command = $this->getMockCommand($methods, new JobBatch([$job]), $this->getJobExecutor($worker));

        $command->expects(self::once())
            ->method('finishJobs')
            ->with(self::callback(function (JobBatch $batch) use ($job): bool {
                $this->assertEquals([$job], $batch->getRetries());

                return true;
            }))
        ;

        $this->executeCommand($command);
    }

    public function testExecutePermanentlyFailingRetryableJob(): void
    {
        $job = $this->getMockJob(RetryableJob::class);

        $exce = new UnrecoverableJobException();

        $worker = $this->getMockWorker($exce);

        $methods = ['getJobs', 'finishJobs'];
        $command = $this->getMockCommand($methods, new JobBatch([$job]), $this->getJobExecutor($worker));

        $command->expects(self::once())
            ->method('finishJobs')
            ->with(self::callback(function (JobBatch $batch) use ($job): bool {
                $this->assertEquals([$job], $batch->getFails());

                return true;
            }))
        ;

        $this->executeCommand($command);
    }

    public function testExecuteManyJob(): void
    {
        $jobs = [$this->getMockJob(Job::class), $this->getMockJob(Job::class)];

        $worker = $this->getMockWorker(null, 2);

        $methods = ['getJobs', 'finishJobs'];
        $command = $this->getMockCommand($methods, new JobBatch($jobs), $this->getJobExecutor($worker));

        $command->expects(self::once())
            ->method('finishJobs')
            ->with(self::callback(function (JobBatch $batch) use ($jobs): bool {
                $this->assertEquals($jobs, $batch->getOks());

                return true;
            }))
        ;

        $this->executeCommand($command);
    }

    public function testExecuteManyFailingJob(): void
    {
        $jobs = [$this->getMockJob(Job::class), $this->getMockJob(Job::class)];

        $exce = new UnrecoverableJobException();

        $worker = $this->getMockWorker($exce, 2);

        $methods = ['getJobs', 'finishJobs'];
        $command = $this->getMockCommand($methods, new JobBatch($jobs), $this->getJobExecutor($worker));

        $command->expects(self::once())
            ->method('finishJobs')
            ->with(self::callback(function (JobBatch $batch) use ($jobs): bool {
                $this->assertEquals($jobs, $batch->getFails());

                return true;
            }))
        ;

        $this->executeCommand($command);
    }

    public function testExecuteEvents(): void
    {
        $jobs = [$this->getMockJob(Job::class), $this->getMockJob(Job::class)];

        $worker = $this->getMockWorker(null, 2);

        $eventDispatcher = $this->getMockBuilder(EventDispatcher::class)
            ->disableOriginalConstructor()
            ->getMock()
        ;

        $eventDispatcher->expects(self::exactly(6))
            ->method('dispatch')
            ->withConsecutive(
                [new StartJobBatchEvent($jobs), JobExecutor::JOB_BATCH_START_EVENT],
                [new StartJobEvent($jobs[0], false), JobExecutor::JOB_START_EVENT],
                [new FinishedJobEvent($jobs[0], false), JobExecutor::JOB_FINISHED_EVENT],
                [new StartJobEvent($jobs[0], false), JobExecutor::JOB_START_EVENT],
                [new FinishedJobEvent($jobs[0], false), JobExecutor::JOB_FINISHED_EVENT],
                [new FinishedJobBatchEvent($jobs, [], [], []), JobExecutor::JOB_BATCH_FINISHED_EVENT]
            )
        ;

        $methods = ['getJobs', 'finishJobs'];
        $command = $this->getMockCommand($methods, new JobBatch($jobs), $this->getJobExecutor($worker, $eventDispatcher));

        $command->expects(self::once())
            ->method('finishJobs')
            ->with(self::callback(function (JobBatch $batch) use ($jobs): bool {
                $this->assertEquals($jobs, $batch->getOks());

                return true;
            }))
        ;

        $this->executeCommand($command);
    }

    private function getMockJob(string $class): MockObject
    {
        $job = $this->getMockBuilder($class)
            ->getMock()
        ;
        $job->method('getName')
            ->willReturn('worker')
        ;
        $job->method('getArguments')
            ->willReturn([])
        ;

        return $job;
    }

    private function getMockWorker(\Exception $exce = null, int $count = 1): MockObject
    {
        $worker = $this->getMockBuilder(Worker::class)
            ->getMock()
        ;
        $execute = $worker->expects(self::exactly($count))
            ->method('execute')
            ->with([])
        ;
        if ($exce) {
            $execute->willThrowException($exce);
        }

        return $worker;
    }

    private function getMockCommand(array $methods, JobBatch $batch, JobExecutor $executor): MockObject
    {
        $command = $this
            ->getMockBuilder(RunnerCommand::class)
            ->setConstructorArgs(['mcfedr:queue:default-runner', [], $executor])
            ->setMethods($methods)
            ->getMock()
        ;

        $command->expects(self::once())
            ->method('getJobs')
            ->willReturn($batch)
        ;

        return $command;
    }

    private function getJobExecutor($worker, EventDispatcher $eventDispatcher = null): JobExecutor
    {
        return new JobExecutor(new ServiceLocator([
            'worker' => static function () use ($worker) { return $worker; },
        ]), $eventDispatcher);
    }

    private function executeCommand(Command $command): void
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
