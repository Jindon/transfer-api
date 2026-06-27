<?php

declare(strict_types=1);

namespace App\Account\Domain;

use App\Account\Domain\Exception\InsufficientFundException;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'accounts')]
class Account
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id;

    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $uuid;

    #[ORM\Column(length: 3)]
    private ?string $currency = null;

    #[ORM\Column(type: Types::INTEGER, options: ['unsigned' => true, 'default' => 0])]
    private int $balance = 0;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $created_at = null;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $updated_at = null;

    public function __construct(string $currencyCode, int $balance = 0)
    {
        $this->uuid = Uuid::v7();
        $this->currency = $currencyCode;
        $this->balance = $balance;
    }

    public function debit(int $amount): void
    {
        $this->assertSufficientBalance($amount);

        $this->balance = $this->balance - $amount;
        $this->updated_at = new DateTimeImmutable();
    }

    public function credit(int $amount): void
    {
        $this->balance = $this->balance + $amount;
        $this->updated_at = new DateTimeImmutable();
    }

    public function assertTransferable(int $amount): void
    {
        // Only checking balance for now, but can add more checks like account status later on
        $this->assertSufficientBalance($amount);
    }

    public function assertSufficientBalance(int $amount): void
    {
        $balance = $this->getBalance();

        if ($balance < $amount) {
            throw new InsufficientFundException((string) $this->uuid, $amount, $balance);
        }
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUuid(): Uuid
    {
        return $this->uuid;
    }

    public function getCurrency(): ?string
    {
        return $this->currency;
    }

    public function setCurrency(string $currency): static
    {
        $this->currency = $currency;

        return $this;
    }

    public function getBalance(): int
    {
        return $this->balance;
    }

    public function setBalance(int $balance): static
    {
        $this->balance = $balance;

        return $this;
    }

    public function getCreatedAt(): ?DateTimeImmutable
    {
        return $this->created_at;
    }

    public function setCreatedAt(?DateTimeImmutable $created_at): static
    {
        $this->created_at = $created_at;

        return $this;
    }

    public function getUpdatedAt(): ?DateTimeImmutable
    {
        return $this->updated_at;
    }

    public function setUpdatedAt(?DateTimeImmutable $updated_at): static
    {
        $this->updated_at = $updated_at;

        return $this;
    }
}
