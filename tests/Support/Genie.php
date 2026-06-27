<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Account\Domain\Account;
use App\Shared\Money\Currency;
use App\Transfer\Domain\Enum\TransferStatus;
use App\Transfer\Domain\Transfer;
use DateTimeImmutable;

class Genie
{
    public static function makeAccount(
        int $balance = 10_000_00,
    ): Account {
        return new Account(Currency::EUR, $balance);
    }

    public static function makeTransfer(
        ?Account $sourceAccount = null,
        ?Account $destinationAccount = null,
        int $amount = 100_00,
        TransferStatus $status = TransferStatus::PENDING,
    ): Transfer {
        $transfer = new Transfer();
        $transfer->setAmount($amount);
        $transfer->setCurrency(Currency::EUR);
        $transfer->setSourceAccount($sourceAccount ?? self::makeAccount());
        $transfer->setDestinationAccount($destinationAccount ?? self::makeAccount());
        $transfer->setCreatedAt(new DateTimeImmutable());
        $transfer->setStatus($status);

        $completedAt = match ($status) {
            TransferStatus::COMPLETED => new DateTimeImmutable(),
            default => null,
        };

        $transfer->setCompletedAt($completedAt);

        return $transfer;
    }
}
