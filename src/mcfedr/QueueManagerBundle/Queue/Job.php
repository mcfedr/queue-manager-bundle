<?php
/**
 * Created by mcfedr on 21/03/2014 11:02
 */

namespace Mcfedr\QueueManagerBundle\Queue;

interface Job
{
    /**
     * Get the name of the worker to be executed
     *
     * @return string
     */
    public function getName();

    /**
     * Gets the arguments that will be passed to the Worker
     *
     * @return array
     */
    public function getArguments();

    /**
     * Gets the options you passed to setup this Job
     *
     * @return array
     */
    public function getOptions();
}
