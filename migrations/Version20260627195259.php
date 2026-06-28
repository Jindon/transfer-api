<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260627195259 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE ledger_entries (id INT AUTO_INCREMENT NOT NULL, ledger_direction VARCHAR(255) NOT NULL, amount INT NOT NULL, currency VARCHAR(3) NOT NULL, created_at DATETIME NOT NULL, transfer_id INT DEFAULT NULL, account_id INT NOT NULL, INDEX IDX_E3FD73F4537048AF (transfer_id), INDEX IDX_E3FD73F49B6B5FBA (account_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE ledger_entries ADD CONSTRAINT FK_E3FD73F4537048AF FOREIGN KEY (transfer_id) REFERENCES transfers (id)');
        $this->addSql('ALTER TABLE ledger_entries ADD CONSTRAINT FK_E3FD73F49B6B5FBA FOREIGN KEY (account_id) REFERENCES accounts (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE ledger_entries DROP FOREIGN KEY FK_E3FD73F4537048AF');
        $this->addSql('ALTER TABLE ledger_entries DROP FOREIGN KEY FK_E3FD73F49B6B5FBA');
        $this->addSql('DROP TABLE ledger_entries');
    }
}
