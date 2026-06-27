<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260627153056 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX UNIQ_A0694A878A90ABA9 ON idempotency_requests');
        $this->addSql('ALTER TABLE idempotency_requests CHANGE `key` idempotency_key VARCHAR(255) NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_A0694A877FD1C147 ON idempotency_requests (idempotency_key)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX UNIQ_A0694A877FD1C147 ON idempotency_requests');
        $this->addSql('ALTER TABLE idempotency_requests CHANGE idempotency_key `key` VARCHAR(255) NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_A0694A878A90ABA9 ON idempotency_requests (`key`)');
    }
}
