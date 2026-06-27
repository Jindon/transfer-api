<?php

declare(strict_types=1);

namespace App\Idempotency\Infrastructure\Persistence\Doctrine;

use App\Idempotency\Domain\Exception\RequestHashMismatchException;
use App\Idempotency\Domain\IdempotencyRequest;
use App\Idempotency\Domain\Repository\IdempotencyRequestRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;

readonly class DoctrineIdempotencyRequestRepository implements IdempotencyRequestRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function reserve(string $key, string $requestHash): void
    {
        $idempotencyRequest = new IdempotencyRequest($key, $requestHash);

        $this->entityManager->persist($idempotencyRequest);
        $this->entityManager->flush();
    }

    public function findByKey(string $key): ?IdempotencyRequest
    {
        return $this->entityManager->getRepository(IdempotencyRequest::class)->findOneBy(['key' => $key]);
    }

    public function findOrThrow(string $key): IdempotencyRequest
    {
        $idempotencyRequest = $this->findByKey($key);

        if (!$idempotencyRequest) {
            throw new RuntimeException("Idempotency key not found: {$key}");
        }

        return $idempotencyRequest;
    }

    public function assertSameRequest(IdempotencyRequest $idempotencyRequest, string $requestHash): void
    {
        if (!hash_equals($idempotencyRequest->getRequestHash(), $requestHash)) {
            throw new RequestHashMismatchException();
        }
    }

    public function attach(string $key, string $sourceType, int $sourceId): void
    {
        $idempotencyRequest = $this->findOrThrow($key);

        $idempotencyRequest->attach($sourceType, $sourceId);
        $this->entityManager->flush();
    }

    public function release(string $key): void
    {
        $idempotencyRequest = $this->findOrThrow($key);

        $this->entityManager->remove($idempotencyRequest);
        $this->entityManager->flush();
    }
}
