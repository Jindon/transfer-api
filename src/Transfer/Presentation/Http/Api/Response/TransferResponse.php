<?php

declare(strict_types=1);

namespace App\Transfer\Presentation\Http\Api\Response;

use App\Shared\Money\Money;
use App\Transfer\Domain\Transfer;
use DateTimeInterface;

class TransferResponse
{
    public function __construct(
        public int $id,
        public string $uuid,
        public string $status,
        public string $sourceAccountUuid,
        public string $destinationAccountUuid,
        public int $amount,
        public string $currency,
        public ?string $reference,
        public string $createdAt,
        public ?string $completedAt,
    ) {
    }

    public static function fromEntity(Transfer $transfer): self
    {
        return new self(
            id: $transfer->getId(),
            uuid: (string) $transfer->getUuid(),
            status: $transfer->getStatus()->value,
            sourceAccountUuid: (string) $transfer->getSourceAccount()->getUuid(),
            destinationAccountUuid: (string) $transfer->getDestinationAccount()->getUuid(),
            amount: $transfer->getAmount(),
            currency: $transfer->getCurrency(),
            reference: $transfer->getReference(),
            createdAt: $transfer->getCreatedAt()->format(DateTimeInterface::ATOM),
            completedAt: $transfer->getCompletedAt()->format(DateTimeInterface::ATOM),
        );
    }

    public function toArray(): array
    {
        return [
            'uuid' => $this->uuid,
            'status' => $this->status,
            'sourceAccountUuid' => $this->sourceAccountUuid,
            'destinationAccountUuid' => $this->destinationAccountUuid,
            'amount' => Money::make($this->amount, $this->currency)->present(),
            'reference' => $this->reference,
            'createdAt' => $this->createdAt,
            'completedAt' => $this->completedAt,
        ];
    }
}
