<?php

namespace App\Tests\Account\Unit\Infrastructure\Persistence;

use App\Transfer\Infrastructure\Persistence\TransactionRunner;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\Exception as DriverException;
use Doctrine\DBAL\Exception\DeadlockException;
use Doctrine\DBAL\Exception\LockWaitTimeoutException;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

final class TransactionRunnerTest extends TestCase
{
    private MockObject|EntityManagerInterface $entityManager;
    private MockObject|Connection $connection;
    private MockObject|LoggerInterface $logger;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->connection = $this->createMock(Connection::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->entityManager->method('getConnection')->willReturn($this->connection);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testRunReturnsWorkResult(): void
    {
        $this->connection->expects($this->once())->method('beginTransaction');
        $this->entityManager->expects($this->once())->method('flush');
        $this->connection->expects($this->once())->method('commit');

        $result = $this->makeRunner()->run(fn () => 'ok');

        $this->assertSame('ok', $result);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testRunRollsBackAndRethrowsOnNonRetryableException(): void
    {
        $this->connection->method('getTransactionNestingLevel')->willReturn(1);
        $this->connection->expects($this->once())->method('rollBack');
        $this->entityManager->expects($this->once())->method('clear');
        $this->connection->expects($this->never())->method('commit');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIs('boom');

        $this->makeRunner()->run(function () { throw new RuntimeException('boom'); });
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testRunSkipsRollbackWhenNoActiveTransaction(): void
    {
        $this->connection->method('getTransactionNestingLevel')->willReturn(0);
        $this->connection->expects($this->never())->method('rollBack');

        $this->expectException(RuntimeException::class);

        $this->makeRunner()->run(function () { throw new RuntimeException(); });
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testRunRetriesOnDeadlockAndSucceeds(): void
    {
        $this->connection->method('getTransactionNestingLevel')->willReturn(1);
        $this->connection->expects($this->exactly(2))->method('beginTransaction');
        $this->connection->expects($this->once())->method('rollBack');
        $this->connection->expects($this->once())->method('commit');
        $this->entityManager->expects($this->once())->method('clear');
        $this->entityManager->expects($this->once())->method('flush');
        $this->logger->expects($this->once())->method('warning')
            ->with('transfer.retry', $this->anything());

        $calls = 0;
        $result = $this->makeRunner(maxAttempts: 2)->run(function () use (&$calls) {
            if (1 === ++$calls) {
                throw $this->makeDeadlockException();
            }

            return 'done';
        });

        $this->assertSame('done', $result);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testRunRetriesOnLockWaitTimeoutAndSucceeds(): void
    {
        $this->connection->method('getTransactionNestingLevel')->willReturn(1);
        $this->connection->expects($this->exactly(2))->method('beginTransaction');
        $this->connection->expects($this->once())->method('rollBack');
        $this->connection->expects($this->once())->method('commit');

        $calls = 0;
        $result = $this->makeRunner(maxAttempts: 2)->run(function () use (&$calls) {
            if (1 === ++$calls) {
                throw $this->makeLockWaitTimeoutException();
            }

            return 'done';
        });

        $this->assertSame('done', $result);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testRunThrowsAfterExhaustingRetries(): void
    {
        $this->connection->method('getTransactionNestingLevel')->willReturn(1);
        $this->connection->expects($this->exactly(2))->method('beginTransaction');
        $this->connection->expects($this->exactly(2))->method('rollBack');
        $this->entityManager->expects($this->exactly(2))->method('clear');

        $this->logger->expects($this->exactly(2))->method('warning')
            ->willReturnCallback(function (string $channel) {
                static $call = 0;
                match (++$call) {
                    1 => $this->assertSame('transfer.retry', $channel),
                    2 => $this->assertSame('transfer.retry_exhausted', $channel),
                    default => null,
                };
            });

        $this->expectException(DeadlockException::class);

        $exception = $this->makeDeadlockException();
        $this->makeRunner(maxAttempts: 2)->run(function () use ($exception) { throw $exception; });
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testRunDoesNotRetryNonRetryableExceptionEvenWithAttemptsRemaining(): void
    {
        $this->connection->method('getTransactionNestingLevel')->willReturn(1);
        $this->connection->expects($this->once())->method('beginTransaction');
        $this->logger->expects($this->never())->method('warning');

        $this->expectException(RuntimeException::class);

        $this->makeRunner(maxAttempts: 3)->run(function () { throw new RuntimeException(); });
    }

    private function makeRunner(int $maxAttempts = 1): TransactionRunner
    {
        return new TransactionRunner($this->entityManager, $this->logger, $maxAttempts, initialRetryDelay: 0);
    }

    private function makeDeadlockException(): DeadlockException
    {
        return new DeadlockException($this->createMock(DriverException::class), null);
    }

    private function makeLockWaitTimeoutException(): LockWaitTimeoutException
    {
        return new LockWaitTimeoutException($this->createMock(DriverException::class), null);
    }
}
