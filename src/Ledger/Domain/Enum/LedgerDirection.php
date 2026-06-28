<?php

declare(strict_types=1);

namespace App\Ledger\Domain\Enum;

enum LedgerDirection: string
{
    case DEBIT = 'debit';
    case CREDIT = 'credit';
}
