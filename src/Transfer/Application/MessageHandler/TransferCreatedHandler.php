<?php

declare(strict_types=1);

namespace App\Transfer\Application\MessageHandler;

use App\Transfer\Domain\Event\TransferCreated;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class TransferCreatedHandler
{
    public function __invoke(TransferCreated $event): void
    {
    }
}
