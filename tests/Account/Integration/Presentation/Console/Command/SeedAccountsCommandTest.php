<?php

declare(strict_types=1);

namespace App\Tests\Account\Integration\Presentation\Console\Command;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

class SeedAccountsCommandTest extends KernelTestCase
{
    public function testExecute(): void
    {
        self::bootKernel();
        $application = new Application(self::$kernel);

        $command = $application->find('app:seed:accounts');
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);

        $commandTester->assertCommandIsSuccessful();

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('4 accounts seeded.', $output);

        $connection = self::getContainer()->get(Connection::class);

        $rowCount = $connection->fetchOne('SELECT COUNT(*) FROM accounts');
        $this->assertEquals(4, (int) $rowCount);

        $ledgerCount = $connection->fetchOne('SELECT COUNT(*) FROM ledger_entries WHERE transfer_id IS NULL');
        $this->assertEquals(4, (int) $ledgerCount);
    }
}
