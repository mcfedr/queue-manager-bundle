<?php

declare(strict_types=1);

namespace Mcfedr\QueueManagerBundle\Queue;

use Mcfedr\QueueManagerBundle\Exception\UnrecoverableJobException;
use Psr\Log\LoggerInterface;

class TestWorker implements Worker
{
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Called to start the queued task.
     *
     * @throws \Exception
     * @throws UnrecoverableJobException
     */
    public function execute(array $arguments): void
    {
        $this->logger->info('Test worker is executing', [
            'arguments' => $arguments,
        ]);
        sleep(1);

        switch (random_int(1, 3)) {
            case 1:
                throw new UnrecoverableJobException('job is going to fail forever');

            case 2:
                throw new \Exception('job is failing for unknown reasons');
        }
    }
}
