<?php

namespace App\Account\Infrastructure\Persistence\Doctrine;

use App\Account\Domain\Account;
use App\Account\Domain\Exception\AccountNotFoundException;
use App\Account\Domain\Repository\AccountRepositoryInterface;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;

readonly class DoctrineAccountRepository implements AccountRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function findByUuid(string $uuid): ?Account
    {
        return $this->entityManager->getRepository(Account::class)->findOneBy(['uuid' => $uuid]);
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function getOrderedLockForUpdate(array $ids): array
    {
        sort($ids, SORT_NUMERIC);

        $accounts = [];

        foreach ($ids as $id) {
            $account = $this->entityManager->find(Account::class, $id, LockMode::PESSIMISTIC_WRITE);

            if (!$account) {
                throw new AccountNotFoundException();
            }

            $accounts[$id] = $account;
        }

        return $accounts;
    }

    public function save(Account $account): void
    {
        $this->entityManager->persist($account);
    }
}
