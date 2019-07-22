<?php

declare(strict_types=1);

namespace Mcfedr\QueueManagerBundle\Worker;

use Mcfedr\QueueManagerBundle\Queue\Worker;

class OomWorker implements Worker
{
    static $a = 0;

    public function execute(array $arguments): void
    {
        self::$a++;
        if (isset($arguments['nth']) && self::$a < $arguments['nth']) {
            return;
        }

        $x = '';
        while (true) {
            $x .= str_repeat('x', 1024 * 10);
        }
    }
}
