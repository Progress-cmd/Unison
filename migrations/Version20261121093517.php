<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261121093517 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE announcement (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(120) NOT NULL, content LONGTEXT NOT NULL, kind VARCHAR(20) NOT NULL, active TINYINT NOT NULL, created_at DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE announcement_view (id INT AUTO_INCREMENT NOT NULL, seen_at DATETIME NOT NULL, announcement_id INT NOT NULL, user_id INT NOT NULL, UNIQUE INDEX UNIQ_D2CCA3D2913AEA17A76ED395 (announcement_id, user_id), INDEX IDX_D2CCA3D2913AEA17 (announcement_id), INDEX IDX_D2CCA3D2A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE announcement_view ADD CONSTRAINT FK_D2CCA3D2913AEA17 FOREIGN KEY (announcement_id) REFERENCES announcement (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE announcement_view ADD CONSTRAINT FK_D2CCA3D2A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE announcement_view DROP FOREIGN KEY FK_D2CCA3D2913AEA17');
        $this->addSql('ALTER TABLE announcement_view DROP FOREIGN KEY FK_D2CCA3D2A76ED395');
        $this->addSql('DROP TABLE announcement');
        $this->addSql('DROP TABLE announcement_view');
    }
}
