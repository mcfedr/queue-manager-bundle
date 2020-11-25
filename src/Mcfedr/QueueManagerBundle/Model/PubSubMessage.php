<?php

declare(strict_types=1);

namespace Mcfedr\QueueManagerBundle\Model;

class PubSubMessage
{
    /**
     * @var array
     */
    private $message;

    public function getMessage(): array
    {
        return $this->message;
    }

    public function setMessage(array $message): self
    {
        $this->message = $message;

        return $this;
    }
}
