<?php

declare(strict_types=1);

namespace App\Tests\Ledger\Unit\Domain;

use App\Ledger\Domain\Enum\LedgerDirection;
use App\Ledger\Domain\LedgerEntry;
use App\Shared\Money\Money;
use App\Tests\Support\Genie;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class LedgerEntryTest extends TestCase
{
    public function testMakeOpeningCreatesACreditEntryWithNullTransfer(): void
    {
        $account = Genie::makeAccount(5_000_00);
        $money = Money::make(5_000_00, 'EUR');
        $now = new DateTimeImmutable();

        $entry = LedgerEntry::makeOpening($account, $money, $now);

        $this->assertNull($entry->getTransfer());
        $this->assertSame($account, $entry->getAccount());
        $this->assertSame(LedgerDirection::CREDIT, $entry->getLedgerDirection());
        $this->assertSame(5_000_00, $entry->getAmount());
        $this->assertSame('EUR', $entry->getCurrency());
        $this->assertSame($now, $entry->getCreatedAt());
    }
}
