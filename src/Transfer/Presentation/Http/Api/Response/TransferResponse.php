<?php

declare(strict_types=1);

namespace App\Transfer\Presentation\Http\Api\Response;

use App\Shared\Money\Money;
use App\Transfer\Domain\Transfer;
use DateTimeInterface;
use Symfony\Component\Serializer\Attribute\Ignore;
use Symfony\Component\Serializer\Attribute\SerializedName;

final readonly class TransferResponse
{
    public function __construct(
        #[Ignore]
        public int $id,
        public string $uuid,
        public string $status,
        public string $sourceAccountUuid,
        public string $destinationAccountUuid,

        #[Ignore]
        public int $amount,

        #[Ignore]
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

    #[SerializedName('amount')]
    public function getFormattedAmount(): string
    {
        return Money::make(
            $this->amount,
            $this->currency,
        )->present();
    }
}
