<?php

namespace App\Transfer\Domain;

use App\Account\Domain\Account;
use App\Transfer\Domain\Enum\TransferStatus;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'transfers')]
class Transfer
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id;

    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $uuid;

    #[ORM\ManyToOne(targetEntity: Account::class)]
    #[ORM\JoinColumn(name: 'source_account_id', referencedColumnName: 'id', nullable: false)]
    private Account $sourceAccount;

    #[ORM\ManyToOne(targetEntity: Account::class)]
    #[ORM\JoinColumn(name: 'destination_account_id', referencedColumnName: 'id', nullable: false)]
    private Account $destinationAccount;

    #[ORM\Column(length: 3)]
    private ?string $currency = null;

    #[ORM\Column(type: Types::INTEGER, options: ['unsigned' => true, 'default' => 0])]
    private int $amount = 0;

    #[ORM\Column(enumType: TransferStatus::class)]
    private TransferStatus $status;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $reference;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $failureReason;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $created_at = null;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $completed_at = null;

    public function __construct()
    {
        $this->uuid = Uuid::v7();
    }

    public function complete(DateTimeImmutable $now): void
    {
        $this->status = TransferStatus::COMPLETED;
        $this->completed_at = $now;
    }

    public function fail(string $reason): void
    {
        $this->status = TransferStatus::FAILED;
        $this->failureReason = $reason;
    }

    public function isPending(): bool
    {
        return TransferStatus::PENDING === $this->status;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUuid(): Uuid
    {
        return $this->uuid;
    }

    public function getSourceAccount(): Account
    {
        return $this->sourceAccount;
    }

    public function setSourceAccount(Account $sourceAccount): void
    {
        $this->sourceAccount = $sourceAccount;
    }

    public function getDestinationAccount(): Account
    {
        return $this->destinationAccount;
    }

    public function setDestinationAccount(Account $destinationAccount): void
    {
        $this->destinationAccount = $destinationAccount;
    }

    public function getCurrency(): ?string
    {
        return $this->currency;
    }

    public function setCurrency(string $currency): static
    {
        $this->currency = $currency;

        return $this;
    }

    public function getAmount(): int
    {
        return $this->amount;
    }

    public function setAmount(int $amount): static
    {
        $this->amount = $amount;

        return $this;
    }

    public function getStatus(): TransferStatus
    {
        return $this->status;
    }

    public function setStatus(TransferStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function setReference(string $reference): static
    {
        $this->reference = $reference;

        return $this;
    }

    public function getFailureReason(): ?string
    {
        return $this->failureReason;
    }

    public function setFailureReason(?string $failureReason): void
    {
        $this->failureReason = $failureReason;
    }

    public function getCreatedAt(): ?DateTimeImmutable
    {
        return $this->created_at;
    }

    public function setCreatedAt(?DateTimeImmutable $created_at): static
    {
        $this->created_at = $created_at;

        return $this;
    }

    public function getCompletedAt(): ?DateTimeImmutable
    {
        return $this->completed_at;
    }

    public function setCompletedAt(?DateTimeImmutable $completed_at): static
    {
        $this->completed_at = $completed_at;

        return $this;
    }
}
