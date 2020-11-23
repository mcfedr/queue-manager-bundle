<?php

declare(strict_types=1);

namespace Mcfedr\QueueManagerBundle\Model;

class PubSubMessage
{
    /**
     * @var string
     */
    private $message;

    public function getMessage(): string
    {
        return $this->message;
    }

    public function setMessage(string $message): void
    {
        $this->message = $message;
    }
}
