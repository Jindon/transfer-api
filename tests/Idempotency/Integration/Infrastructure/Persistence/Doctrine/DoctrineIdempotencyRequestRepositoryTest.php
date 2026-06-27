<?php

declare(strict_types=1);

namespace App\Tests\Idempotency\Integration\Infrastructure\Persistence\Doctrine;

use App\Idempotency\Domain\Exception\RequestHashMismatchException;
use App\Idempotency\Domain\Repository\IdempotencyRequestRepositoryInterface;
use App\Transfer\Domain\Transfer;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class DoctrineIdempotencyRequestRepositoryTest extends KernelTestCase
{
    private IdempotencyRequestRepositoryInterface $repository;
    private EntityManagerInterface $entityManager;

    public function setup(): void
    {
        parent::setUp();

        self::bootKernel();

        $this->repository = self::getContainer()->get(IdempotencyRequestRepositoryInterface::class);
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
    }

    public function testReservePersistsIdempotencyRequest(): void
    {
        $this->repository->reserve(
            'key-123',
            'hash-123'
        );

        $this->entityManager->clear();

        $request = $this->repository->findByKey('key-123');

        $this->assertSame('key-123', $request->getKey());
        $this->assertSame('hash-123', $request->getRequestHash());
    }

    public function testFindByKeyReturnsRequest(): void
    {
        $this->repository->reserve(
            'key-123',
            'hash-123'
        );

        $this->entityManager->clear();

        $request = $this->repository->findByKey('key-123');

        $this->assertSame(
            'key-123',
            $request->getKey()
        );
    }

    public function testFindOrThrowThrowsWhenRequestDoesNotExist(): void
    {
        $this->expectException(RuntimeException::class);

        $this->repository->findOrThrow('missing');
    }

    public function testAssertSameRequestDoesNotThrow(): void
    {
        $this->repository->reserve(
            'key-123',
            'hash-123'
        );

        $request = $this->repository->findByKey('key-123');

        $this->expectNotToPerformAssertions();

        $this->repository->assertSameRequest(
            $request,
            'hash-123'
        );
    }

    public function testAssertSameRequestThrowsWhenHashDiffers(): void
    {
        $this->repository->reserve(
            'key-123',
            'hash-123'
        );

        $request = $this->repository->findByKey('key-123');

        $this->expectException(RequestHashMismatchException::class);

        $this->repository->assertSameRequest(
            $request,
            'different'
        );
    }

    public function testAttachUpdatesSourceInformation(): void
    {
        $this->repository->reserve(
            'key-123',
            'hash-123'
        );

        $this->repository->attach(
            'key-123',
            Transfer::class,
            10
        );

        $this->entityManager->clear();

        $request = $this->repository->findByKey('key-123');

        $this->assertSame(Transfer::class, $request->getSourceType());

        $this->assertSame(10, $request->getSourceId());
    }

    public function testReleaseRemovesRequest(): void
    {
        $this->repository->reserve(
            'key-123',
            'hash-123'
        );

        $this->repository->release('key-123');

        $this->assertNull($this->repository->findByKey('key-123'));
    }
}
