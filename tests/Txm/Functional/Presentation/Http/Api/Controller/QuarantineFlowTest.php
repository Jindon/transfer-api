<?php

declare(strict_types=1);

namespace App\Tests\Txm\Functional\Presentation\Http\Api\Controller;

use App\Shared\Money\Currency;
use App\Tests\Support\Genie;
use App\Transfer\Domain\Enum\TransferStatus;
use App\Transfer\Domain\Transfer;
use App\Txm\Domain\Enum\QuarantineStatus;
use App\Txm\Domain\QuarantinedTransfer;
use App\Txm\Domain\Service\TransactionMonitorInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;

final class QuarantineFlowTest extends WebTestCase
{
    public function testQuarantinedTransferCanBeApproved(): void
    {
        $client = static::createClient();
        $em = $this->getContainer()->get(EntityManagerInterface::class);

        $source = Genie::makeAccount(balance: 1000_00);
        $destination = Genie::makeAccount(balance: 500_00);
        $em->persist($source);
        $em->persist($destination);
        $em->flush();

        // Swap TXM to flag every transfer
        $this->getContainer()->set(TransactionMonitorInterface::class, new class implements TransactionMonitorInterface {
            public function check(Transfer $transfer): bool
            {
                return false;
            }
        });

        $client->request(
            'POST',
            '/api/transfers',
            server: $this->headers(),
            content: json_encode([
                'sourceAccountUuid' => (string) $source->getUuid(),
                'destinationAccountUuid' => (string) $destination->getUuid(),
                'amount' => 200_00,
                'currency' => Currency::EUR,
            ]),
        );

        $this->assertResponseIsSuccessful();
        $transferUuid = json_decode($client->getResponse()->getContent(), true)['uuid'];

        // Transfer is still PENDING; balances and ledger untouched
        $em->clear();
        $transfer = $em->getRepository(Transfer::class)->findOneBy(['uuid' => $transferUuid]);
        $this->assertSame(TransferStatus::PENDING, $transfer->getStatus());
        $this->assertSame(1000_00, $em->find($source::class, $source->getId())->getBalance());

        $quarantine = $em->getRepository(QuarantinedTransfer::class)->findOneBy(['transfer' => $transfer]);
        $this->assertNotNull($quarantine);
        $this->assertSame(QuarantineStatus::PENDING_REVIEW, $quarantine->getStatus());

        // Approve
        $client->request('POST', "/api/transfers/{$transferUuid}/approve", server: $this->headers());

        $this->assertResponseIsSuccessful();
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('completed', $response['status']);

        $em->clear();
        $this->assertSame(800_00, $em->find($source::class, $source->getId())->getBalance());
        $this->assertSame(700_00, $em->find($destination::class, $destination->getId())->getBalance());

        $quarantine = $em->getRepository(QuarantinedTransfer::class)->findOneBy(['transfer' => $em->getRepository(Transfer::class)->findOneBy(['uuid' => $transferUuid])]);
        $this->assertSame(QuarantineStatus::APPROVED, $quarantine->getStatus());
    }

    public function testQuarantinedTransferCanBeDenied(): void
    {
        $client = static::createClient();
        $em = $this->getContainer()->get(EntityManagerInterface::class);

        $source = Genie::makeAccount(balance: 1000_00);
        $destination = Genie::makeAccount(balance: 500_00);
        $em->persist($source);
        $em->persist($destination);
        $em->flush();

        $this->getContainer()->set(TransactionMonitorInterface::class, new class implements TransactionMonitorInterface {
            public function check(Transfer $transfer): bool
            {
                return false;
            }
        });

        $client->request(
            'POST',
            '/api/transfers',
            server: $this->headers(),
            content: json_encode([
                'sourceAccountUuid' => (string) $source->getUuid(),
                'destinationAccountUuid' => (string) $destination->getUuid(),
                'amount' => 200_00,
                'currency' => Currency::EUR,
            ]),
        );

        $this->assertResponseIsSuccessful();
        $transferUuid = json_decode($client->getResponse()->getContent(), true)['uuid'];

        // Deny
        $client->request(
            'POST',
            "/api/transfers/{$transferUuid}/deny",
            server: $this->headers(),
            content: json_encode(['reason' => 'suspicious activity']),
        );

        $this->assertResponseIsSuccessful();
        $response = json_decode($client->getResponse()->getContent(), true);
        self::assertSame('failed', $response['status']);

        // Balances must be untouched
        $em->clear();
        $this->assertSame(1000_00, $em->find($source::class, $source->getId())->getBalance());
        $this->assertSame(500_00, $em->find($destination::class, $destination->getId())->getBalance());

        $transfer = $em->getRepository(Transfer::class)->findOneBy(['uuid' => $transferUuid]);
        $this->assertSame(TransferStatus::FAILED, $transfer->getStatus());

        $quarantine = $em->getRepository(QuarantinedTransfer::class)->findOneBy(['transfer' => $transfer]);
        $this->assertSame(QuarantineStatus::DENIED, $quarantine->getStatus());
        $this->assertSame('suspicious activity', $quarantine->getReason());
    }

    public function testApproveAlreadyReviewedQuarantineReturns409(): void
    {
        $client = static::createClient();
        $em = $this->getContainer()->get(EntityManagerInterface::class);

        $source = Genie::makeAccount(balance: 1000_00);
        $destination = Genie::makeAccount(balance: 500_00);
        $em->persist($source);
        $em->persist($destination);
        $em->flush();

        $this->getContainer()->set(TransactionMonitorInterface::class, new class implements TransactionMonitorInterface {
            public function check(Transfer $transfer): bool
            {
                return false;
            }
        });

        $client->request(
            'POST',
            '/api/transfers',
            server: $this->headers(),
            content: json_encode([
                'sourceAccountUuid' => (string) $source->getUuid(),
                'destinationAccountUuid' => (string) $destination->getUuid(),
                'amount' => 100_00,
                'currency' => Currency::EUR,
            ]),
        );

        $transferUuid = json_decode($client->getResponse()->getContent(), true)['uuid'];

        // Approve once
        $client->request('POST', "/api/transfers/{$transferUuid}/approve", server: $this->headers());
        $this->assertResponseIsSuccessful();

        // Approve again — should conflict
        $client->request('POST', "/api/transfers/{$transferUuid}/approve", server: $this->headers());
        $this->assertResponseStatusCodeSame(409);
    }

    private function headers(): array
    {
        return [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_API_KEY' => static::getContainer()->getParameter('app.api_key'),
            'HTTP_IDEMPOTENCY_KEY' => (string) Uuid::v7(),
        ];
    }
}
