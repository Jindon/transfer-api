<?php

declare(strict_types=1);

namespace App\Idempotency\Domain\Exception;

use RuntimeException;

class MissingIdempotencyKeyException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Idempotency-Key header is required');
    }
}
