<?php

declare(strict_types=1);

namespace App\Transfer\Infrastructure\Persistence;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Exception\DeadlockException;
use Doctrine\DBAL\Exception\LockWaitTimeoutException;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Psr\Log\LoggerInterface;
use Random\Randomizer;
use Throwable;

final readonly class TransactionRunner
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
        private int $maxAttempts = 3,
        private int $initialRetryDelay = 10_000, // ms
    ) {
    }

    /**
     * @throws Throwable
     */
    public function run(callable $work): mixed
    {
        $connection = $this->entityManager->getConnection();

        for ($attempt = 1; $attempt <= $this->maxAttempts; ++$attempt) {
            $connection->beginTransaction();

            try {
                $result = $work();

                $this->entityManager->flush();
                $connection->commit();

                return $result;
            } catch (DeadlockException|LockWaitTimeoutException $exception) {
                $this->rollback($connection);
                $this->entityManager->clear();

                if ($attempt === $this->maxAttempts) {
                    $this->logger->warning('transfer.retry_exhausted', [
                        'attempts' => $attempt,
                        'exception' => $exception::class,
                    ]);

                    throw $exception;
                }

                $this->logger->warning('transfer.retry', [
                    'attempt' => $attempt,
                    'exception' => $exception::class,
                ]);

                usleep($this->retryDelay($attempt));
            } catch (Throwable $exception) {
                $this->rollback($connection);
                $this->entityManager->clear();

                throw $exception;
            }
        }

        throw new LogicException('Transaction runner exited unexpectedly.');
    }

    /**
     * @throws Exception
     */
    private function rollback(Connection $connection): void
    {
        if ($connection->getTransactionNestingLevel() > 0) {
            $connection->rollBack();
        }
    }

    private function retryDelay(int $attempt): int
    {
        $baseDelay = $this->initialRetryDelay * (2 ** ($attempt - 1));

        return $baseDelay + new Randomizer()->getInt(0, $baseDelay);
    }
}
