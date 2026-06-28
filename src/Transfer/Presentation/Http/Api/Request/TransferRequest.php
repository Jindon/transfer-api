<?php

declare(strict_types=1);

namespace App\Transfer\Presentation\Http\Api\Request;

use App\Shared\Money\Currency;
use Symfony\Component\Validator\Constraints as Assert;

class TransferRequest
{
    public function __construct(
        #[Assert\NotBlank, Assert\Uuid]
        public string $sourceAccountUuid,

        #[Assert\NotBlank, Assert\Uuid]
        #[Assert\NotEqualTo(propertyPath: 'sourceAccountUuid', message: 'Source and destination must be different.')]
        public string $destinationAccountUuid,

        #[Assert\NotNull, Assert\Positive, Assert\Type('integer')]
        public int $amount,

        #[Assert\NotNull, Assert\Choice(choices: Currency::SUPPORTED_CURRENCIES, message: 'Currency "{{ value }}" is not supported.')]
        public string $currency,

        #[Assert\Length(max: 255)]
        public ?string $reference = null,
    ) {
    }
}
