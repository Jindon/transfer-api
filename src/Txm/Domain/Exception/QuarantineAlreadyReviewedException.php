<?php

declare(strict_types=1);

namespace App\Txm\Domain\Exception;

use RuntimeException;

class QuarantineAlreadyReviewedException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('This quarantine has already been reviewed');
    }
}
