<?php

declare(strict_types=1);

namespace App\Txm\Domain\Enum;

enum QuarantineStatus: string
{
    case PENDING_REVIEW = 'pending_review';
    case APPROVED = 'approved';
    case DENIED = 'denied';
}
