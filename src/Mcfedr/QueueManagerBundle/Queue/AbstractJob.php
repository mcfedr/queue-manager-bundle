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
     * @var array
     */
    private $options;

    /**
     * @param string $name
     * @param array $arguments
     * @param array $options
     */
    public function __construct($name, array $arguments, array $options)
    {
        $this->name = $name;
        $this->arguments = $arguments;
        $this->options = $options;
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

    /**
     * @return array
     */
    public function getOptions()
    {
        return $this->options;
    }
}
