<?php

declare(strict_types=1);

namespace Mcfedr\QueueManagerBundle\Queue;

class PubSubJob extends AbstractRetryableJob
{
    /**
     * @var ?string
     */
    private $id;

    /**
     * @var null|string
     */
    private $ackId;

    public function __construct(string $name, array $arguments, $id = null, int $retryCount = 0, $ackId = null)
    {
        parent::__construct($name, $arguments, $retryCount);
        $this->id = $id;
        $this->ackId = $ackId;
    }

    public function getId()
    {
        return $this->id;
    }

    public function setId(?string $id)
    {
        $this->id = $id;

        return $this;
    }

    public function getAckId()
    {
        return $this->ackId;
    }

    public function getMessageBody(): string
    {
        return json_encode([
            'name' => $this->getName(),
            'arguments' => $this->getArguments(),
            'retryCount' => $this->getRetryCount(),
        ]);
    }
}
