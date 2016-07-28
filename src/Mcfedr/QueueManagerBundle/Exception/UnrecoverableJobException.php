<?php
/**
 * Created by mcfedr on 09/06/2016 14:28
 */

namespace Mcfedr\QueueManagerBundle\Exception;

/**
 * Class UnrecoverableJobException
 * @package Mcfedr\QueueManagerBundle\Exception
 *
 * Indicates that a job should not be rescheduled
 */
class UnrecoverableJobException extends \Exception
{
}
