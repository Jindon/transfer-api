<?php

declare(strict_types=1);

namespace App\Txm\Application\Command;

readonly class ReviewQuarantineCommand
{
    public function __construct(
        public string $transferUuid,
        public bool $approve,
        public ?string $reason = null,
    ) {
    }
}
