<?php

declare(strict_types=1);

namespace App\Tests\Transfer\Functional\Presentation\Http\Api\Controller;

use App\Shared\Money\Currency;
use App\Transfer\Application\Command\TransferCommand;
use App\Transfer\Application\Handler\TransferHandler;
use App\Transfer\Domain\Enum\TransferStatus;
use App\Transfer\Domain\Transfer;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;

class TransferControllerTest extends WebTestCase
{
    #[AllowMockObjectsWithoutExpectations]
    public function testCreateTransfer(): void
    {
        $client = $this->createClient();

        $handler = $this->createMock(TransferHandler::class);

        $transfer = $this->mockTransfer();

        $sourceAccountUuid = (string) Uuid::v7();
        $destinationAccountUuid = (string) Uuid::v7();

        $handler
            ->expects($this->once())
            ->method('handle')
            ->with($this->callback(function (TransferCommand $command) use ($sourceAccountUuid, $destinationAccountUuid): bool {
                $this->assertSame($sourceAccountUuid, $command->sourceAccountUuid);
                $this->assertSame($destinationAccountUuid, $command->destinationAccountUuid);

                $this->assertSame(1000_00, $command->amount->getAmount());
                $this->assertSame(Currency::EUR, $command->amount->getCurrencyCode());

                $this->assertSame('idem-123', $command->idempotencyKey);

                return true;
            }))
            ->willReturn($transfer);

        $this->getContainer()->set(TransferHandler::class, $handler);

        $client->request(
            'POST',
            '/api/transfers',
            server: $this->apiHeaders([
                'HTTP_IDEMPOTENCY_KEY' => 'idem-123',
            ]),
            content: json_encode([
                'sourceAccountUuid' => $sourceAccountUuid,
                'destinationAccountUuid' => $destinationAccountUuid,
                'amount' => 1000_00,
                'currency' => Currency::EUR,
            ])
        );

        $this->assertResponseIsSuccessful();
    }

    public function testMissingIdempotencyKeyReturnsError(): void
    {
        $client = $this->createClient();

        $client->request(
            'POST',
            '/api/transfers',
            server: [
                'CONTENT_TYPE' => 'application/json',
            ],
            content: json_encode([
                'sourceAccountUuid' => 'acc-1',
                'destinationAccountUuid' => 'acc-2',
                'amount' => 1000,
                'currency' => 'USD',
            ])
        );

        self::assertResponseStatusCodeSame(401);
    }

    public function testInvalidPayloadReturnsValidationError(): void
    {
        $client = $this->createClient();

        $client->request(
            'POST',
            '/api/transfers',
            server: $this->apiHeaders([
                'HTTP_IDEMPOTENCY_KEY' => 'idem-123',
            ]),
            content: json_encode([
                'amount' => -100,
            ])
        );

        self::assertResponseStatusCodeSame(422);
    }

    private function apiHeaders(array $headers = []): array
    {
        return array_merge([
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_API_KEY' => $this->getContainer()->getParameter('app.api_key'),
        ], $headers);
    }

    private function mockTransfer(array $overrides = []): Transfer
    {
        return $this->createConfiguredMock(
            Transfer::class,
            array_merge([
                'getId' => 123,
                'getStatus' => TransferStatus::COMPLETED,
                'getCurrency' => Currency::EUR,
                'getCreatedAt' => new DateTimeImmutable(),
                'getCompletedAt' => new DateTimeImmutable(),
            ], $overrides)
        );
    }
}
