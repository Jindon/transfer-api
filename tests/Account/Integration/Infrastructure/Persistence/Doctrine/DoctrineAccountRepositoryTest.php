<?php

declare(strict_types=1);

namespace App\Tests\Account\Integration\Infrastructure\Persistence\Doctrine;

use App\Account\Domain\Repository\AccountRepositoryInterface;
use App\Tests\Support\Genie;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

class DoctrineAccountRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private AccountRepositoryInterface $accountRepository;

    protected function setUp(): void
    {
        parent::setUp();

        self::bootKernel();

        $this->accountRepository = self::getContainer()->get(AccountRepositoryInterface::class);
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
    }

    public function testSavePersistsAccount(): void
    {
        $account = Genie::makeAccount();

        $this->accountRepository->save($account);

        $this->entityManager->flush();
        $this->entityManager->clear();

        $saved = $this->accountRepository->findByUuid(
            (string) $account->getUuid()
        );

        $this->assertNotNull($saved);
        $this->assertSame(
            (string) $account->getUuid(),
            (string) $saved->getUuid()
        );
    }

    public function testFindByUuidReturnsAccount(): void
    {
        $account = Genie::makeAccount();

        $this->entityManager->persist($account);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $result = $this->accountRepository->findByUuid(
            (string) $account->getUuid()
        );

        $this->assertNotNull($result);
        $this->assertSame(
            (string) $account->getUuid(),
            (string) $result->getUuid()
        );
    }

    public function testFindByUuidReturnsNullWhenAccountDoesNotExist(): void
    {
        $result = $this->accountRepository->findByUuid((string) Uuid::v7());

        $this->assertNull($result);
    }

    public function testGetOrderedLockForUpdateReturnsAccountsOrderedById(): void
    {
        $account1 = Genie::makeAccount();
        $account2 = Genie::makeAccount();

        $this->entityManager->persist($account1);
        $this->entityManager->persist($account2);
        $this->entityManager->flush();

        $maxId = max($account1->getId(), $account2->getId());
        $minId = min($account1->getId(), $account2->getId());

        $ids = [$maxId, $minId];

        $accounts = $this->entityManager->wrapInTransaction(function () use ($ids) {
            return $this->accountRepository->getOrderedLockForUpdate($ids);
        });

        $this->assertSame($ids[1], $accounts[$minId]->getId());
        $this->assertSame($ids[0], $accounts[$maxId]->getId());
    }
}
