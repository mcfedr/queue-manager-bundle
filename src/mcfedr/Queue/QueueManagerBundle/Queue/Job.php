<?php
/**
 * Created by mcfedr on 21/03/2014 11:02
 */

namespace mcfedr\Queue\QueueManagerBundle\Queue;

interface Job
{
    /**
     * Get the data for this job
     *
     * @return string
     */
    public function getData();
}
