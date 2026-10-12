<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261012112010 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX UNIQ_389B7835E237E06 ON tag');
        $this->addSql('ALTER TABLE tag ADD parent_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE tag ADD CONSTRAINT FK_389B783727ACA70 FOREIGN KEY (parent_id) REFERENCES tag (id) ON DELETE SET NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_389B783727ACA705E237E06 ON tag (parent_id, name)');
        $this->addSql('CREATE INDEX IDX_389B783727ACA70 ON tag (parent_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE tag DROP FOREIGN KEY FK_389B783727ACA70');
        $this->addSql('DROP INDEX UNIQ_389B783727ACA705E237E06 ON tag');
        $this->addSql('DROP INDEX IDX_389B783727ACA70 ON tag');
        $this->addSql('ALTER TABLE tag DROP parent_id');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_389B7835E237E06 ON tag (name)');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
