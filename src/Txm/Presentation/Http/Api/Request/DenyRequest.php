<?php

declare(strict_types=1);

namespace App\Txm\Presentation\Http\Api\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class DenyRequest
{
    public function __construct(
        #[Assert\NotBlank]
        public string $reason,
    ) {
    }
}
