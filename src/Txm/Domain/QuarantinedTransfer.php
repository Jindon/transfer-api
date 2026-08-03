<?php

declare(strict_types=1);

namespace App\Txm\Domain;

use App\Transfer\Domain\Transfer;
use App\Txm\Domain\Enum\QuarantineStatus;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'quarantined_transfers')]
class QuarantinedTransfer
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id;

    #[ORM\OneToOne(targetEntity: Transfer::class)]
    #[ORM\JoinColumn(name: 'transfer_id', referencedColumnName: 'id', nullable: false)]
    private Transfer $transfer;

    #[ORM\Column(enumType: QuarantineStatus::class)]
    private QuarantineStatus $status;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $reason = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $quarantined_at;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $reviewed_at = null;

    public function __construct(Transfer $transfer)
    {
        $this->transfer = $transfer;
        $this->status = QuarantineStatus::PENDING_REVIEW;
        $this->quarantined_at = new DateTimeImmutable();
    }

    public function approve(DateTimeImmutable $now): void
    {
        $this->status = QuarantineStatus::APPROVED;
        $this->reviewed_at = $now;
    }

    public function deny(?string $reason, DateTimeImmutable $now): void
    {
        $this->status = QuarantineStatus::DENIED;
        $this->reason = $reason;
        $this->reviewed_at = $now;
    }

    public function isPendingReview(): bool
    {
        return QuarantineStatus::PENDING_REVIEW === $this->status;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTransfer(): Transfer
    {
        return $this->transfer;
    }

    public function getStatus(): QuarantineStatus
    {
        return $this->status;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function getQuarantinedAt(): DateTimeImmutable
    {
        return $this->quarantined_at;
    }

    public function getReviewedAt(): ?DateTimeImmutable
    {
        return $this->reviewed_at;
    }
}
