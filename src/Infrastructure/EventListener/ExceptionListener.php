<?php

declare(strict_types=1);

namespace App\Infrastructure\EventListener;

use App\Account\Domain\Exception\AccountNotFoundException;
use App\Account\Domain\Exception\InsufficientFundException;
use App\Idempotency\Domain\Exception\MissingIdempotencyKeyException;
use App\Idempotency\Domain\Exception\RequestHashMismatchException;
use App\Transfer\Domain\Exception\TransferAlreadyProcessedException;
use App\Transfer\Domain\Exception\TransferConflictException;
use App\Transfer\Domain\Exception\TransferNotFoundException;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(event: KernelEvents::EXCEPTION, priority: 10)]
class ExceptionListener
{
    public function __invoke(ExceptionEvent $event): void
    {
        $e = $event->getThrowable();

        [$status, $type, $detail] = match (true) {
            $e instanceof InsufficientFundException => [422, 'insufficient-funds', $e->getMessage()],
            $e instanceof TransferNotFoundException,
            $e instanceof AccountNotFoundException => [404, 'not-found', $e->getMessage()],
            $e instanceof TransferAlreadyProcessedException => [409, 'already-processed', $e->getMessage()],
            $e instanceof RequestHashMismatchException => [409, 'request-hash-mismatch', $e->getMessage()],
            $e instanceof MissingIdempotencyKeyException => [400, 'missing-idempotency-key-header', $e->getMessage()],
            $e instanceof TransferConflictException => [409, 'transfer-conflict', $e->getMessage()],
            default => [null, null, null],
        };

        if (null === $status) {
            return;
        }

        $event->setResponse(new JsonResponse(
            ['type' => $type, 'status' => $status, 'detail' => $detail],
            $status,
            ['Content-Type' => 'application/problem+json'],
        ));
    }
}
