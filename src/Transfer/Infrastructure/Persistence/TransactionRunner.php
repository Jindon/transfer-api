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
        $this->entityManager->beginTransaction();

        try {
            $result = $work();
            $this->entityManager->flush();
            $this->entityManager->commit();

            return $result;
        } catch (Throwable $exception) {
            $this->entityManager->rollback();
            $this->entityManager->clear();
            throw $exception;
        }
    }
}
