<?php

declare(strict_types=1);

namespace App\Transfer\Application\MessageHandler;

use App\Transfer\Domain\Event\TransferFailed;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class TransferFailedHandler
{
    public function __invoke(TransferFailed $event): void
    {
    }
}
