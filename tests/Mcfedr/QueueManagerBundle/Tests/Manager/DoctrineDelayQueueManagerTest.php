<?php

declare(strict_types=1);

namespace Mcfedr\QueueManagerBundle\Tests\Manager;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use Mcfedr\QueueManagerBundle\Entity\DoctrineDelayJob;
use Mcfedr\QueueManagerBundle\Manager\DoctrineDelayQueueManager;
use Mcfedr\QueueManagerBundle\Manager\QueueManagerRegistry;
use Mcfedr\QueueManagerBundle\Queue\Job;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 * @coversNothing
 */
final class DoctrineDelayQueueManagerTest extends TestCase
{
    /**
     * @var DoctrineDelayQueueManager
     */
    private $manager;

    /**
     * @var EntityRepository|MockObject
     */
    private $repo;

    /**
     * @var EntityManager|MockObject
     */
    private $entityManager;

    /**
     * @var MockObject|QueueManagerRegistry
     */
    private $queueManagerRegistry;

    protected function setUp(): void
    {
        $this->repo = $this->getMockBuilder(EntityRepository::class)->disableOriginalConstructor()->getMock();

        $this->entityManager = $this->getMockBuilder(EntityManager::class)->disableOriginalConstructor()->getMock();

        $this->entityManager->method('getRepository')
            ->with(DoctrineDelayJob::class)
            ->willReturn($this->repo)
        ;

        $doctrine = $this->getMockBuilder(Registry::class)->disableOriginalConstructor()->getMock();
        $doctrine->method('getManager')
            ->with(null)
            ->willReturn($this->entityManager)
        ;

        $this->queueManagerRegistry = $this->getMockBuilder(QueueManagerRegistry::class)
            ->disableOriginalConstructor()
            ->getMock()
        ;

        $this->manager = new DoctrineDelayQueueManager($this->queueManagerRegistry, $doctrine, [
            'entity_manager' => null,
            'default_manager' => 'default',
            'default_manager_options' => [
                'manager_option_a' => 'a',
            ],
        ]);
    }

    public function testPutWithSignificantDelay(): void
    {
        $this->entityManager
            ->expects($this->once())
            ->method('persist')
        ;

        $this->entityManager
            ->expects($this->once())
            ->method('flush')
        ;

        $job = $this->manager->put('test_worker', [
            'argument_a' => 'a',
        ], ['time' => new \DateTime('+1 minute')]);

        $this->assertSame('test_worker', $job->getName());
        $this->assertSame([
            'argument_a' => 'a',
        ], $job->getArguments());
    }

    /**
     * @dataProvider getNotSignificantDelayAndTimeInPastJobTimes
     */
    public function testPutWithNotSignificantDelayAndTimeInPast(\DateTime $jobTime): void
    {
        $job = $this->getMockBuilder(Job::class)->getMock();

        $this->queueManagerRegistry
            ->expects($this->once())
            ->method('put')
            ->with('test_worker', [
                'argument_a' => 'a',
            ], [
                'manager_option_a' => 'a',
            ], 'default')
            ->willReturn($job)
        ;

        $putJob = $this->manager->put('test_worker', [
            'argument_a' => 'a',
        ], ['time' => $jobTime]);

        $this->assertSame($job, $putJob);
    }

    public function getNotSignificantDelayAndTimeInPastJobTimes()
    {
        return [
            [new \DateTime('+12 seconds')],
            [new \DateTime('-12 seconds')],
        ];
    }

    public function testPutFast(): void
    {
        $job = $this->getMockBuilder(Job::class)->getMock();

        $this->queueManagerRegistry
            ->expects($this->once())
            ->method('put')
            ->with('test_worker', [
                'argument_a' => 'a',
            ], [
                'manager_option_a' => 'a',
            ], 'default')
            ->willReturn($job)
        ;

        $putJob = $this->manager->put('test_worker', [
            'argument_a' => 'a',
        ]);

        $this->assertSame($job, $putJob);
    }

    public function testDelete(): void
    {
        $toDelete = $this->getMockBuilder(DoctrineDelayJob::class)->setConstructorArgs(['test_worker', [], [], 'default', new \DateTime()])->getMock();
        $toDelete->method('getId')->willReturn(1);

        $this->entityManager
            ->expects($this->once())
            ->method('contains')
            ->with($toDelete)
            ->willReturn(false)
        ;

        $reference = 1;

        $this->entityManager
            ->expects($this->once())
            ->method('getReference')
            ->willReturn($reference)
        ;

        $this->entityManager
            ->expects($this->once())
            ->method('remove')
            ->with($reference)
        ;

        $this->entityManager
            ->expects($this->once())
            ->method('flush')
            ->with($reference)
        ;

        $this->manager->delete($toDelete);
    }

    public function testDeleteFromEM(): void
    {
        $toDelete = new DoctrineDelayJob('test_worker', [], [], 'default', new \DateTime());

        $this->entityManager
            ->expects($this->once())
            ->method('contains')
            ->with($toDelete)
            ->willReturn(true)
        ;

        $this->entityManager
            ->expects($this->once())
            ->method('remove')
            ->with($toDelete)
        ;

        $this->entityManager
            ->expects($this->once())
            ->method('flush')
            ->with($toDelete)
        ;

        $this->manager->delete($toDelete);
    }

    /**
     * @expectedException \Mcfedr\QueueManagerBundle\Exception\WrongJobException
     */
    public function testDeleteOther(): void
    {
        $this->manager->delete($this->getMockBuilder(Job::class)->getMock());
    }

    /**
     * @expectedException \Mcfedr\QueueManagerBundle\Exception\NoSuchJobException
     */
    public function testNonPersisted(): void
    {
        $toDelete = new DoctrineDelayJob('test_worker', [], [], 'default', new \DateTime());

        $this->manager->delete($toDelete);
    }
}
