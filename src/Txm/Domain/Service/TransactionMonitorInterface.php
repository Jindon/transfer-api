<?php

declare(strict_types=1);

namespace App\Txm\Domain\Service;

use App\Transfer\Domain\Transfer;

interface TransactionMonitorInterface
{
    public function check(Transfer $transfer): bool;
}
