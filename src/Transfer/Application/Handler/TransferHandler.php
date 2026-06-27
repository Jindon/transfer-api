<?php

declare(strict_types=1);

namespace App\Transfer\Application\Handler;

use App\Account\Domain\Account;
use App\Account\Domain\Exception\AccountNotFoundException;
use App\Account\Domain\Repository\AccountRepositoryInterface;
use App\Transfer\Application\Command\TransferCommand;
use App\Transfer\Domain\Exception\TransferAlreadyProcessedException;
use App\Transfer\Domain\Repository\TransferRepositoryInterface;
use App\Transfer\Domain\Transfer;
use App\Transfer\Infrastructure\Persistence\TransactionRunner;
use DateTimeImmutable;
use Symfony\Component\Uid\Ulid;
use Throwable;

readonly class TransferHandler
{
    public function __construct(
        private AccountRepositoryInterface $accountRepository,
        private TransactionRunner $transferProcessor,
        private TransferRepositoryInterface $transferRepository,
    ) {
    }

    /**
     * @throws Throwable
     */
    public function handle(TransferCommand $command): Transfer
    {
        $sourceAccount = $this->resolveAccount($command->sourceAccountUuid);
        $sourceAccount->assertTransferable($command->amount->getAmount());

        $destinationAccount = $this->resolveAccount($command->destinationAccountUuid);

        $pendingTransfer = $this->transferRepository->createPending(
            sourceAccount: $sourceAccount,
            destinationAccount: $destinationAccount,
            amount: $command->amount,
            reference: (string) new Ulid(),
            dateTime: new DateTimeImmutable(),
        );

        $transferId = $pendingTransfer->getId();

        try {
            return $this->transferProcessor->run(function () use ($transferId) {
                $transfer = $this->transferRepository->findById($transferId);

                if (!$transfer->isPending()) {
                    throw new TransferAlreadyProcessedException();
                }

                $sourceAccountId = $transfer->getSourceAccount()->getId();
                $destinationAccountId = $transfer->getDestinationAccount()->getId();

                $accounts = $this->accountRepository->getOrderedLockForUpdate([$sourceAccountId, $destinationAccountId]);

                /** @var Account $sourceAccount */
                $sourceAccount = $accounts[$sourceAccountId];
                /** @var Account $destinationAccount */
                $destinationAccount = $accounts[$destinationAccountId];

                $transfer->complete(new DateTimeImmutable());

                $sourceAccount->debit($transfer->getAmount());
                $destinationAccount->credit($transfer->getAmount());

                $this->accountRepository->save($sourceAccount);
                $this->accountRepository->save($destinationAccount);

                return $transfer;
            });
        } catch (Throwable $e) {
            $this->transferRepository->markFailed($transferId, $e->getMessage());

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
}
