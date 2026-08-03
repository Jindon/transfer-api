<?php

declare(strict_types=1);

namespace App\Tests\Txm\Unit\Infrastructure\Service;

use App\Tests\Support\Genie;
use App\Txm\Infrastructure\Service\StubTransactionMonitor;
use PHPUnit\Framework\TestCase;

final class StubTransactionMonitorTest extends TestCase
{
    public function testCheckAlwaysReturnsTrue(): void
    {
        $monitor = new StubTransactionMonitor();

        $this->assertTrue($monitor->check(Genie::makeTransfer()));
    }
}
