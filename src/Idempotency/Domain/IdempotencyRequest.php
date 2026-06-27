<?php

declare(strict_types=1);

namespace App\Idempotency\Domain;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity()]
#[ORM\Table(name: 'idempotency_requests')]
class IdempotencyRequest
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id;

    #[ORM\Column(name: 'idempotency_key', length: 255, unique: true)]
    private string $key;

    #[ORM\Column(length: 64)]
    private string $requestHash;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $sourceType = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $sourceId = null;

    #[ORM\Column]
    private DateTimeImmutable $createdAt;

    public function __construct(string $key, string $requestHash)
    {
        $this->key = $key;
        $this->requestHash = $requestHash;
        $this->createdAt = new DateTimeImmutable();
    }

    public function attach(string $sourceType, int $sourceId): void
    {
        $this->sourceType = $sourceType;
        $this->sourceId = $sourceId;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): void
    {
        $this->id = $id;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function setKey(string $key): void
    {
        $this->key = $key;
    }

    public function getRequestHash(): string
    {
        return $this->requestHash;
    }

    public function setRequestHash(string $requestHash): void
    {
        $this->requestHash = $requestHash;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTimeImmutable $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getSourceType(): ?string
    {
        return $this->sourceType;
    }

    public function setSourceType(?string $sourceType): void
    {
        $this->sourceType = $sourceType;
    }

    public function getSourceId(): ?int
    {
        return $this->sourceId;
    }

    public function setSourceId(?int $sourceId): void
    {
        $this->sourceId = $sourceId;
    }
}
