<?php

declare(strict_types=1);

namespace App\Tests\Account\Unit\Domain;

use App\Account\Domain\Exception\InsufficientFundException;
use App\Tests\Support\Genie;
use PHPUnit\Framework\TestCase;

class AccountTest extends TestCase
{
    public function testDebitWithSufficientBalance(): void
    {
        $account = Genie::makeAccount(6_000_00);

        $account->debit(5_000_00);

        $this->assertEquals(1_000_00, $account->getBalance());
    }

    public function testDebitWithInSufficientBalance(): void
    {
        $balance = 1_000_00;
        $debitAmount = 1_500_00;
        $account = Genie::makeAccount($balance);

        $this->expectException(InsufficientFundException::class);
        $this->expectExceptionMessageIs(sprintf(
            'Account with UUID "%s" has insufficient funds. Requested: %d, balance: %d.',
            $account->getUuid(),
            $debitAmount,
            $balance,
        ));

        $account->debit($debitAmount);

        $this->assertEquals($balance, $account->getBalance());
    }

    public function testCredit(): void
    {
        $account = Genie::makeAccount(1_000_00);

        $account->credit(500_00);

        $this->assertEquals(1_500_00, $account->getBalance());
    }

    public function testAssertSufficientBalanceDoNotThrowExceptionOnSufficientBalance(): void
    {
        $account = Genie::makeAccount(6_000_00);

        $this->expectNotToPerformAssertions();

        $account->assertSufficientBalance(5_000_00);
    }

    public function testAssertSufficientBalanceThrowsExceptionOnInsufficientBalance(): void
    {
        $account = Genie::makeAccount(4_000_00);

        $this->expectException(InsufficientFundException::class);

        $account->assertSufficientBalance(5_000_00);
    }

    public function testAssertTransferableDoNotThrowExceptionOnSuccessCondition(): void
    {
        $account = Genie::makeAccount(6_000_00);

        $this->expectNotToPerformAssertions();

        $account->assertTransferable(5_000_00);
    }

    public function testAssertTransferableThrowsExceptionOnFailingCondition(): void
    {
        $account = Genie::makeAccount(4_000_00);

        $this->expectException(InsufficientFundException::class);

        $account->assertTransferable(5_000_00);
    }
}
