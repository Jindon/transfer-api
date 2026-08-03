<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260706055922 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add quarantined transaction table';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE quarantined_transfers (id INT AUTO_INCREMENT NOT NULL, status VARCHAR(255) NOT NULL, reason VARCHAR(255) DEFAULT NULL, quarantined_at DATETIME NOT NULL, reviewed_at DATETIME DEFAULT NULL, transfer_id INT NOT NULL, UNIQUE INDEX UNIQ_AB77A2A0537048AF (transfer_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE quarantined_transfers ADD CONSTRAINT FK_AB77A2A0537048AF FOREIGN KEY (transfer_id) REFERENCES transfers (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE quarantined_transfers DROP FOREIGN KEY FK_AB77A2A0537048AF');
        $this->addSql('DROP TABLE quarantined_transfers');
    }
}
