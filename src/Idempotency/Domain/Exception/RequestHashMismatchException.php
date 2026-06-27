<?php

declare(strict_types=1);

namespace App\Idempotency\Domain\Exception;

use RuntimeException;

class RequestHashMismatchException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Idempotency-Key reused with a different request body');
    }
}
