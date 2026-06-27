<?php

declare(strict_types=1);

namespace App\Account\Domain\Exception;

use RuntimeException;

class AccountNotFoundException extends RuntimeException
{
    public function __construct(?string $accountUuid = null)
    {
        $message = $accountUuid
            ? sprintf('Account with UUID "%s" not found.', $accountUuid)
            : 'Account with not found';

        parent::__construct($message);
    }
}
