<?php

declare(strict_types=1);

namespace App\Tests\Transfer\Unit\Application\Handler;

use App\Account\Domain\Exception\AccountNotFoundException;
use App\Account\Domain\Exception\InsufficientFundException;
use App\Account\Domain\Repository\AccountRepositoryInterface;
use App\Idempotency\Domain\Exception\RequestHashMismatchException;
use App\Idempotency\Domain\IdempotencyRequest;
use App\Idempotency\Domain\Repository\IdempotencyRequestRepositoryInterface;
use App\Shared\Money\Money;
use App\Tests\Support\Genie;
use App\Transfer\Application\Command\TransferCommand;
use App\Transfer\Application\Handler\TransferHandler;
use App\Transfer\Application\Service\MoneyMover;
use App\Transfer\Domain\Event\TransferCompleted;
use App\Transfer\Domain\Event\TransferCreated;
use App\Transfer\Domain\Event\TransferFailed;
use App\Transfer\Domain\Exception\TransferConflictException;
use App\Transfer\Domain\Repository\TransferRepositoryInterface;
use App\Transfer\Domain\Transfer;
use App\Transfer\Infrastructure\Persistence\TransactionRunner;
use App\Txm\Domain\Repository\QuarantinedTransferRepositoryInterface;
use App\Txm\Domain\Service\TransactionMonitorInterface;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\Exception as DriverException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use ReflectionException;
use ReflectionProperty;
use RuntimeException;
use stdClass;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

#[AllowMockObjectsWithoutExpectations]
final class TransferHandlerTest extends TestCase
{
    private MockObject|AccountRepositoryInterface $accountRepository;
    private MockObject|TransferRepositoryInterface $transferRepository;
    private MockObject|IdempotencyRequestRepositoryInterface $idempotencyRepository;
    private MockObject|MoneyMover $moneyMover;
    private MockObject|TransactionMonitorInterface $transactionMonitor;
    private MockObject|QuarantinedTransferRepositoryInterface $quarantineRepository;
    private MockObject|CacheItemPoolInterface $cache;
    private MockObject|LoggerInterface $logger;
    private MockObject|MessageBusInterface $messageBus;
    private TransactionRunner $runner;

