<?php

declare(strict_types=1);

namespace App\Tests\Transfer\Integration\Infrastructure\Persistence\Doctrine;

use App\Shared\Money\Currency;
use App\Shared\Money\Money;
use App\Tests\Support\Genie;
use App\Transfer\Domain\Enum\TransferStatus;
use App\Transfer\Domain\Repository\TransferRepositoryInterface;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class DoctrineTransferRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private TransferRepositoryInterface $transferRepository;

    protected function setUp(): void
    {
        parent::setUp();

        self::bootKernel();

        $this->transferRepository = self::getContainer()->get(TransferRepositoryInterface::class);
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
    }

    public function testFindByIdReturnsTransfer(): void
    {
        $transfer = Genie::makeTransfer();

        $this->entityManager->persist($transfer->getSourceAccount());
        $this->entityManager->persist($transfer->getDestinationAccount());
        $this->entityManager->persist($transfer);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $result = $this->transferRepository->findById($transfer->getId());

        $this->assertNotNull($result);
        $this->assertSame($transfer->getId(), $result->getId());
    }

    public function testFindByIdReturnsNullWhenTransferDoesNotExist(): void
    {
        $this->assertNull(
            $this->transferRepository->findById(999999)
        );
    }

    public function testCreatePendingPersistsTransfer(): void
    {
        $source = Genie::makeAccount();
        $destination = Genie::makeAccount();

        $this->entityManager->persist($source);
        $this->entityManager->persist($destination);
        $this->entityManager->flush();

        $now = new DateTimeImmutable();

        $pendingTransfer = $this->transferRepository->createPending(
            $source,
            $destination,
            new Money(5000, Currency::EUR),
            'REF123',
            $now,
        );

        $this->entityManager->clear();

        $transfer = $this->transferRepository->findById($pendingTransfer->getId());

        $this->assertNotNull($transfer);
        $this->assertSame(5000, $transfer->getAmount());
        $this->assertSame(Currency::EUR, $transfer->getCurrency());
        $this->assertSame('REF123', $transfer->getReference());
        $this->assertEquals(TransferStatus::PENDING, $transfer->getStatus());
        $this->assertNull($transfer->getCompletedAt());

        $this->assertSame(
            $source->getId(),
            $transfer->getSourceAccount()->getId()
        );

        $this->assertSame(
            $destination->getId(),
            $transfer->getDestinationAccount()->getId()
        );

        $this->assertEquals($now->getTimestamp(), $transfer->getCreatedAt()->getTimestamp());
    }

    public function testMarkFailedUpdatesTransfer(): void
    {
        $transfer = Genie::makeTransfer();

        $this->entityManager->persist($transfer->getSourceAccount());
        $this->entityManager->persist($transfer->getDestinationAccount());
        $this->entityManager->persist($transfer);
        $this->entityManager->flush();

        $this->transferRepository->markFailed(
            $transfer->getId(),
            'INSUFFICIENT_FUNDS'
        );

        $this->entityManager->clear();

        $updated = $this->transferRepository->findById($transfer->getId());

        $this->assertEquals(
            TransferStatus::FAILED,
            $updated->getStatus()
        );

        $this->assertSame(
            'INSUFFICIENT_FUNDS',
            $updated->getFailureReason()
        );
    }

    public function testMarkFailedDoesNotThrowWhenTransferDoesNotExist(): void
    {
        $this->expectNotToPerformAssertions();

        $this->transferRepository->markFailed(
            999999,
            'reason'
        );
    }
}
