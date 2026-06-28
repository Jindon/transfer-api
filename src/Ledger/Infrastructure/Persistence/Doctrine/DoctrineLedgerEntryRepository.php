<?php

declare(strict_types=1);

namespace App\Ledger\Infrastructure\Persistence\Doctrine;

use App\Account\Domain\Account;
use App\Ledger\Domain\Enum\LedgerDirection;
use App\Ledger\Domain\LedgerEntry;
use App\Ledger\Domain\Repository\LedgerEntryRepositoryInterface;
use App\Shared\Money\Money;
use App\Transfer\Domain\Transfer;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineLedgerEntryRepository implements LedgerEntryRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function recordDoubleEntry(Transfer $transfer, Account $source, Account $destination, Money $amount): void
    {
        $now = new DateTimeImmutable();

        $debit = LedgerEntry::make(
            $transfer,
            account: $source,
            ledgerDirection: LedgerDirection::DEBIT,
            amount: $amount,
            createdAt: $now,
        );

        $credit = LedgerEntry::make(
            $transfer,
            account: $destination,
            ledgerDirection: LedgerDirection::CREDIT,
            amount: $amount,
            createdAt: $now,
        );

        $this->entityManager->persist($debit);
        $this->entityManager->persist($credit);
    }
}
