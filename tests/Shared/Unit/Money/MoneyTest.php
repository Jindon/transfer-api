<?php

declare(strict_types=1);

namespace App\Tests\Shared\Unit\Money;

use App\Shared\Money\Currency;
use App\Shared\Money\Money;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function testMoney(): void
    {
        $money = new Money(100_00, Currency::EUR);

        $this->assertEquals(100_00, $money->getAmount());
        $this->assertEquals(Currency::EUR, $money->getCurrencyCode());
    }

    public function testMake(): void
    {
        $money = Money::make(100_00, Currency::EUR);

        $this->assertEquals(100_00, $money->getAmount());
        $this->assertEquals(Currency::EUR, $money->getCurrencyCode());
    }

    public function testPresent(): void
    {
        $money = Money::make(1_000_00, Currency::EUR);

        $this->assertEquals('EUR 1,000.00', $money->present());
    }
}
