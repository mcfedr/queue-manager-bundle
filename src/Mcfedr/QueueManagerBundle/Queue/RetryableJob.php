<?php
/**
 * Created by mcfedr on 09/06/2016 14:34
 */

namespace Mcfedr\QueueManagerBundle\Queue;

interface RetryableJob extends Job
{
    /**
     * Used to count retries
     *
     * @return int
     */
    public function getRetryCount();
}
