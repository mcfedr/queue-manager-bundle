<?php

declare(strict_types=1);

namespace Mcfedr\QueueManagerBundle\Exception;

class NoSuchQueueException extends JobNotDeletableException
{
    public function __construct()
    {
        parent::__construct('No such queue exists.');
    }
}
