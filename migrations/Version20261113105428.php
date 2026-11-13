<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261113105428 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE favorite_track (id INT AUTO_INCREMENT NOT NULL, added_at DATETIME NOT NULL, user_id INT NOT NULL, track_id INT NOT NULL, UNIQUE INDEX UNIQ_606BD382A76ED3955ED23C43 (user_id, track_id), INDEX IDX_606BD382A76ED395 (user_id), INDEX IDX_606BD3825ED23C43 (track_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE favorite_track ADD CONSTRAINT FK_606BD382A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE favorite_track ADD CONSTRAINT FK_606BD3825ED23C43 FOREIGN KEY (track_id) REFERENCES track (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE favorite_track DROP FOREIGN KEY FK_606BD382A76ED395');
        $this->addSql('ALTER TABLE favorite_track DROP FOREIGN KEY FK_606BD3825ED23C43');
        $this->addSql('DROP TABLE favorite_track');
    }
}
