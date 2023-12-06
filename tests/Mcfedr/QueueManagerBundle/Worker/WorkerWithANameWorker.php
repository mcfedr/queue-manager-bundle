<?php

declare(strict_types=1);

namespace Mcfedr\QueueManagerBundle\Worker;

use Mcfedr\QueueManagerBundle\Queue\Worker;

class WorkerWithANameWorker implements Worker
{
    public static function getName(): string
    {
        return 'worker_with_a_name';
    }

    public function execute(array $arguments): void {}
}
