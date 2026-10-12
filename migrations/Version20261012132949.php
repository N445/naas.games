<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261012132949 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE screenshot ADD file_hash VARCHAR(40) DEFAULT NULL');
        $this->addSql('CREATE INDEX IDX_58991E4158440AD6 ON screenshot (file_hash)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX IDX_58991E4158440AD6 ON screenshot');
        $this->addSql('ALTER TABLE screenshot DROP file_hash');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
