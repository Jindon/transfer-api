<?php

declare(strict_types=1);

namespace App\Transfer\Application\Command;

use App\Shared\Money\Money;

final readonly class TransferCommand
{
    public function __construct(
        public string $sourceAccountUuid,
        public string $destinationAccountUuid,
        public Money $amount,
        public string $idempotencyKey,
        public string $requestHash,
    ) {
    }
}
