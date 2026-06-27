<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260626115330 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE transfers (id INT AUTO_INCREMENT NOT NULL, uuid BINARY(16) NOT NULL, currency VARCHAR(3) NOT NULL, amount INT UNSIGNED DEFAULT 0 NOT NULL, status VARCHAR(255) NOT NULL, reference VARCHAR(255) DEFAULT NULL, failure_reason VARCHAR(255) DEFAULT NULL, created_at DATETIME DEFAULT NULL, completed_at DATETIME DEFAULT NULL, source_account_id INT NOT NULL, destination_account_id INT NOT NULL, UNIQUE INDEX UNIQ_802A3918D17F50A6 (uuid), INDEX IDX_802A3918E7DF2E9E (source_account_id), INDEX IDX_802A3918C652C408 (destination_account_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE transfers ADD CONSTRAINT FK_802A3918E7DF2E9E FOREIGN KEY (source_account_id) REFERENCES accounts (id)');
        $this->addSql('ALTER TABLE transfers ADD CONSTRAINT FK_802A3918C652C408 FOREIGN KEY (destination_account_id) REFERENCES accounts (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE transfers DROP FOREIGN KEY FK_802A3918E7DF2E9E');
        $this->addSql('ALTER TABLE transfers DROP FOREIGN KEY FK_802A3918C652C408');
        $this->addSql('DROP TABLE transfers');
    }
}
