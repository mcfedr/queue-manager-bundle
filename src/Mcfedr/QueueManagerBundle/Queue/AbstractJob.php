<?php
/**
 * Created by mcfedr on 04/02/2016 00:39
 */

namespace Mcfedr\QueueManagerBundle\Queue;

abstract class AbstractJob implements Job
{
    /**
     * @var string
     */
    private $name;

    /**
     * @var array
     */
    private $arguments;

    /**
     * @param string $name
     * @param array $arguments
     */
    public function __construct($name, array $arguments)
    {
        $this->name = $name;
        $this->arguments = $arguments;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return array
     */
    public function getArguments()
    {
        return $this->arguments;
    }
}
