<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261012123225 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Paramètres de la page d’accueil';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE homepage_settings (id INT AUTO_INCREMENT NOT NULL, hero_titles JSON NOT NULL, hero_subtitles JSON NOT NULL, hero_screenshot_ids JSON NOT NULL, featured_album_ids JSON NOT NULL, show_statistics TINYINT NOT NULL, show_latest TINYINT NOT NULL, latest_limit INT NOT NULL, show_albums TINYINT NOT NULL, show_games TINYINT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, created_by VARCHAR(255) DEFAULT NULL, updated_by VARCHAR(255) DEFAULT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE homepage_settings');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
