<?php

declare(strict_types=1);

namespace App\Tests\Txm\Unit\Application\Handler;

use App\Tests\Support\Genie;
use App\Transfer\Application\Service\MoneyMover;
use App\Transfer\Domain\Event\TransferCompleted;
use App\Transfer\Domain\Event\TransferFailed;
use App\Transfer\Domain\Repository\TransferRepositoryInterface;
use App\Transfer\Infrastructure\Persistence\TransactionRunner;
use App\Txm\Application\Command\ReviewQuarantineCommand;
use App\Txm\Application\Handler\ReviewQuarantineHandler;
use App\Txm\Domain\Exception\QuarantineAlreadyReviewedException;
use App\Txm\Domain\Exception\TransferNotQuarantinedException;
use App\Txm\Domain\QuarantinedTransfer;
use App\Txm\Domain\Repository\QuarantinedTransferRepositoryInterface;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ReflectionProperty;
use stdClass;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;

#[AllowMockObjectsWithoutExpectations]
final class ReviewQuarantineHandlerTest extends TestCase
{
    private MockObject|TransferRepositoryInterface $transferRepository;
    private MockObject|QuarantinedTransferRepositoryInterface $quarantineRepository;
    private MockObject|MoneyMover $moneyMover;
    private MockObject|MessageBusInterface $messageBus;
    private TransactionRunner $runner;

    protected function setUp(): void
    {
        $this->transferRepository = $this->createMock(TransferRepositoryInterface::class);
        $this->quarantineRepository = $this->createMock(QuarantinedTransferRepositoryInterface::class);
        $this->moneyMover = $this->createMock(MoneyMover::class);
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->messageBus->method('dispatch')->willReturn(new Envelope(new stdClass()));
        $this->runner = $this->makePassthroughRunner();
    }

    /**
     * @throws \ReflectionException
     * @throws \Throwable
     * @throws ExceptionInterface
     */
    public function testHandleApproveRunsMoneyMovementAndSavesQuarantine(): void
    {
        $transfer = Genie::makeTransfer();
        new ReflectionProperty($transfer, 'id')->setValue($transfer, 7);

        $quarantine = new QuarantinedTransfer($transfer);

        $this->transferRepository->method('findByUuid')->willReturn($transfer);
        $this->quarantineRepository->method('findByTransfer')->willReturn($quarantine);
        $this->moneyMover->expects($this->once())->method('execute')->with(7)->willReturn($transfer);
        $this->quarantineRepository->expects($this->once())->method('save')->with($quarantine);

        $dispatched = [];
        $this->messageBus->method('dispatch')
            ->willReturnCallback(static function (object $event) use (&$dispatched): Envelope {
                $dispatched[] = $event;

                return new Envelope($event);
            });

        $this->makeHandler()->handle(new ReviewQuarantineCommand('uuid', approve: true));

        $this->assertCount(1, $dispatched);
        $this->assertInstanceOf(TransferCompleted::class, $dispatched[0]);
        $this->assertSame(7, $dispatched[0]->transferId);
        $this->assertFalse($quarantine->isPendingReview());
    }

    public function testHandleDenyMarksFailedAndSavesQuarantine(): void
    {
        $transfer = Genie::makeTransfer();
        new ReflectionProperty($transfer, 'id')->setValue($transfer, 7);

        $quarantine = new QuarantinedTransfer($transfer);

        $this->transferRepository->method('findByUuid')->willReturn($transfer);
        $this->quarantineRepository->method('findByTransfer')->willReturn($quarantine);
        $this->transferRepository->expects($this->once())->method('markFailed')->with(7, 'fraud detected');
        $this->quarantineRepository->expects($this->once())->method('save')->with($quarantine);

        $dispatched = [];
        $this->messageBus->method('dispatch')
            ->willReturnCallback(static function (object $event) use (&$dispatched): Envelope {
                $dispatched[] = $event;

                return new Envelope($event);
            });

        $this->makeHandler()->handle(new ReviewQuarantineCommand('uuid', approve: false, reason: 'fraud detected'));

        $this->assertCount(1, $dispatched);
        $this->assertInstanceOf(TransferFailed::class, $dispatched[0]);
        $this->assertSame('fraud detected', $quarantine->getReason());
        $this->assertFalse($quarantine->isPendingReview());
    }

    public function testHandleThrowsWhenNoQuarantineRecordExists(): void
    {
        $transfer = Genie::makeTransfer();
        $this->transferRepository->method('findByUuid')->willReturn($transfer);
        $this->quarantineRepository->method('findByTransfer')->willReturn(null);

        $this->expectException(TransferNotQuarantinedException::class);

        $this->makeHandler()->handle(new ReviewQuarantineCommand('uuid', approve: true));
    }

    public function testHandleThrowsWhenQuarantineAlreadyReviewed(): void
    {
        $transfer = Genie::makeTransfer();
        $quarantine = new QuarantinedTransfer($transfer);
        $quarantine->approve(new DateTimeImmutable());

        $this->transferRepository->method('findByUuid')->willReturn($transfer);
        $this->quarantineRepository->method('findByTransfer')->willReturn($quarantine);

        $this->expectException(QuarantineAlreadyReviewedException::class);

        $this->makeHandler()->handle(new ReviewQuarantineCommand('uuid', approve: true));
    }

    private function makeHandler(): ReviewQuarantineHandler
    {
        return new ReviewQuarantineHandler(
            $this->transferRepository,
            $this->quarantineRepository,
            $this->runner,
            $this->moneyMover,
            $this->messageBus,
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
}
