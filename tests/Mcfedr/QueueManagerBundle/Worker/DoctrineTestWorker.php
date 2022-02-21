<?php

declare(strict_types=1);

namespace Mcfedr\QueueManagerBundle\Worker;

use Mcfedr\QueueManagerBundle\Exception\UnrecoverableJobException;
use Mcfedr\QueueManagerBundle\Queue\Worker;
use Psr\Log\LoggerInterface;

class DoctrineTestWorker implements Worker
{
    private LoggerInterface $logger;
    private array $count = [];

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function execute(array $options): void
    {
        if (!isset($options['job'])) {
            throw new UnrecoverableJobException('Missing job argument');
        }

        $job = $options['job'];

        if (isset($this->count[$job])) {
            ++$this->count[$job];
            $this->logger->warning('counted!', ['options' => $options]);
        } else {
            $this->count[$job] = 1;
            $this->logger->info('once', ['options' => $options]);
        }
    }
}
