<?php

declare(strict_types=1);

namespace App\Transfer\Application\MessageHandler;

use App\Transfer\Domain\Event\TransferCompleted;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class TransferCompletedHandler
{
    public function __invoke(TransferCompleted $event): void
    {
    }
}
