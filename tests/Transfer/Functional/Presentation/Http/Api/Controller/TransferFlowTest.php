<?php

declare(strict_types=1);

namespace App\Tests\Transfer\Functional\Presentation\Http\Api\Controller;

use App\Ledger\Domain\LedgerEntry;
use App\Shared\Money\Currency;
use App\Tests\Support\Genie;
use App\Transfer\Domain\Transfer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;

final class TransferFlowTest extends WebTestCase
{
    public function testSuccessfulTransfer(): void
    {
        $client = static::createClient();

        $em = $this->getContainer()->get(EntityManagerInterface::class);

        $source = Genie::makeAccount(balance: 1000_00);
        $destination = Genie::makeAccount(balance: 1000_00);

        $em->persist($source);
        $em->persist($destination);
        $em->flush();

        $client->request(
            'POST',
            '/api/transfers',
            server: $this->headers(),
            content: json_encode([
                'sourceAccountUuid' => (string) $source->getUuid(),
                'destinationAccountUuid' => (string) $destination->getUuid(),
                'amount' => 250_00,
                'currency' => Currency::EUR,
            ]),
        );

        $this->assertResponseIsSuccessful();

        $response = json_decode(
            $client->getResponse()->getContent(),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        self::assertArrayHasKey('uuid', $response);

        $em->clear();

        $transfer = $em->getRepository(Transfer::class)
            ->findOneBy([
                'uuid' => $response['uuid'],
            ]);

        $this->assertNotNull($transfer);

        $ledgerEntries = $em->getRepository(LedgerEntry::class)
            ->findBy([
                'transfer' => $transfer,
            ]);

        $this->assertCount(2, $ledgerEntries);

        $source = $em->find($source::class, $source->getId());
        $destination = $em->find($destination::class, $destination->getId());

        $this->assertSame(750_00, $source->getBalance());
        $this->assertSame(1_250_00, $destination->getBalance());
    }

    public function testDuplicateIdempotencyKeyReturnsExistingTransfer(): void
    {
        $client = static::createClient();

        $em = static::getContainer()->get(EntityManagerInterface::class);

        $source = Genie::makeAccount(balance: 1000_00);
        $destination = Genie::makeAccount(balance: 1000_00);

        $em->persist($source);
        $em->persist($destination);
        $em->flush();

        $idempotencyKey = (string) Uuid::v7();

        $payload = json_encode([
            'sourceAccountUuid' => (string) $source->getUuid(),
            'destinationAccountUuid' => (string) $destination->getUuid(),
            'amount' => 250_00,
            'currency' => Currency::EUR,
        ]);

        // First request
        $client->request(
            'POST',
            '/api/transfers',
            server: $this->headers($idempotencyKey),
            content: $payload,
        );

        self::assertResponseIsSuccessful();

        $first = json_decode($client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);

        // Second request with same key
        $client->request(
            'POST',
            '/api/transfers',
            server: $this->headers($idempotencyKey),
            content: $payload,
        );

        self::assertResponseIsSuccessful();

        $second = json_decode($client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame($first['uuid'], $second['uuid']);

        $em->clear();

        self::assertCount(
            1,
            $em->getRepository(Transfer::class)->findAll()
        );

        self::assertCount(
            2,
            $em->getRepository(LedgerEntry::class)->findAll()
        );
    }

    public function testTransferFailsWhenSourceHasInsufficientBalance(): void
    {
        $client = static::createClient();

        $em = static::getContainer()->get(EntityManagerInterface::class);

        $source = Genie::makeAccount(balance: 100_00);
        $destination = Genie::makeAccount(balance: 1000_00);

        $em->persist($source);
        $em->persist($destination);
        $em->flush();

        $client->request(
            'POST',
            '/api/transfers',
            server: $this->headers(),
            content: json_encode([
                'sourceAccountUuid' => (string) $source->getUuid(),
                'destinationAccountUuid' => (string) $destination->getUuid(),
                'amount' => 250_00,
                'currency' => Currency::EUR,
            ]),
        );

        self::assertResponseStatusCodeSame(422);

        $em->clear();

        $source = $em->find($source::class, $source->getId());
        $destination = $em->find($destination::class, $destination->getId());

        self::assertSame(100_00, $source->getBalance());
        self::assertSame(1000_00, $destination->getBalance());

        self::assertCount(
            0,
            $em->getRepository(LedgerEntry::class)->findAll()
        );
    }

    public function testTransferFailsWhenDestinationAccountDoesNotExist(): void
    {
        $client = static::createClient();

        $em = static::getContainer()->get(EntityManagerInterface::class);

        $source = Genie::makeAccount(balance: 1000_00);

        $em->persist($source);
        $em->flush();

        $client->request(
            'POST',
            '/api/transfers',
            server: $this->headers(),
            content: json_encode([
                'sourceAccountUuid' => (string) $source->getUuid(),
                'destinationAccountUuid' => (string) Uuid::v7(),
                'amount' => 250_00,
                'currency' => Currency::EUR,
            ]),
        );

        self::assertResponseStatusCodeSame(404);

        self::assertCount(
            0,
            $em->getRepository(Transfer::class)->findAll()
        );
    }

    private function headers(?string $idempotencyKey = null): array
    {
        return [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_API_KEY' => static::getContainer()->getParameter('app.api_key'),
            'HTTP_IDEMPOTENCY_KEY' => $idempotencyKey ?? (string) Uuid::v7(),
        ];
    }
}
