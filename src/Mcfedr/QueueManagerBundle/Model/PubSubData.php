<?php

declare(strict_types=1);

namespace Mcfedr\QueueManagerBundle\Model;

class PubSubData
{
    /**
     * @var string
     */
    private $data;

    public function getData(): string
    {
        return base64_decode($this->data, true);
    }

    public function setData(string $data): void
    {
        $this->data = $data;
    }
}
