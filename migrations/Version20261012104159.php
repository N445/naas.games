<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261012104159 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE ship (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(150) NOT NULL, manufacturer VARCHAR(150) NOT NULL, focus VARCHAR(150) DEFAULT NULL, size VARCHAR(50) DEFAULT NULL, image_url VARCHAR(255) DEFAULT NULL, embedding LONGTEXT DEFAULT NULL, rsi_id INT NOT NULL, UNIQUE INDEX UNIQ_FA30EB24967433DD (rsi_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE screenshot ADD ai_suggested_tags JSON DEFAULT NULL, ADD embedding LONGTEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE ship');
        $this->addSql('ALTER TABLE screenshot DROP ai_suggested_tags, DROP embedding');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
