<?php

declare(strict_types=1);

namespace App\Transfer\Infrastructure\Persistence;

use Doctrine\ORM\EntityManagerInterface;
use Throwable;

final readonly class TransactionRunner
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @throws Throwable
     */
    public function run(callable $work): mixed
    {
        $connection = $this->entityManager->getConnection();

        $connection->beginTransaction();

        try {
            $result = $work();

            $this->entityManager->flush();
            $connection->commit();

            return $result;
        } catch (Throwable $e) {
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }

            $this->entityManager->clear();

            throw $e;
        }
    }
}
