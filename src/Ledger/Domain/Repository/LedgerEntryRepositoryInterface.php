<?php

declare(strict_types=1);

namespace App\Ledger\Domain\Repository;

use App\Account\Domain\Account;
use App\Shared\Money\Money;
use App\Transfer\Domain\Transfer;

interface LedgerEntryRepositoryInterface
{
    public function recordDoubleEntry(Transfer $transfer, Account $source, Account $destination, Money $amount): void;
}
