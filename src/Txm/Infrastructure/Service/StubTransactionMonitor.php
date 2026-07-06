<?php

declare(strict_types=1);

namespace App\Txm\Infrastructure\Service;

use App\Transfer\Domain\Transfer;
use App\Txm\Domain\Service\TransactionMonitorInterface;

class StubTransactionMonitor implements TransactionMonitorInterface
{
    public function check(Transfer $transfer): bool
    {
        return true;
    }
}
