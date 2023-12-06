<?php

declare(strict_types=1);

namespace Mcfedr\QueueManagerBundle\Entity;

use Carbon\Carbon;
use Doctrine\ORM\Mapping as ORM;
use Mcfedr\QueueManagerBundle\Queue\RetryableJob;

#[ORM\Entity]
#[ORM\Table(name: 'DoctrineDelayJob')]
#[ORM\Index(columns: ['time'])]
#[ORM\Index(columns: ['processing'])]
class DoctrineDelayJob implements RetryableJob
{
    #[ORM\Column(name: 'id', type: 'integer')]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private string $name;

    #[ORM\Column(type: 'json')]
    private array $arguments;

    #[ORM\Column(type: 'json')]
    private array $options;
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $manager;

    #[ORM\Column(name: 'time', type: 'datetime')]
    private \DateTime $time;
    #[ORM\Column(type: 'datetime')]
    private \DateTime $createdAt;
    #[ORM\Column(name: 'processing', type: 'boolean')]
    private bool $processing = false;

    #[ORM\Column(type: 'integer')]
    private int $retryCount;

    public function __construct(string $name, array $arguments, array $options, ?string $manager, \DateTime $time, int $retryCount = 0)
    {
        $this->name = $name;
        $this->arguments = $arguments;
        $this->options = $options;
        $this->manager = $manager;
        $this->time = $time;
        $this->createdAt = new Carbon();
        $this->retryCount = $retryCount;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getArguments(): array
    {
        return $this->arguments;
    }

    public function getOptions(): array
    {
        return $this->options;
    }

    public function getManager(): ?string
    {
        return $this->manager;
    }

    public function getTime(): \DateTime
    {
        return $this->time;
    }

    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    public function getRetryCount(): int
    {
        return $this->retryCount;
    }
}
