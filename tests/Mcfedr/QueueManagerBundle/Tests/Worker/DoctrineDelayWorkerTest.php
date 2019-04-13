<?php

declare(strict_types=1);

namespace Mcfedr\QueueManagerBundle\Tests\Worker;

use Carbon\Carbon;
use Mcfedr\QueueManagerBundle\Entity\DoctrineDelayJob;
use Mcfedr\QueueManagerBundle\Exception\UnrecoverableJobException;
use Mcfedr\QueueManagerBundle\Manager\QueueManagerRegistry;
use Mcfedr\QueueManagerBundle\Worker\DoctrineDelayWorker;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class DoctrineDelayWorkerTest extends TestCase
{
    /**
     * @var MockObject|QueueManagerRegistry
     */
    private $queueManagerRegistry;

    /**
     * @var DoctrineDelayWorker
     */
    private $doctrineDelayWorker;

    protected function setUp(): void
    {
        $this->queueManagerRegistry = $this->getMockBuilder(QueueManagerRegistry::class)->disableOriginalConstructor()->getMock();
        $this->doctrineDelayWorker = new DoctrineDelayWorker($this->queueManagerRegistry);
    }

    public function testExecuteNoArgs(): void
    {
        $this->expectException(UnrecoverableJobException::class);
        $this->doctrineDelayWorker->execute([]);
    }

    public function testExecute(): void
    {
        $this->queueManagerRegistry->expects($this->once())->method('put')->with('name', ['args'], ['options'], 'manager');

        $this->doctrineDelayWorker->execute(['job' => new DoctrineDelayJob('name', ['args'], ['options'], 'manager', new Carbon())]);
    }
}
