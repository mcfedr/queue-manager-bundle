<?php
/**
 * Created by mcfedr on 17/03/2016 21:39
 */


namespace Mcfedr\QueueManagerBundle\Tests\Command;

use Mcfedr\QueueManagerBundle\Command\RunnerCommand;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class RunnerCommandTest extends \PHPUnit_Framework_TestCase
{
    public function testExecuteJob()
    {
        $command = $this
            ->getMockBuilder(RunnerCommand::class)
            ->setConstructorArgs(['mcfedr:queue:default-runner', []])
            ->setMethods(['getJob', 'executeJob'])
            ->getMock();
        $command->expects($this->once())
            ->method('getJob')
            ->willReturn('the job');

        $command->expects($this->once())
            ->method('executeJob')
            ->with('the job');

        $application = new Application();
        $application->add($command);

        $command = $application->find('mcfedr:queue:default-runner');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            '--once' => true,
            '--singleProcess' => true
        ]);
    }
}
