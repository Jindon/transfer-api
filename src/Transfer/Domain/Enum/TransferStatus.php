<?php

declare(strict_types=1);

namespace App\Transfer\Domain\Enum;

enum TransferStatus: string
{
    case PENDING = 'pending';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
}
