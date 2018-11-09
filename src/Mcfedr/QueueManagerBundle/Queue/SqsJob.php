<?php

declare(strict_types=1);

namespace Mcfedr\QueueManagerBundle\Queue;

class SqsJob extends AbstractRetryableJob
{
    /**
     * @var ?string
     */
    private $id;

    /**
     * @var ?int
     */
    private $delay;

    /**
     * @var ?string
     */
    private $url;

    /**
     * @var ?string
     */
    private $receiptHandle;

    /**
     * @var ?int
     */
    private $visibilityTimeout;

    public function __construct(string $name, array $arguments, ?int $delay, string $url, ?string $id = null, int $retryCount = 0, ?string $receiptHandle = null, ?int $visibilityTimeout = null)
    {
        parent::__construct($name, $arguments, $retryCount);
        $this->id = $id;
        $this->delay = $delay;
        $this->url = $url;
        $this->receiptHandle = $receiptHandle;
        $this->visibilityTimeout = $visibilityTimeout;
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function setId(?string $id)
    {
        $this->id = $id;

        return $this;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function getDelay(): ?int
    {
        return $this->delay;
    }

    public function getReceiptHandle(): ?string
    {
        return $this->receiptHandle;
    }

    public function getVisibilityTimeout(): ?int
    {
        return $this->visibilityTimeout;
    }

    public function getMessageBody(): string
    {
        return json_encode([
            'name' => $this->getName(),
            'arguments' => $this->getArguments(),
            'retryCount' => $this->getRetryCount(),
            'visibilityTimeout' => $this->getVisibilityTimeout(),
        ]);
    }
}
