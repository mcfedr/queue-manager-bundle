<?php


namespace Mcfedr\QueueManagerBundle\Worker;

use Mcfedr\QueueManagerBundle\Queue\Worker;

class OomWorker implements Worker
{
    public function execute(array $arguments): void
    {
        $x = '';
        while (true) {
            $x .= str_repeat('x', 1024 * 10);
        }
    }
}
