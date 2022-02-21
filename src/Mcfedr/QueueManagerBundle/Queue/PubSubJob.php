<?php

declare(strict_types=1);

namespace Mcfedr\QueueManagerBundle\Queue;

class PubSubJob extends AbstractRetryableJob
{
    private ?string $id;
    private ?string $ackId;

    public function __construct(string $name, array $arguments, $id = null, int $retryCount = 0, $ackId = null)
    {
        parent::__construct($name, $arguments, $retryCount);
        $this->id = (string) $id;
        $this->ackId = (string) $ackId;
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function setId(?string $id): static
    {
        $this->id = $id;

        return $this;
    }

    public function getAckId(): ?string
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
