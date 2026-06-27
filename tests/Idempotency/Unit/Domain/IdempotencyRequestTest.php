<?php

declare(strict_types=1);

namespace App\Tests\Idempotency\Unit\Domain;

use App\Idempotency\Domain\IdempotencyRequest;
use App\Transfer\Domain\Transfer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

class IdempotencyRequestTest extends TestCase
{
    public function testAttachAddsSourceDetails(): void
    {
        $idempotencyKey = (string) Uuid::v7();
        $idempotencyRequest = new IdempotencyRequest(
            $idempotencyKey,
            'request_hash_test'
        );

        $this->assertNull($idempotencyRequest->getSourceType());
        $this->assertNull($idempotencyRequest->getSourceId());

        $sourceId = 12;
        $sourceType = Transfer::class;
        $idempotencyRequest->attach($sourceType, $sourceId);

        $this->assertSame($sourceId, $idempotencyRequest->getSourceId());
        $this->assertSame($sourceType, $idempotencyRequest->getSourceType());
    }
}
