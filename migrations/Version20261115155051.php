<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261115155051 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Originaux 32:9 rattachés aux screenshots finaux, empreintes perceptuelles et doublons écartés.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE screenshot_original (id INT AUTO_INCREMENT NOT NULL, match_key VARCHAR(255) NOT NULL, taken_at DATETIME DEFAULT NULL, perceptual_hash VARCHAR(16) DEFAULT NULL, embedding LONGTEXT DEFAULT NULL, downloadable TINYINT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, created_by VARCHAR(255) DEFAULT NULL, updated_by VARCHAR(255) DEFAULT NULL, file_path VARCHAR(255) NOT NULL, file_original_name VARCHAR(255) NOT NULL, file_mime_type VARCHAR(100) NOT NULL, file_size INT NOT NULL, file_width INT NOT NULL, file_height INT NOT NULL, file_hash VARCHAR(40) DEFAULT NULL, INDEX IDX_3BE7B8CD8EDE0A77 (match_key), INDEX IDX_3BE7B8CD58440AD6 (file_hash), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE screenshot ADD match_key VARCHAR(255) DEFAULT NULL, ADD perceptual_hash VARCHAR(16) DEFAULT NULL, ADD distinct_from JSON DEFAULT NULL, ADD original_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE screenshot ADD CONSTRAINT FK_58991E41108B7592 FOREIGN KEY (original_id) REFERENCES screenshot_original (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_58991E418EDE0A77 ON screenshot (match_key)');
        $this->addSql('CREATE INDEX IDX_58991E41108B7592 ON screenshot (original_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE screenshot DROP FOREIGN KEY FK_58991E41108B7592');
        $this->addSql('DROP INDEX IDX_58991E418EDE0A77 ON screenshot');
        $this->addSql('DROP INDEX IDX_58991E41108B7592 ON screenshot');
        $this->addSql('ALTER TABLE screenshot DROP match_key, DROP perceptual_hash, DROP distinct_from, DROP original_id');
        $this->addSql('DROP TABLE screenshot_original');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
