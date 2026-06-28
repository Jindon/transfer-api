<?php

declare(strict_types=1);

namespace App\Transfer\Domain\Exception;

use RuntimeException;

class TransferNotFoundException extends RuntimeException
{
    public function __construct(string $uuid)
    {
        parent::__construct(sprintf('Transfer with UUID "%s" not found.', $uuid));
    }
}
