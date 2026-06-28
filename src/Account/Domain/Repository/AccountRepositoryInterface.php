<?php

declare(strict_types=1);

namespace App\Account\Domain\Repository;

use App\Account\Domain\Account;

interface AccountRepositoryInterface
{
    public function findByUuid(string $uuid): ?Account;

    /** @return Account[] */
    public function findAll(): array;

    public function getOrderedLockForUpdate(array $ids): array;

    public function save(Account $account): void;
}
