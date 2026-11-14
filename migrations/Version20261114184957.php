<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261114184957 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE playback_state (id INT AUTO_INCREMENT NOT NULL, queue JSON NOT NULL, source_queue JSON NOT NULL, current_index INT NOT NULL, position INT NOT NULL, shuffle TINYINT NOT NULL, repeat_mode VARCHAR(3) NOT NULL, updated_at DATETIME NOT NULL, user_id INT NOT NULL, UNIQUE INDEX UNIQ_48B3841FA76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE playback_state ADD CONSTRAINT FK_48B3841FA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE playback_state DROP FOREIGN KEY FK_48B3841FA76ED395');
        $this->addSql('DROP TABLE playback_state');
    }
}
