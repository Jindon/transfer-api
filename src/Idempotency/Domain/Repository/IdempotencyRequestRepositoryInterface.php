<?php

declare(strict_types=1);

namespace App\Idempotency\Domain\Repository;

use App\Idempotency\Domain\IdempotencyRequest;

interface IdempotencyRequestRepositoryInterface
{
    public function reserve(string $key, string $requestHash): void;

    public function findByKey(string $key): ?IdempotencyRequest;

    public function findOrThrow(string $key): IdempotencyRequest;

    public function assertSameRequest(IdempotencyRequest $idempotencyRequest, string $requestHash): void;

    public function attach(string $key, string $sourceType, int $sourceId): void;

    public function release(string $key): void;
}
