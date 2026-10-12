<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261012100241 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE game (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(100) NOT NULL, slug VARCHAR(100) NOT NULL, ai_context LONGTEXT DEFAULT NULL, UNIQUE INDEX UNIQ_232B318C5E237E06 (name), UNIQUE INDEX UNIQ_232B318C989D9B62 (slug), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 (queue_name, available_at, delivered_at, id), PRIMARY KEY (id))');
        $this->addSql('ALTER TABLE screenshot ADD ai_tagged_at DATETIME DEFAULT NULL, ADD game_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE screenshot ADD CONSTRAINT FK_58991E41E48FD905 FOREIGN KEY (game_id) REFERENCES game (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_58991E41E48FD905 ON screenshot (game_id)');
        $this->addSql('INSERT INTO game (name, slug, ai_context) VALUES (:name, :slug, :context)', ['name' => 'Star Citizen', 'slug' => 'star-citizen', 'context' => 'Univers spatial (Cloud Imperium Games). Vue cockpit, à pied (FPS), en véhicule ou en EVA.
Constructeurs et vaisseaux : Aegis (Avenger, Gladius, Sabre, Eclipse, Vanguard, Hammerhead, Retaliator, Redeemer, Reclaimer, Idris, Javelin) ; Anvil (Arrow, Hornet, Hurricane, Valkyrie, Carrack, Pisces, Terrapin, Paladin, Asgard, Liberator) ; Drake (Cutlass, Buccaneer, Caterpillar, Corsair, Vulture, Herald, Kraken, Ironclad, Golem, Clipper) ; MISC (Freelancer, Prospector, Starfarer, Reliant, Hull A à E, Odyssey, Fortune, Starlancer) ; Crusader (Mercury Star Runner, Hercules C2/M2/A2, Ares, Spirit, Intrepid) ; Origin (100i, 300i, 400i, 600i, 890 Jump, 85X, X1) ; RSI (Aurora, Constellation, Zeus, Polaris, Perseus, Scorpius, Galaxy, Apollo) ; Consolidated Outland (Mustang, Nomad, Pioneer, HoverQuad) ; Argo (MOLE, RAFT, MPUV, SRV) ; Esperia (Prowler, Talon, Glaive, Blade) ; Gatac (Syulen, Railen) ; Aopoa (Nox, Khartu-al, San\'tok.yāi) ; Banu (Defender, Merchantman) ; Mirai (Fury, Pulse, Guardian).
Véhicules au sol : Tumbril (Cyclone, Nova, Storm), Greycat (ROC, PTV), Origin G12, Anvil Ballista, Centurion.
Systèmes et lieux : Stanton (Hurston et Lorville, microTech et New Babbage, ArcCorp et Area18, Crusader et Orison), Pyro (Ruin Station, Checkmate, Orbituary), Nyx (Levski) ; stations orbitales, Port Tressler, Everus Harbor, Baijini Point, Seraphim Station, aires de repos, Jump Points, champs d\'astéroïdes, Aaron Halo.
Activités typiques : combat spatial, minage, salvage, transport de fret, exploration, bunkers, Xenothreat, Jumptown, Siege of Orison.
Style : science-fiction réaliste, HUD holographique, grands panoramas planétaires, cockpits détaillés.']);
        $this->addSql('INSERT INTO game (name, slug, ai_context) VALUES (:name, :slug, :context)', ['name' => 'Arc Raiders', 'slug' => 'arc-raiders', 'context' => 'Extraction PvPvE à la troisième personne (Embark Studios, sortie en 2025). Les joueurs (Raiders) fouillent la surface d\'une Terre post-apocalyptique envahie par les machines ARC, puis rentrent à Speranza, la cité souterraine.
Machines ARC (à vérifier et compléter) : Tick, Pop, Fireball, Snitch, Wasp, Hornet, Rocketeer, Leaper, Surveyor, Sentinel, Bastion, Bombardier, Spotter, Queen, Matriarch.
Lieux : Speranza, Dam Battlegrounds, Buried City, Spaceport, Blue Gate, Stella Montis.
Style : rétro-futurisme années 70-80, couleurs saturées, végétation qui recouvre les ruines, machines volantes ou à pattes au design mécanique.']);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE game');
        $this->addSql('DROP TABLE messenger_messages');
        $this->addSql('ALTER TABLE screenshot DROP FOREIGN KEY FK_58991E41E48FD905');
        $this->addSql('DROP INDEX IDX_58991E41E48FD905 ON screenshot');
        $this->addSql('ALTER TABLE screenshot DROP ai_tagged_at, DROP game_id');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
