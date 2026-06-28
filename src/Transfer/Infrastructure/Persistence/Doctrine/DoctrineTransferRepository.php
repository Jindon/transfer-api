<?php

namespace App\Transfer\Infrastructure\Persistence\Doctrine;

use App\Account\Domain\Account;
use App\Shared\Money\Money;
use App\Transfer\Domain\Enum\TransferStatus;
use App\Transfer\Domain\Repository\TransferRepositoryInterface;
use App\Transfer\Domain\Transfer;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;

readonly class DoctrineTransferRepository implements TransferRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @throws ORMException
     */
    public function findById(int $id): ?Transfer
    {
        return $this->entityManager->find(Transfer::class, $id);
    }

    public function findByUuid(string $uuid): ?Transfer
    {
        return $this->entityManager->getRepository(Transfer::class)->findOneBy(['uuid' => $uuid]);
    }

    public function createPending(
        Account $sourceAccount,
        Account $destinationAccount,
        Money $amount,
        string $reference,
        DateTimeImmutable $dateTime,
    ): Transfer {
        $transfer = new Transfer();

        $transfer->setAmount($amount->getAmount());
        $transfer->setCurrency($amount->getCurrencyCode());
        $transfer->setReference($reference);
        $transfer->setDestinationAccount($destinationAccount);
        $transfer->setSourceAccount($sourceAccount);
        $transfer->setStatus(TransferStatus::PENDING);
        $transfer->setCreatedAt($dateTime);

        $this->entityManager->persist($transfer);
        $this->entityManager->flush();

        return $transfer;
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function markFailed(int $transferId, ?string $reason = null): void
    {
        $transfer = $this->entityManager->find(Transfer::class, $transferId);

        if ($transfer) {
            $transfer->fail($reason);
            $this->entityManager->flush();
        }
    }
}
