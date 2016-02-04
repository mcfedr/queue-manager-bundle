<?php
/**
 * Created by mcfedr on 03/02/2016 21:53
 */

namespace Mcfedr\QueueManagerBundle\Queue;

interface Worker
{
    /**
     * Called to start the queued task
     *
     * @param array $arguments
     * @throws \Exception
     */
    public function execute(array $arguments);
}
