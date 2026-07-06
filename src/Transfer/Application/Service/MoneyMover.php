<?php

declare(strict_types=1);

namespace App\Transfer\Application\Service;

use App\Account\Domain\Account;
use App\Account\Domain\Repository\AccountRepositoryInterface;
use App\Ledger\Domain\Repository\LedgerEntryRepositoryInterface;
use App\Shared\Money\Money;
use App\Transfer\Domain\Exception\TransferAlreadyProcessedException;
use App\Transfer\Domain\Repository\TransferRepositoryInterface;
use App\Transfer\Domain\Transfer;
use DateTimeImmutable;

readonly class MoneyMover
{
    public function __construct(
        private AccountRepositoryInterface $accountRepository,
        private TransferRepositoryInterface $transferRepository,
        private LedgerEntryRepositoryInterface $ledgerEntryRepository,
    ) {
    }

    public function execute(int $transferId): Transfer
    {
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

        $sourceAccount->debit($transfer->getAmount());
        $destinationAccount->credit($transfer->getAmount());

        $this->ledgerEntryRepository->recordDoubleEntry(
            transfer: $transfer,
            source: $sourceAccount,
            destination: $destinationAccount,
            amount: Money::make($transfer->getAmount(), $transfer->getCurrency()),
        );

        $transfer->complete(new DateTimeImmutable());

        $this->accountRepository->save($sourceAccount);
        $this->accountRepository->save($destinationAccount);

        return $transfer;
    }
}
