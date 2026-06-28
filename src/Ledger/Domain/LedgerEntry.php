<?php

declare(strict_types=1);

namespace App\Ledger\Domain;

use App\Account\Domain\Account;
use App\Ledger\Domain\Enum\LedgerDirection;
use App\Shared\Money\Money;
use App\Transfer\Domain\Transfer;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'ledger_entries')]
class LedgerEntry
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id;

    #[ORM\ManyToOne(targetEntity: Transfer::class)]
    #[ORM\JoinColumn(name: 'transfer_id', referencedColumnName: 'id', nullable: true)]
    private ?Transfer $transfer = null;

    #[ORM\ManyToOne(targetEntity: Account::class)]
    #[ORM\JoinColumn(name: 'account_id', referencedColumnName: 'id', nullable: false)]
    private Account $account;

    #[ORM\Column(enumType: LedgerDirection::class)]
    private LedgerDirection $ledgerDirection;

    #[ORM\Column(type: Types::INTEGER)]
    private int|string $amount;

    #[ORM\Column(length: 3)]
    private string $currency;

    #[ORM\Column]
    private DateTimeImmutable $createdAt;

    public static function make(
        Transfer $transfer,
        Account $account,
        LedgerDirection $ledgerDirection,
        Money $amount,
        DateTimeImmutable $createdAt,
    ): LedgerEntry {
        $ledgerEntry = new LedgerEntry();
        $ledgerEntry->setTransfer($transfer);
        $ledgerEntry->setAccount($account);
        $ledgerEntry->setLedgerDirection($ledgerDirection);
        $ledgerEntry->setAmount($amount->getAmount());
        $ledgerEntry->setCurrency($amount->getCurrencyCode());
        $ledgerEntry->setCreatedAt($createdAt);

        return $ledgerEntry;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): void
    {
        $this->id = $id;
    }

    public function getTransfer(): ?Transfer
    {
        return $this->transfer;
    }

    public function setTransfer(?Transfer $transfer): void
    {
        $this->transfer = $transfer;
    }

    public function getAccount(): Account
    {
        return $this->account;
    }

    public function setAccount(Account $account): void
    {
        $this->account = $account;
    }

    public function getLedgerDirection(): LedgerDirection
    {
        return $this->ledgerDirection;
    }

    public function setLedgerDirection(LedgerDirection $ledgerDirection): void
    {
        $this->ledgerDirection = $ledgerDirection;
    }

    public function getAmount(): int|string
    {
        return $this->amount;
    }

    public function setAmount(int|string $amount): void
    {
        $this->amount = $amount;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function setCurrency(string $currency): void
    {
        $this->currency = $currency;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTimeImmutable $createdAt): void
    {
        $this->createdAt = $createdAt;
    }
}
