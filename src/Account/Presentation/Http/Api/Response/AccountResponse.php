<?php

declare(strict_types=1);

namespace App\Account\Presentation\Http\Api\Response;

use App\Account\Domain\Account;
use App\Shared\Money\Money;
use DateTimeInterface;
use Symfony\Component\Serializer\Attribute\Ignore;
use Symfony\Component\Serializer\Attribute\SerializedName;

final readonly class AccountResponse
{
    public function __construct(
        #[Ignore]
        public int $balance,
        public string $uuid,
        public string $currency,
        public ?string $createdAt,
        public ?string $updatedAt,
    ) {
    }

    public static function fromEntity(Account $account): self
    {
        return new self(
            balance: $account->getBalance(),
            uuid: (string) $account->getUuid(),
            currency: $account->getCurrency(),
            createdAt: $account->getCreatedAt()?->format(DateTimeInterface::ATOM),
            updatedAt: $account->getUpdatedAt()?->format(DateTimeInterface::ATOM),
        );
    }

    #[SerializedName('balance')]
    public function getFormattedBalance(): string
    {
        return Money::make($this->balance, $this->currency)->present();
    }
}
