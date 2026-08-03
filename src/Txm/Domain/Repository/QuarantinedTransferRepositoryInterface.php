<?php

declare(strict_types=1);

namespace App\Txm\Domain\Repository;

use App\Transfer\Domain\Transfer;
use App\Txm\Domain\QuarantinedTransfer;

interface QuarantinedTransferRepositoryInterface
{
    public function quarantine(Transfer $transfer): QuarantinedTransfer;

    public function findByTransfer(Transfer $transfer): ?QuarantinedTransfer;

    public function save(QuarantinedTransfer $quarantinedTransfer): void;
}
