<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261012120729 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE link (id INT AUTO_INCREMENT NOT NULL, icon VARCHAR(100) NOT NULL, title VARCHAR(255) NOT NULL, subtitle VARCHAR(255) DEFAULT NULL, color VARCHAR(7) NOT NULL, url VARCHAR(255) NOT NULL, position INT NOT NULL, enabled TINYINT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, created_by VARCHAR(255) DEFAULT NULL, updated_by VARCHAR(255) DEFAULT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('INSERT INTO link (icon, title, subtitle, color, url, position, enabled, created_at, updated_at) VALUES (:icon, :title, :subtitle, :color, :url, :position, :enabled, NOW(), NOW())', ['icon' => 'fa7-brands:bluesky', 'title' => 'Bluesky', 'subtitle' => '@naas.bsky.social', 'color' => '#1185fe', 'url' => 'https://bsky.app/profile/naas.bsky.social', 'position' => 1, 'enabled' => 1]);
        $this->addSql('INSERT INTO link (icon, title, subtitle, color, url, position, enabled, created_at, updated_at) VALUES (:icon, :title, :subtitle, :color, :url, :position, :enabled, NOW(), NOW())', ['icon' => 'fa7-brands:x-twitter', 'title' => 'X', 'subtitle' => '@NaasOff', 'color' => '#e7e9ea', 'url' => 'https://x.com/NaasOff', 'position' => 2, 'enabled' => 1]);
        $this->addSql('INSERT INTO link (icon, title, subtitle, color, url, position, enabled, created_at, updated_at) VALUES (:icon, :title, :subtitle, :color, :url, :position, :enabled, NOW(), NOW())', ['icon' => 'fa7-brands:flickr', 'title' => 'Flickr', 'subtitle' => 'Mes screenshots en haute définition', 'color' => '#ff0084', 'url' => 'https://www.flickr.com/people/199420432@N07/', 'position' => 3, 'enabled' => 1]);
        $this->addSql('INSERT INTO link (icon, title, subtitle, color, url, position, enabled, created_at, updated_at) VALUES (:icon, :title, :subtitle, :color, :url, :position, :enabled, NOW(), NOW())', ['icon' => 'fa7-solid:users', 'title' => 'Community Hub', 'subtitle' => 'Profil Star Citizen', 'color' => '#00a8e1', 'url' => 'https://robertsspaceindustries.com/community-hub/user/Naas', 'position' => 4, 'enabled' => 1]);
        $this->addSql('INSERT INTO link (icon, title, subtitle, color, url, position, enabled, created_at, updated_at) VALUES (:icon, :title, :subtitle, :color, :url, :position, :enabled, NOW(), NOW())', ['icon' => 'fa7-brands:instagram', 'title' => 'Instagram', 'subtitle' => null, 'color' => '#e1306c', 'url' => 'https://www.instagram.com', 'position' => 5, 'enabled' => 0]);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE link');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
