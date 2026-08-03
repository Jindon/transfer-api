<?php

declare(strict_types=1);

namespace App\Transfer\Application\Handler;

use App\Account\Domain\Account;
use App\Account\Domain\Exception\AccountNotFoundException;
use App\Account\Domain\Repository\AccountRepositoryInterface;
use App\Idempotency\Domain\Exception\RequestHashMismatchException;
use App\Idempotency\Domain\Repository\IdempotencyRequestRepositoryInterface;
use App\Transfer\Application\Command\TransferCommand;
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
use DateTimeImmutable;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Ulid;
use Throwable;

readonly class TransferHandler
{
    private const int REPLAY_TTL = 86400; // 24h

    public function __construct(
        private AccountRepositoryInterface $accountRepository,
        private TransactionRunner $transferProcessor,
        private TransferRepositoryInterface $transferRepository,
        private IdempotencyRequestRepositoryInterface $idempotencyRequestRepository,
        private MoneyMover $moneyMover,
        private TransactionMonitorInterface $transactionMonitor,
        private QuarantinedTransferRepositoryInterface $quarantinedTransferRepository,
        private CacheItemPoolInterface $replayCache,
        private LoggerInterface $logger,
        private MessageBusInterface $messageBus,
    ) {
    }

    /**
     * @throws Throwable
     */
    public function handle(TransferCommand $command): Transfer
    {
        /**
         * If request is duplicate, then check cache and return immediately.
         */
        $cached = $this->fromReplayCache($command);
        if ($cached) {
            return $cached;
        }

        try {
            $this->idempotencyRequestRepository->reserve($command->idempotencyKey, $command->requestHash);
        } catch (UniqueConstraintViolationException) {
            /**
             * if unique constraint, means entry for key exists
             * get that out of DB and push it to replay cache before returning the transfer.
             */
            $transfer = $this->resolveFromExistingKey($command);
            $this->toReplayCache($command->idempotencyKey, $command->requestHash, $transfer);

            return $transfer;
        }

        $pendingTransfer = $this->createPendingTransfer($command);

        $transferId = $pendingTransfer->getId();

        $this->idempotencyRequestRepository->attach($command->idempotencyKey, Transfer::class, $transferId);

        $this->messageBus->dispatch(new TransferCreated($transferId));

        if (!$this->transactionMonitor->check($pendingTransfer)) {
            $this->quarantinedTransferRepository->quarantine($pendingTransfer);

            return $pendingTransfer;
        }

        try {
            $transfer = $this->transferProcessor->run(fn () => $this->moneyMover->execute($transferId));

            $this->toReplayCache($command->idempotencyKey, $command->requestHash, $transfer);
            $this->messageBus->dispatch(new TransferCompleted($transfer->getId()));

            return $transfer;
        } catch (Throwable $e) {
            $this->transferRepository->markFailed($transferId, $e->getMessage());
            $this->idempotencyRequestRepository->release($command->idempotencyKey);

            $this->logger->warning('transfer.failed', [
                'transfer_id' => $transferId,
                'reason' => $e->getMessage(),
            ]);

            try {
                $this->messageBus->dispatch(new TransferFailed($transferId));
            } catch (Throwable) {
                // Bus failure must not mask the original transfer exception
            }

            throw $e;
        }
    }

    private function resolveAccount(string $accountUuid): Account
    {
        $account = $this->accountRepository->findByUuid($accountUuid);

        if (is_null($account)) {
            throw new AccountNotFoundException($accountUuid);
        }

        return $account;
    }

    private function resolveFromExistingKey(TransferCommand $command): Transfer
    {
        $existing = $this->idempotencyRequestRepository->findOrThrow($command->idempotencyKey);
        $this->idempotencyRequestRepository->assertSameRequest($existing, $command->requestHash);

        if (!$existing->getSourceId()) {
            throw new TransferConflictException('Request already in progress');
        }

        $this->logger->info('transfer.db_replay', ['key' => $command->idempotencyKey]);

        return $this->transferRepository->findById($existing->getSourceId());
    }

    private function createPendingTransfer(TransferCommand $command): Transfer
    {
        $sourceAccount = $this->resolveAccount($command->sourceAccountUuid);
        $sourceAccount->assertTransferable($command->amount->getAmount());

        $destinationAccount = $this->resolveAccount($command->destinationAccountUuid);

        return $this->transferRepository->createPending(
            sourceAccount: $sourceAccount,
            destinationAccount: $destinationAccount,
            amount: $command->amount,
            reference: (string) new Ulid(),
            dateTime: new DateTimeImmutable(),
        );
    }

    private function fromReplayCache(TransferCommand $command): ?Transfer
    {
        try {
            $item = $this->replayCache->getItem($this->cacheKey($command->idempotencyKey));

            if (!$item->isHit()) {
                return null;
            }

            ['source_id' => $sourceId, 'request_hash' => $storedHash] = $item->get();

            if (!hash_equals($command->requestHash, $storedHash)) {
                throw new RequestHashMismatchException();
            }

            $transfer = $this->transferRepository->findById($sourceId);

            if (!$transfer) {
                return null; // falls through DB
            }

            $this->logger->info('transfer.cache_replay', ['key' => $command->idempotencyKey]);

            return $transfer;
        } catch (RequestHashMismatchException $e) {
            throw $e;
        } catch (Throwable) {
            // Service down - falls through DB
            return null;
        }
    }

    private function toReplayCache(string $key, string $requestHash, Transfer $transfer): void
    {
        try {
            $item = $this->replayCache->getItem($this->cacheKey($key));
            $item->set(['source_id' => $transfer->getId(), 'request_hash' => $requestHash]);
            $item->expiresAfter(self::REPLAY_TTL);
            $this->replayCache->save($item);
        } catch (Throwable) {
            // Don't throw, Redis failure shouldn't impact the transfer flow
        }
    }

    private function cacheKey(string $idempotencyKey): string
    {
        return 'idem_transfer_'.hash('sha256', $idempotencyKey);
    }
}
