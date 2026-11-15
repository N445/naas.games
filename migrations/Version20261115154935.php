<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261115154935 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Hero title and subtitle are static translations: drop their admin overrides.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE homepage_settings DROP hero_titles, DROP hero_subtitles');
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE homepage_settings ADD hero_titles JSON NOT NULL DEFAULT ('[]'), ADD hero_subtitles JSON NOT NULL DEFAULT ('[]')");
    }
}
