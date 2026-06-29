<?php

namespace App\Account\Presentation\Console\Command;

use App\Account\Domain\Account;
use App\Ledger\Domain\LedgerEntry;
use App\Shared\Money\Currency;
use App\Shared\Money\Money;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

#[AsCommand(
    name: 'app:seed:accounts',
    description: 'Seed initial accounts',
)]
class SeedAccountsCommand extends Command
{
    private const array ACCOUNTS = [
        ['currency' => Currency::EUR, 'balance' => 10_000_00],
        ['currency' => Currency::EUR, 'balance' => 5_000_00],
        ['currency' => Currency::EUR, 'balance' => 2_500_00],
        ['currency' => Currency::EUR, 'balance' => 20_000_00],
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $now = new DateTimeImmutable();

        $this->entityManager->beginTransaction();
        try {
            foreach (self::ACCOUNTS as ['currency' => $currency, 'balance' => $balance]) {
                $account = new Account($currency, $balance);
                $account->setCreatedAt($now);
                $account->setUpdatedAt($now);
                $this->entityManager->persist($account);

                $this->entityManager->persist(
                    LedgerEntry::makeOpening($account, Money::make($balance, $currency), $now)
                );
            }

            $this->entityManager->flush();
            $this->entityManager->commit();
        } catch (Throwable $e) {
            $this->entityManager->rollback();
            $io->warning('Failed to seed accounts:: '.$e->getMessage());

            return Command::FAILURE;
        }

        $io->success(count(self::ACCOUNTS).' accounts seeded.');

        return Command::SUCCESS;
    }
}
