<?php

namespace App\Transfer\Domain\Repository;

use App\Account\Domain\Account;
use App\Shared\Money\Money;
use App\Transfer\Domain\Transfer;
use DateTimeImmutable;

interface TransferRepositoryInterface
{
    public function findById(int $id): ?Transfer;

    public function createPending(
        Account $sourceAccount,
        Account $destinationAccount,
        Money $amount,
        string $reference,
        DateTimeImmutable $dateTime,
    ): Transfer;

    public function markFailed(int $transferId, ?string $reason = null): void;
}
