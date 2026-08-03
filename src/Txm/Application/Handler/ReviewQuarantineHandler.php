<?php

declare(strict_types=1);

namespace App\Txm\Application\Handler;

use App\Transfer\Application\Service\MoneyMover;
use App\Transfer\Domain\Event\TransferCompleted;
use App\Transfer\Domain\Event\TransferFailed;
use App\Transfer\Domain\Exception\TransferNotFoundException;
use App\Transfer\Domain\Repository\TransferRepositoryInterface;
use App\Transfer\Domain\Transfer;
use App\Transfer\Infrastructure\Persistence\TransactionRunner;
use App\Txm\Application\Command\ReviewQuarantineCommand;
use App\Txm\Domain\Exception\QuarantineAlreadyReviewedException;
use App\Txm\Domain\Exception\TransferNotQuarantinedException;
use App\Txm\Domain\QuarantinedTransfer;
use App\Txm\Domain\Repository\QuarantinedTransferRepositoryInterface;
use DateTimeImmutable;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Throwable;

readonly class ReviewQuarantineHandler
{
    public function __construct(
        private TransferRepositoryInterface $transferRepository,
        private QuarantinedTransferRepositoryInterface $quarantinedTransferRepository,
        private TransactionRunner $transferProcessor,
        private MoneyMover $moneyMover,
        private MessageBusInterface $messageBus,
    ) {
    }

    /**
     * @throws Throwable
     * @throws ExceptionInterface
     */
    public function handle(ReviewQuarantineCommand $command): Transfer
    {
        $transfer = $this->transferRepository->findByUuid($command->transferUuid)
            ?? throw new TransferNotFoundException($command->transferUuid);

        $quarantine = $this->quarantinedTransferRepository->findByTransfer($transfer)
            ?? throw new TransferNotQuarantinedException($command->transferUuid);

        if (!$quarantine->isPendingReview()) {
            throw new QuarantineAlreadyReviewedException();
        }

        return $command->approve
            ? $this->approve($transfer, $quarantine)
            : $this->deny($transfer, $quarantine, $command->reason);
    }

    /**
     * @throws Throwable
     * @throws ExceptionInterface
     */
    private function approve(Transfer $transfer, QuarantinedTransfer $quarantine): Transfer
    {
        $result = $this->transferProcessor->run(fn () => $this->moneyMover->execute($transfer->getId()));

        $quarantine->approve(new DateTimeImmutable());
        $this->quarantinedTransferRepository->save($quarantine);

        $this->messageBus->dispatch(new TransferCompleted($result->getId()));

        return $result;
    }

    private function deny(Transfer $transfer, QuarantinedTransfer $quarantine, ?string $reason): Transfer
    {
        $quarantine->deny($reason, new DateTimeImmutable());
        $this->quarantinedTransferRepository->save($quarantine);

        $this->transferRepository->markFailed($transfer->getId(), $reason);

        try {
            $this->messageBus->dispatch(new TransferFailed($transfer->getId()));
        } catch (Throwable) {
            // Bus failure must not mask the denial
        }

        return $transfer;
    }
}
