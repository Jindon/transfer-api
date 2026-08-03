<?php

declare(strict_types=1);

namespace App\Txm\Presentation\Http\Api\Controller;

use App\Transfer\Presentation\Http\Api\Response\TransferResponse;
use App\Txm\Application\Command\ReviewQuarantineCommand;
use App\Txm\Application\Handler\ReviewQuarantineHandler;
use App\Txm\Presentation\Http\Api\Request\DenyRequest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

final class QuarantineController extends AbstractController
{
    public function __construct(
        private readonly ReviewQuarantineHandler $reviewHandler,
    ) {
    }

    #[Route('/api/transfers/{uuid}/approve', name: 'approve_quarantined_transfer', methods: ['POST'])]
    public function approve(string $uuid): JsonResponse
    {
        $transfer = $this->reviewHandler->handle(new ReviewQuarantineCommand($uuid, approve: true));

        return $this->json(TransferResponse::fromEntity($transfer));
    }

    #[Route('/api/transfers/{uuid}/deny', name: 'deny_quarantined_transfer', methods: ['POST'])]
    public function deny(string $uuid, #[MapRequestPayload] DenyRequest $request): JsonResponse
    {
        $transfer = $this->reviewHandler->handle(new ReviewQuarantineCommand($uuid, approve: false, reason: $request->reason));

        return $this->json(TransferResponse::fromEntity($transfer));
    }
}
