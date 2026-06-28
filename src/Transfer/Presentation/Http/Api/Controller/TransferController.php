<?php

declare(strict_types=1);

namespace App\Transfer\Presentation\Http\Api\Controller;

use App\Idempotency\Domain\Exception\MissingIdempotencyKeyException;
use App\Shared\Money\Money;
use App\Transfer\Application\Command\TransferCommand;
use App\Transfer\Application\Handler\TransferHandler;
use App\Transfer\Domain\Exception\TransferNotFoundException;
use App\Transfer\Domain\Repository\TransferRepositoryInterface;
use App\Transfer\Presentation\Http\Api\Request\TransferRequest;
use App\Transfer\Presentation\Http\Api\Response\TransferResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

final class TransferController extends AbstractController
{
    public function __construct(
        private readonly TransferHandler $transferHandler,
        private readonly TransferRepositoryInterface $transferRepository,
    ) {
    }

    #[Route('/api/transfers', name: 'create_transfer', methods: ['POST'])]
    public function create(
        Request $request,
        #[MapRequestPayload] TransferRequest $transferRequest,
    ): JsonResponse {
        $idempotencyKey = $request->headers->get('Idempotency-Key')
            ?? throw new MissingIdempotencyKeyException();

        $transferCommand = new TransferCommand(
            sourceAccountUuid: $transferRequest->sourceAccountUuid,
            destinationAccountUuid: $transferRequest->destinationAccountUuid,
            amount: Money::make($transferRequest->amount, $transferRequest->currency),
            idempotencyKey: $idempotencyKey,
            requestHash: hash('sha256', $request->getContent()),
        );

        $transfer = $this->transferHandler->handle($transferCommand);

        return $this->json(TransferResponse::fromEntity($transfer));
    }

    #[Route('/api/transfers/{uuid}', name: 'show_transfer', methods: ['GET'])]
    public function show(string $uuid): JsonResponse
    {
        $transfer = $this->transferRepository->findByUuid($uuid)
            ?? throw new TransferNotFoundException($uuid);

        return $this->json(TransferResponse::fromEntity($transfer));
    }
}
