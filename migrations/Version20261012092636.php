<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261012092636 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE album (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, slug VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, published TINYINT NOT NULL, view_count INT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, created_by VARCHAR(255) DEFAULT NULL, updated_by VARCHAR(255) DEFAULT NULL, cover_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_39986E43989D9B62 (slug), INDEX IDX_39986E43922726E9 (cover_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE daily_statistic (id INT AUTO_INCREMENT NOT NULL, day DATE NOT NULL, views INT NOT NULL, downloads INT NOT NULL, screenshot_id INT NOT NULL, INDEX IDX_E59F5F86E5A02990 (day), UNIQUE INDEX UNIQ_E59F5F864A8B36A0E5A02990 (screenshot_id, day), INDEX IDX_E59F5F864A8B36A0 (screenshot_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE screenshot (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, slug VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, taken_at DATETIME DEFAULT NULL, published TINYINT NOT NULL, view_count INT NOT NULL, download_count INT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, created_by VARCHAR(255) DEFAULT NULL, updated_by VARCHAR(255) DEFAULT NULL, file_path VARCHAR(255) NOT NULL, file_original_name VARCHAR(255) NOT NULL, file_mime_type VARCHAR(100) NOT NULL, file_size INT NOT NULL, file_width INT NOT NULL, file_height INT NOT NULL, UNIQUE INDEX UNIQ_58991E41989D9B62 (slug), INDEX IDX_58991E41683C6017 (published), INDEX IDX_58991E41C48F0688 (taken_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE screenshot_album (screenshot_id INT NOT NULL, album_id INT NOT NULL, INDEX IDX_A93A3D34A8B36A0 (screenshot_id), INDEX IDX_A93A3D31137ABCF (album_id), PRIMARY KEY (screenshot_id, album_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE screenshot_tag (screenshot_id INT NOT NULL, tag_id INT NOT NULL, INDEX IDX_BEC1F7CE4A8B36A0 (screenshot_id), INDEX IDX_BEC1F7CEBAD26311 (tag_id), PRIMARY KEY (screenshot_id, tag_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE tag (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(100) NOT NULL, slug VARCHAR(100) NOT NULL, UNIQUE INDEX UNIQ_389B7835E237E06 (name), UNIQUE INDEX UNIQ_389B783989D9B62 (slug), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE album ADD CONSTRAINT FK_39986E43922726E9 FOREIGN KEY (cover_id) REFERENCES screenshot (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE daily_statistic ADD CONSTRAINT FK_E59F5F864A8B36A0 FOREIGN KEY (screenshot_id) REFERENCES screenshot (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE screenshot_album ADD CONSTRAINT FK_A93A3D34A8B36A0 FOREIGN KEY (screenshot_id) REFERENCES screenshot (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE screenshot_album ADD CONSTRAINT FK_A93A3D31137ABCF FOREIGN KEY (album_id) REFERENCES album (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE screenshot_tag ADD CONSTRAINT FK_BEC1F7CE4A8B36A0 FOREIGN KEY (screenshot_id) REFERENCES screenshot (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE screenshot_tag ADD CONSTRAINT FK_BEC1F7CEBAD26311 FOREIGN KEY (tag_id) REFERENCES tag (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE album DROP FOREIGN KEY FK_39986E43922726E9');
        $this->addSql('ALTER TABLE daily_statistic DROP FOREIGN KEY FK_E59F5F864A8B36A0');
        $this->addSql('ALTER TABLE screenshot_album DROP FOREIGN KEY FK_A93A3D34A8B36A0');
        $this->addSql('ALTER TABLE screenshot_album DROP FOREIGN KEY FK_A93A3D31137ABCF');
        $this->addSql('ALTER TABLE screenshot_tag DROP FOREIGN KEY FK_BEC1F7CE4A8B36A0');
        $this->addSql('ALTER TABLE screenshot_tag DROP FOREIGN KEY FK_BEC1F7CEBAD26311');
        $this->addSql('DROP TABLE album');
        $this->addSql('DROP TABLE daily_statistic');
        $this->addSql('DROP TABLE screenshot');
        $this->addSql('DROP TABLE screenshot_album');
        $this->addSql('DROP TABLE screenshot_tag');
        $this->addSql('DROP TABLE tag');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
