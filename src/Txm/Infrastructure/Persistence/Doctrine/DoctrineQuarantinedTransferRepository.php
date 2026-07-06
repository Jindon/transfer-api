<?php

declare(strict_types=1);

namespace App\Txm\Infrastructure\Persistence\Doctrine;

use App\Transfer\Domain\Transfer;
use App\Txm\Domain\QuarantinedTransfer;
use App\Txm\Domain\Repository\QuarantinedTransferRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

readonly class DoctrineQuarantinedTransferRepository implements QuarantinedTransferRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function quarantine(Transfer $transfer): QuarantinedTransfer
    {
        $quarantinedTransfer = new QuarantinedTransfer($transfer);
        $this->entityManager->persist($quarantinedTransfer);
        $this->entityManager->flush();

        return $quarantinedTransfer;
    }

    public function findByTransfer(Transfer $transfer): ?QuarantinedTransfer
    {
        return $this->entityManager->getRepository(QuarantinedTransfer::class)
            ->findOneBy(['transfer' => $transfer]);
    }

    public function save(QuarantinedTransfer $quarantinedTransfer): void
    {
        $this->entityManager->flush();
    }
}
