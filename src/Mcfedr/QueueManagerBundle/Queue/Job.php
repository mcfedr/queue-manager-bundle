<?php

declare(strict_types=1);

namespace Mcfedr\QueueManagerBundle\Queue;

interface Job
{
    /**
     * Get the name of the worker to be executed.
     */
    public function getName(): string;

    /**
     * Gets the arguments that will be passed to the Worker.
     */
    public function getArguments(): array;
}
