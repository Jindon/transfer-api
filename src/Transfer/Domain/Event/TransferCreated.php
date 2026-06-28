<?php

declare(strict_types=1);

namespace App\Transfer\Domain\Event;

final readonly class TransferCreated
{
    public function __construct(public int $transferId)
    {
    }
}
