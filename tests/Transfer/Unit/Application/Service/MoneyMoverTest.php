<?php

declare(strict_types=1);

namespace App\Tests\Transfer\Unit\Application\Service;

use App\Account\Domain\Repository\AccountRepositoryInterface;
use App\Ledger\Domain\Repository\LedgerEntryRepositoryInterface;
use App\Tests\Support\Genie;
use App\Transfer\Application\Service\MoneyMover;
use App\Transfer\Domain\Enum\TransferStatus;
use App\Transfer\Domain\Exception\TransferAlreadyProcessedException;
use App\Transfer\Domain\Repository\TransferRepositoryInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

#[AllowMockObjectsWithoutExpectations]
final class MoneyMoverTest extends TestCase
{
    private MockObject|AccountRepositoryInterface $accountRepository;
    private MockObject|TransferRepositoryInterface $transferRepository;
    private MockObject|LedgerEntryRepositoryInterface $ledgerRepository;

    protected function setUp(): void
    {
        $this->accountRepository = $this->createMock(AccountRepositoryInterface::class);
        $this->transferRepository = $this->createMock(TransferRepositoryInterface::class);
        $this->ledgerRepository = $this->createMock(LedgerEntryRepositoryInterface::class);
    }

    /**
     * @throws \ReflectionException
     */
    public function testExecuteMovesMoneyAndCompletesTransfer(): void
    {
        $src = Genie::makeAccount(balance: 50000);
        $dst = Genie::makeAccount(balance: 10000);
        new ReflectionProperty($src, 'id')->setValue($src, 1);
        new ReflectionProperty($dst, 'id')->setValue($dst, 2);

        $transfer = Genie::makeTransfer($src, $dst, amount: 10000);
        new ReflectionProperty($transfer, 'id')->setValue($transfer, 99);

        $this->transferRepository->expects($this->once())->method('findById')->with(99)->willReturn($transfer);
        $this->accountRepository->method('getOrderedLockForUpdate')->willReturn([1 => $src, 2 => $dst]);

        $this->ledgerRepository->expects($this->once())->method('recordDoubleEntry');
        $this->accountRepository->expects($this->exactly(2))->method('save');

        $result = $this->makeMoneyMover()->execute(99);

        $this->assertFalse($result->isPending());
    }

    public function testExecuteThrowsWhenTransferIsNotPending(): void
    {
        $transfer = Genie::makeTransfer(status: TransferStatus::COMPLETED);
        $this->transferRepository->method('findById')->willReturn($transfer);

        $this->expectException(TransferAlreadyProcessedException::class);

        $this->makeMoneyMover()->execute(1);
    }

    private function makeMoneyMover(): MoneyMover
    {
        return new MoneyMover($this->accountRepository, $this->transferRepository, $this->ledgerRepository);
    }
}
