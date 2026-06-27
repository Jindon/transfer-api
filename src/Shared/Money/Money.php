<?php

declare(strict_types=1);

namespace App\Shared\Money;

final readonly class Money
{
    public function __construct(
        private int $amount,
        private string $currencyCode,
    ) {
    }

    public function getAmount(): int
    {
        return $this->amount;
    }

    public function getCurrencyCode(): string
    {
        return $this->currencyCode;
    }

    public static function make(int $amount, string $currencyCode): self
    {
        return new self($amount, $currencyCode);
    }

    /**
     * In practise Money would be far more complex
     * Currency would hold a lot more info other than the currency_code and would require an Entity of it's own
     * The formatting below is very basic and will not be correct for different currencies eg: which lacks a decimal point.
     */
    public function present(): string
    {
        $amount = number_format($this->amount / 100, 2, '.', ',');

        return $this->getCurrencyCode().' '.$amount;
    }
}
