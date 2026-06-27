<?php

declare(strict_types=1);

namespace App\Tests\Transfer\Unit\Domain;

use App\Tests\Support\Genie;
use App\Transfer\Domain\Enum\TransferStatus;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class TransferTest extends TestCase
{
    public function testCompleteMarksTransactionCompleted(): void
    {
        $transfer = Genie::makeTransfer();

        $this->assertSame(TransferStatus::PENDING, $transfer->getStatus());
        $this->assertNull($transfer->getCompletedAt());

        $transfer->complete(new DateTimeImmutable());

        $this->assertSame(TransferStatus::COMPLETED, $transfer->getStatus());
        $this->assertNotNull($transfer->getCompletedAt());
    }

    public function testFailMarksTransactionFailed(): void
    {
        $transfer = Genie::makeTransfer();

        $this->assertSame(TransferStatus::PENDING, $transfer->getStatus());
        $this->assertNull($transfer->getCompletedAt());

        $transfer->fail('error message');

        $this->assertSame(TransferStatus::FAILED, $transfer->getStatus());
        $this->assertNull($transfer->getCompletedAt());
        $this->assertEquals('error message', $transfer->getFailureReason());
    }

    public function testIsPendingStatus(): void
    {
        $pendingTransfer = Genie::makeTransfer();

        $this->assertTrue($pendingTransfer->isPending());

        $completedTransfer = Genie::makeTransfer(status: TransferStatus::COMPLETED);

        $this->assertFalse($completedTransfer->isPending());
    }
}
