<?php

declare(strict_types=1);

namespace App\Tests\Txm\Functional\Presentation\Http\Api\Controller;

use App\Shared\Money\Currency;
use App\Transfer\Domain\Enum\TransferStatus;
use App\Transfer\Domain\Transfer;
use App\Txm\Application\Command\ReviewQuarantineCommand;
use App\Txm\Application\Handler\ReviewQuarantineHandler;
use App\Txm\Domain\Exception\QuarantineAlreadyReviewedException;
use App\Txm\Domain\Exception\TransferNotQuarantinedException;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;

final class QuarantineControllerTest extends WebTestCase
{
    #[AllowMockObjectsWithoutExpectations]
    public function testApproveCallsHandlerAndReturnsTransfer(): void
    {
        $client = $this->createClient();
        $uuid = (string) Uuid::v7();

        $handler = $this->createMock(ReviewQuarantineHandler::class);
        $handler->expects($this->once())
            ->method('handle')
            ->with($this->callback(fn (ReviewQuarantineCommand $cmd) => $cmd->transferUuid === $uuid && true === $cmd->approve))
            ->willReturn($this->mockTransfer());

        $this->getContainer()->set(ReviewQuarantineHandler::class, $handler);

        $client->request('POST', "/api/transfers/{$uuid}/approve", server: $this->apiHeaders());

        $this->assertResponseIsSuccessful();
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testDenyCallsHandlerWithReasonAndReturnsTransfer(): void
    {
        $client = $this->createClient();
        $uuid = (string) Uuid::v7();

        $handler = $this->createMock(ReviewQuarantineHandler::class);
        $handler->expects($this->once())
            ->method('handle')
            ->with($this->callback(fn (ReviewQuarantineCommand $cmd) => $cmd->transferUuid === $uuid && false === $cmd->approve && 'fraud detected' === $cmd->reason))
            ->willReturn($this->mockTransfer(TransferStatus::FAILED));

        $this->getContainer()->set(ReviewQuarantineHandler::class, $handler);

        $client->request(
            'POST',
            "/api/transfers/{$uuid}/deny",
            server: $this->apiHeaders(),
            content: json_encode(['reason' => 'fraud detected']),
        );

        $this->assertResponseIsSuccessful();
    }

    public function testDenyWithBlankReasonReturnsValidationError(): void
    {
        $client = $this->createClient();

        $client->request(
            'POST',
            '/api/transfers/'.Uuid::v7().'/deny',
            server: $this->apiHeaders(),
            content: json_encode(['reason' => '']),
        );

        $this->assertResponseStatusCodeSame(422);
    }

    public function testDenyWithMissingReasonReturnsValidationError(): void
    {
        $client = $this->createClient();

        $client->request(
            'POST',
            '/api/transfers/'.Uuid::v7().'/deny',
            server: $this->apiHeaders(),
            content: json_encode([]),
        );

        $this->assertResponseStatusCodeSame(422);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testApproveWhenNotQuarantinedReturns409(): void
    {
        $client = $this->createClient();
        $uuid = (string) Uuid::v7();

        $handler = $this->createMock(ReviewQuarantineHandler::class);
        $handler->method('handle')->willThrowException(new TransferNotQuarantinedException($uuid));
        $this->getContainer()->set(ReviewQuarantineHandler::class, $handler);

        $client->request('POST', "/api/transfers/{$uuid}/approve", server: $this->apiHeaders());

        $this->assertResponseStatusCodeSame(409);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testApproveWhenAlreadyReviewedReturns409(): void
    {
        $client = $this->createClient();
        $uuid = (string) Uuid::v7();

        $handler = $this->createMock(ReviewQuarantineHandler::class);
        $handler->method('handle')->willThrowException(new QuarantineAlreadyReviewedException());
        $this->getContainer()->set(ReviewQuarantineHandler::class, $handler);

        $client->request('POST', "/api/transfers/{$uuid}/approve", server: $this->apiHeaders());

        $this->assertResponseStatusCodeSame(409);
    }

    public function testEndpointsRequireApiKey(): void
    {
        $client = $this->createClient();

        $client->request('POST', '/api/transfers/'.Uuid::v7().'/approve');
        $this->assertResponseStatusCodeSame(401);

        $client->request('POST', '/api/transfers/'.Uuid::v7().'/deny');
        $this->assertResponseStatusCodeSame(401);
    }

    private function apiHeaders(): array
    {
        return [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_API_KEY' => $this->getContainer()->getParameter('app.api_key'),
        ];
    }

    #[AllowMockObjectsWithoutExpectations]
    private function mockTransfer(TransferStatus $status = TransferStatus::COMPLETED): Transfer
    {
        return $this->createConfiguredMock(Transfer::class, [
            'getId' => 1,
            'getStatus' => $status,
            'getCurrency' => Currency::EUR,
            'getCreatedAt' => new DateTimeImmutable(),
            'getCompletedAt' => new DateTimeImmutable(),
        ]);
    }
}
