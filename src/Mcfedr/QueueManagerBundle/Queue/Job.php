<?php

declare(strict_types=1);

namespace Mcfedr\QueueManagerBundle\Queue;

interface Job
{
    /**
     * Get the name of the worker to be executed.
     *
     * @return string
     */
    public function getName();

    /**
     * Gets the arguments that will be passed to the Worker.
     *
     * @return array
     */
    public function getArguments();
}
