<?php

declare(strict_types=1);

namespace App\Txm\Domain\Exception;

use RuntimeException;

class TransferNotQuarantinedException extends RuntimeException
{
    public function __construct(string $uuid)
    {
        parent::__construct("Transfer {$uuid} is not under quarantine review");
    }
}
