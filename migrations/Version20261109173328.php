<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261109173328 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE import_job (id INT AUTO_INCREMENT NOT NULL, url VARCHAR(500) NOT NULL, status VARCHAR(20) NOT NULL, title VARCHAR(255) DEFAULT NULL, error VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, requested_by_id INT NOT NULL, INDEX IDX_6FB540784DA1E751 (requested_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE import_job ADD CONSTRAINT FK_6FB540784DA1E751 FOREIGN KEY (requested_by_id) REFERENCES users (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE import_job DROP FOREIGN KEY FK_6FB540784DA1E751');
        $this->addSql('DROP TABLE import_job');
    }
}
