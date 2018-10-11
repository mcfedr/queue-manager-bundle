<?php

declare(strict_types=1);

namespace Mcfedr\QueueManagerBundle\Exception;

class InvalidWorkerException extends QueueManagerException implements UnrecoverableJobExceptionInterface
{
}