    protected function setUp(): void
    {
        $this->accountRepository = $this->createMock(AccountRepositoryInterface::class);
        $this->transferRepository = $this->createMock(TransferRepositoryInterface::class);
        $this->idempotencyRepository = $this->createMock(IdempotencyRequestRepositoryInterface::class);
        $this->moneyMover = $this->createMock(MoneyMover::class);
        $this->transactionMonitor = $this->createMock(TransactionMonitorInterface::class);
        $this->transactionMonitor->method('check')->willReturn(true);
        $this->quarantineRepository = $this->createMock(QuarantinedTransferRepositoryInterface::class);
        $this->cache = $this->createMock(CacheItemPoolInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->messageBus->method('dispatch')->willReturn(new Envelope(new stdClass()));
        $this->runner = $this->makePassthroughRunner();
    }

    public function testHandleReturnsCachedTransferOnCacheHit(): void
    {
        $transfer = Genie::makeTransfer();
        $this->cacheHit(sourceId: 42, hash: 'hash-abc');
        $this->transferRepository->method('findById')->willReturn($transfer);
        $this->idempotencyRepository->expects($this->never())->method('reserve');
        $this->messageBus->expects($this->never())->method('dispatch');

        $result = $this->makeHandler()->handle($this->makeCommand(hash: 'hash-abc'));

        $this->assertSame($transfer, $result);
    }

    public function testHandleThrowsOnCacheHitWithHashMismatch(): void
    {
        $this->cacheHit(sourceId: 42, hash: 'stored-hash');
        $this->transferRepository->method('findById')->willReturn(Genie::makeTransfer());

        $this->expectException(RequestHashMismatchException::class);

        $this->makeHandler()->handle($this->makeCommand(hash: 'different-hash'));
    }

    public function testHandleFallsThroughToDbWhenCacheServiceIsDown(): void
    {
        $this->cache->method('getItem')->willThrowException(new RuntimeException('Redis is down'));
        $this->idempotencyRepository->expects($this->once())->method('reserve');
        $this->accountRepository->method('findByUuid')->willReturn(null);

        $this->expectException(AccountNotFoundException::class);

        $this->makeHandler()->handle($this->makeCommand());
    }

    public function testHandleThrowsWhenSourceAccountNotFound(): void
    {
        $this->cacheMiss();
        $this->accountRepository->method('findByUuid')->willReturn(null);

        $this->expectException(AccountNotFoundException::class);

        $this->makeHandler()->handle($this->makeCommand());
    }

    public function testHandleThrowsOnInsufficientFunds(): void
    {
        $this->cacheMiss();
        $this->accountRepository->method('findByUuid')->willReturn(Genie::makeAccount(balance: 50));

        $this->expectException(InsufficientFundException::class);

        $this->makeHandler()->handle($this->makeCommand()); // command requests 1000 cents
    }

    public function testHandleThrowsWhenDestinationAccountNotFound(): void
    {
        $this->cacheMiss();
        $command = $this->makeCommand();
        $sourceAccount = Genie::makeAccount();

        $this->accountRepository->method('findByUuid')
            ->willReturnCallback(fn (string $uuid) => $uuid === $command->sourceAccountUuid ? $sourceAccount : null);

        $this->expectException(AccountNotFoundException::class);

        $this->makeHandler()->handle($command);
    }

    public function testHandleReturnsExistingTransferOnDuplicateIdempotencyKey(): void
    {
        $this->cacheMiss();
        $command = $this->makeCommand();

        $idemRequest = new IdempotencyRequest($command->idempotencyKey, $command->requestHash);
        $idemRequest->attach(Transfer::class, 42);

        $this->idempotencyRepository->method('reserve')
            ->willThrowException($this->makeUniqueConstraintException());
        $this->idempotencyRepository->method('findOrThrow')->willReturn($idemRequest);

        $existingTransfer = Genie::makeTransfer();
        $this->transferRepository->expects($this->once())->method('findById')->with(42)->willReturn($existingTransfer);

        $result = $this->makeHandler()->handle($command);

        $this->assertSame($existingTransfer, $result);
    }

    public function testHandleThrowsConflictWhenTransferKeyIsInProgress(): void
    {
        $this->cacheMiss();
        $command = $this->makeCommand();

        // no attach() call → sourceId stays null, signaling the transfer hasn't been created yet
        $idemRequest = new IdempotencyRequest($command->idempotencyKey, $command->requestHash);

        $this->idempotencyRepository->method('reserve')
            ->willThrowException($this->makeUniqueConstraintException());
        $this->idempotencyRepository->method('findOrThrow')->willReturn($idemRequest);

        $this->expectException(TransferConflictException::class);

        $this->makeHandler()->handle($command);
    }

    public function testHandleMarksTransferFailedAndReleasesKeyOnTransactionFailure(): void
    {
        $this->cacheMiss();
        $command = $this->makeCommand();

        $sourceAccount = Genie::makeAccount();
        $destAccount = Genie::makeAccount();
        $pendingTransfer = Genie::makeTransfer($sourceAccount, $destAccount);
        $this->setId($pendingTransfer, 99);

        $this->accountRepository->method('findByUuid')
            ->willReturnCallback(fn (string $uuid) => $uuid === $command->sourceAccountUuid ? $sourceAccount : $destAccount);

        $this->transferRepository->method('createPending')->willReturn($pendingTransfer);
        $this->moneyMover->expects($this->once())->method('execute')
            ->with(99)
            ->willThrowException(new RuntimeException('db exploded'));

        $this->transferRepository->expects($this->once())->method('markFailed')->with(99, 'db exploded');
        $this->idempotencyRepository->expects($this->once())->method('release')->with($command->idempotencyKey);
        $this->logger->expects($this->once())->method('warning')->with('transfer.failed', $this->anything());

        $dispatched = [];
        $this->messageBus->method('dispatch')
            ->willReturnCallback(static function (object $event) use (&$dispatched): Envelope {
                $dispatched[] = $event;

                return new Envelope($event);
            });

        try {
            $this->makeHandler()->handle($command);
            $this->fail('Expected RuntimeException');
        } catch (RuntimeException $e) {
            $this->assertSame('db exploded', $e->getMessage());
        }

        $this->assertCount(2, $dispatched);
        $this->assertInstanceOf(TransferCreated::class, $dispatched[0]);
        $this->assertSame(99, $dispatched[0]->transferId);
        $this->assertInstanceOf(TransferFailed::class, $dispatched[1]);
        $this->assertSame(99, $dispatched[1]->transferId);
    }

    public function testHandleDispatchesTransferCreatedAndCompletedOnSuccess(): void
    {
        $this->cacheMiss();
        $command = $this->makeCommand();

        $srcAccount = Genie::makeAccount();
        $dstAccount = Genie::makeAccount();
        $this->setId($srcAccount, 1);
        $this->setId($dstAccount, 2);

        $pendingTransfer = Genie::makeTransfer($srcAccount, $dstAccount);
        $this->setId($pendingTransfer, 99);

        $this->accountRepository->method('findByUuid')
            ->willReturnCallback(fn (string $uuid) => $uuid === $command->sourceAccountUuid ? $srcAccount : $dstAccount);
        $this->transferRepository->method('createPending')->willReturn($pendingTransfer);
        $this->moneyMover->method('execute')->willReturn($pendingTransfer);

        $dispatched = [];
        $this->messageBus->method('dispatch')
            ->willReturnCallback(static function (object $event) use (&$dispatched): Envelope {
                $dispatched[] = $event;

                return new Envelope($event);
            });

        $this->makeHandler()->handle($command);

        $this->assertCount(2, $dispatched);
        $this->assertInstanceOf(TransferCreated::class, $dispatched[0]);
        $this->assertSame(99, $dispatched[0]->transferId);
        $this->assertInstanceOf(TransferCompleted::class, $dispatched[1]);
        $this->assertSame(99, $dispatched[1]->transferId);
    }

    public function testHandleQuarantinesTransferWhenTxmFails(): void
    {
        $this->cacheMiss();
        $command = $this->makeCommand();

        $sourceAccount = Genie::makeAccount();
        $destAccount = Genie::makeAccount();
        $pendingTransfer = Genie::makeTransfer($sourceAccount, $destAccount);
        $this->setId($pendingTransfer, 55);

        $this->accountRepository->method('findByUuid')
            ->willReturnCallback(fn (string $uuid) => $uuid === $command->sourceAccountUuid ? $sourceAccount : $destAccount);
        $this->transferRepository->method('createPending')->willReturn($pendingTransfer);

        $this->transactionMonitor = $this->createMock(TransactionMonitorInterface::class);
        $this->transactionMonitor->method('check')->willReturn(false);
        $this->quarantineRepository->expects($this->once())->method('quarantine')->with($pendingTransfer);
        $this->moneyMover->expects($this->never())->method('execute');

        $result = $this->makeHandler()->handle($command);

        $this->assertSame($pendingTransfer, $result);
    }

    private function makeHandler(): TransferHandler
    {
        return new TransferHandler(
            $this->accountRepository,
            $this->runner,
            $this->transferRepository,
            $this->idempotencyRepository,
            $this->moneyMover,
            $this->transactionMonitor,
            $this->quarantineRepository,
            $this->cache,
            $this->logger,
            $this->messageBus,
        );
    }

    private function makeCommand(string $key = 'idem-key-1', string $hash = 'hash-abc'): TransferCommand
    {
        return new TransferCommand(
            sourceAccountUuid: 'src-uuid',
            destinationAccountUuid: 'dst-uuid',
            amount: Money::make(1000, 'EUR'),
            idempotencyKey: $key,
            requestHash: $hash,
        );
    }

    private function makePassthroughRunner(): TransactionRunner
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('getTransactionNestingLevel')->willReturn(1);
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getConnection')->willReturn($connection);

        return new TransactionRunner($em, $this->createStub(LoggerInterface::class), maxAttempts: 1, initialRetryDelay: 0);
    }

    private function cacheMiss(): void
    {
        $item = $this->createStub(CacheItemInterface::class);
        $item->method('isHit')->willReturn(false);
        $this->cache->method('getItem')->willReturn($item);
    }

    private function cacheHit(int $sourceId, string $hash): void
    {
        $item = $this->createStub(CacheItemInterface::class);
        $item->method('isHit')->willReturn(true);
        $item->method('get')->willReturn(['source_id' => $sourceId, 'request_hash' => $hash]);
        $this->cache->method('getItem')->willReturn($item);
    }

    private function makeUniqueConstraintException(): UniqueConstraintViolationException
    {
        return new UniqueConstraintViolationException($this->createStub(DriverException::class), null);
    }

    /**
     * @throws ReflectionException
     */
    private function setId(object $entity, int $id): void
    {
        new ReflectionProperty($entity, 'id')->setValue($entity, $id);
    }
}
