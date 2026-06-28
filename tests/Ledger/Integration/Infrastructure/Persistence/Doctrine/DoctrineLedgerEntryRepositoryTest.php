<?php

declare(strict_types=1);

namespace App\Tests\Ledger\Integration\Infrastructure\Persistence\Doctrine;

use App\Ledger\Domain\Enum\LedgerDirection;
use App\Ledger\Domain\LedgerEntry;
use App\Ledger\Domain\Repository\LedgerEntryRepositoryInterface;
use App\Shared\Money\Money;
use App\Tests\Support\Genie;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DoctrineLedgerEntryRepositoryTest extends KernelTestCase
{
    private LedgerEntryRepositoryInterface $repository;
    private EntityManagerInterface $entityManager;

    public function setup(): void
    {
        parent::setUp();

        self::bootKernel();

        $this->repository = self::getContainer()->get(LedgerEntryRepositoryInterface::class);
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
    }

    public function testRecordDoubleEntryAddsLedgerInSourceAndDestination(): void
    {
        $transfer = Genie::makeTransfer();

        $this->entityManager->persist($transfer->getSourceAccount());
        $this->entityManager->persist($transfer->getDestinationAccount());
        $this->entityManager->persist($transfer);

        $this->repository->recordDoubleEntry(
            $transfer,
            $transfer->getSourceAccount(),
            $transfer->getDestinationAccount(),
            Money::make($transfer->getAmount(), $transfer->getCurrency()),
        );

        $this->entityManager->flush();
        $this->entityManager->clear();

        $this->assertSame(2, $this->entityManager->getRepository(LedgerEntry::class)->count([
            'transfer' => $transfer,
        ]));

        $debit = $this->entityManager->getRepository(LedgerEntry::class)->findOneBy([
            'transfer' => $transfer,
            'account' => $transfer->getSourceAccount(),
            'ledgerDirection' => LedgerDirection::DEBIT,
        ]);

        $this->assertNotNull($debit);
        $this->assertSame($transfer->getAmount(), $debit->getAmount());
        $this->assertSame($transfer->getCurrency(), $debit->getCurrency());

        $credit = $this->entityManager->getRepository(LedgerEntry::class)->findOneBy([
            'transfer' => $transfer,
            'account' => $transfer->getDestinationAccount(),
            'ledgerDirection' => LedgerDirection::CREDIT,
        ]);

        $this->assertNotNull($credit);
        $this->assertSame($transfer->getAmount(), $credit->getAmount());
        $this->assertSame($transfer->getCurrency(), $credit->getCurrency());
    }
}
