<?php

declare(strict_types=1);

namespace App\Tests\Account\Functional\Presentation\Http\Api\Controller;

use App\Account\Domain\Account;
use App\Account\Domain\Repository\AccountRepositoryInterface;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;

class AccountControllerTest extends WebTestCase
{
    #[AllowMockObjectsWithoutExpectations]
    public function testIndexReturnsAccountList(): void
    {
        $client = $this->createClient();

        $account = $this->mockAccount();

        $repository = $this->createMock(AccountRepositoryInterface::class);
        $repository->method('findAll')->willReturn([$account]);

        $this->getContainer()->set(AccountRepositoryInterface::class, $repository);

        $client->request('GET', '/api/accounts', server: $this->apiHeaders());

        $this->assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertCount(1, $data);
        $this->assertSame((string) $account->getUuid(), $data[0]['uuid']);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testIndexReturnsEmptyList(): void
    {
        $client = $this->createClient();

        $repository = $this->createMock(AccountRepositoryInterface::class);
        $repository->method('findAll')->willReturn([]);

        $this->getContainer()->set(AccountRepositoryInterface::class, $repository);

        $client->request('GET', '/api/accounts', server: $this->apiHeaders());

        $this->assertResponseIsSuccessful();
        $this->assertSame([], json_decode($client->getResponse()->getContent(), true));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testShowReturnsAccount(): void
    {
        $client = $this->createClient();
        $uuid = (string) Uuid::v7();

        $account = $this->mockAccount(['getUuid' => Uuid::fromString($uuid)]);

        $repository = $this->createMock(AccountRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('findByUuid')
            ->with($uuid)
            ->willReturn($account);

        $this->getContainer()->set(AccountRepositoryInterface::class, $repository);

        $client->request('GET', '/api/accounts/'.$uuid, server: $this->apiHeaders());

        $this->assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame($uuid, $data['uuid']);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testShowReturnsNotFoundForUnknownUuid(): void
    {
        $client = $this->createClient();
        $uuid = (string) Uuid::v7();

        $repository = $this->createMock(AccountRepositoryInterface::class);
        $repository->method('findByUuid')->willReturn(null);

        $this->getContainer()->set(AccountRepositoryInterface::class, $repository);

        $client->request('GET', '/api/accounts/'.$uuid, server: $this->apiHeaders());

        self::assertResponseStatusCodeSame(404);
    }

    private function apiHeaders(): array
    {
        return [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_API_KEY' => $this->getContainer()->getParameter('app.api_key'),
        ];
    }

    private function mockAccount(array $overrides = []): Account
    {
        return $this->createConfiguredMock(
            Account::class,
            array_merge([
                'getUuid' => Uuid::v7(),
                'getCurrency' => 'EUR',
                'getBalance' => 50000,
                'getCreatedAt' => new DateTimeImmutable(),
                'getUpdatedAt' => new DateTimeImmutable(),
            ], $overrides)
        );
    }
}
