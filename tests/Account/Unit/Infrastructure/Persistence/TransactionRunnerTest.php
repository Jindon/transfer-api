<?php

namespace App\Tests\Account\Unit\Infrastructure\Persistence;

use App\Transfer\Infrastructure\Persistence\TransactionRunner;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

final class TransactionRunnerTest extends TestCase
{
    private EntityManagerInterface|MockObject $entityManager;
    private Connection|MockObject $connection;
    private TransactionRunner $transactionRunner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->connection = $this->createMock(Connection::class);

        $this->entityManager
            ->method('getConnection')
            ->willReturn($this->connection);

        $this->transactionRunner = new TransactionRunner($this->entityManager);
    }

    /**
     * @throws Throwable
     */
    public function testRunCommitsTransaction(): void
    {
        $this->connection
            ->expects($this->once())
            ->method('beginTransaction');

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $this->connection
            ->expects($this->once())
            ->method('commit');

        $this->connection
            ->expects($this->never())
            ->method('rollBack');

        $result = $this->transactionRunner->run(
            fn () => 'done'
        );

        $this->assertSame('done', $result);
    }

    /**
     * @throws Throwable
     */
    public function testRunRollsBackTransactionWhenExceptionOccurs(): void
    {
        $this->connection
            ->expects($this->once())
            ->method('beginTransaction');

        $this->connection
            ->expects($this->once())
            ->method('isTransactionActive')
            ->willReturn(true);

        $this->connection
            ->expects($this->once())
            ->method('rollBack');

        $this->entityManager
            ->expects($this->once())
            ->method('clear');

        $this->entityManager
            ->expects($this->never())
            ->method('flush');

        $this->connection
            ->expects($this->never())
            ->method('commit');

        $this->expectException(RuntimeException::class);

        $this->transactionRunner->run(function () {
            throw new RuntimeException('Boom');
        });
    }

    /**
     * @throws Throwable
     */
    public function testDoesNotRollbackWhenTransactionIsNotActive(): void
    {
        $this->connection
            ->expects($this->once())
            ->method('beginTransaction');

        $this->connection
            ->expects($this->once())
            ->method('isTransactionActive')
            ->willReturn(false);

        $this->connection
            ->expects($this->never())
            ->method('rollBack');

        $this->entityManager
            ->expects($this->once())
            ->method('clear');

        $this->expectException(RuntimeException::class);

        $this->transactionRunner->run(function () {
            throw new RuntimeException('Connection lost');
        });
    }
}
