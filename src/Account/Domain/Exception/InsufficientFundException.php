<?php

declare(strict_types=1);

namespace App\Account\Domain\Exception;

use RuntimeException;

class InsufficientFundException extends RuntimeException
{
    public function __construct(string $accountUuid, int $requested, int $balance)
    {
        parent::__construct(sprintf(
            'Account with UUID "%s" has insufficient funds. Requested: %d, balance: %d.',
            $accountUuid,
            $requested,
            $balance,
        ));
    }
}
