<?php

declare(strict_types=1);

namespace Mcfedr\QueueManagerBundle\Queue;

abstract class AbstractJob implements Job
{
    private string $name;
    private array $arguments;

    public function __construct(string $name, array $arguments)
    {
        $this->name = $name;
        $this->arguments = $arguments;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getArguments(): array
    {
        return $this->arguments;
    }
}
