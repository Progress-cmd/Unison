<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261117135932 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE error_report (id INT AUTO_INCREMENT NOT NULL, source VARCHAR(20) NOT NULL, message LONGTEXT NOT NULL, context VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, resolved_at DATETIME DEFAULT NULL, import_job_id INT DEFAULT NULL, INDEX IDX_5209F40177D1F4B1 (import_job_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE error_report ADD CONSTRAINT FK_5209F40177D1F4B1 FOREIGN KEY (import_job_id) REFERENCES import_job (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE error_report DROP FOREIGN KEY FK_5209F40177D1F4B1');
        $this->addSql('DROP TABLE error_report');
    }
}
