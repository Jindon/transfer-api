<?php

declare(strict_types=1);

namespace App\Transfer\Presentation\Http\Api\Controller;

use App\Shared\Money\Money;
use App\Transfer\Application\Command\TransferCommand;
use App\Transfer\Application\Handler\TransferHandler;
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
    ) {
    }

    #[Route('/api/transfers', name: 'create_transfer', methods: ['POST'])]
    public function create(
        Request $request,
        #[MapRequestPayload] TransferRequest $transferRequest,
    ): JsonResponse {
        // Todo: handle idempotency with header
        $request->headers->get('Idempotency-Key');

        $transferCommand = new TransferCommand(
            sourceAccountUuid: $transferRequest->sourceAccountUuid,
            destinationAccountUuid: $transferRequest->destinationAccountUuid,
            amount: Money::make($transferRequest->amount, $transferRequest->currency),
        );

        $transfer = $this->transferHandler->handle($transferCommand);

        return $this->json(TransferResponse::fromEntity($transfer)->toArray());
    }
}
