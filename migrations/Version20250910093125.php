<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250910093125 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE dir (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, user_id INTEGER NOT NULL, name VARCHAR(255) NOT NULL, summary CLOB DEFAULT NULL, CONSTRAINT FK_BAAB7A10A76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_BAAB7A10A76ED395 ON dir (user_id)');
        $this->addSql('CREATE TABLE file (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, dir_id INTEGER DEFAULT NULL, user_id INTEGER DEFAULT NULL, name VARCHAR(255) NOT NULL, path VARCHAR(255) NOT NULL, CONSTRAINT FK_8C9F3610EEB38DE6 FOREIGN KEY (dir_id) REFERENCES dir (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_8C9F3610A76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_8C9F3610EEB38DE6 ON file (dir_id)');
        $this->addSql('CREATE INDEX IDX_8C9F3610A76ED395 ON file (user_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE dir');
        $this->addSql('DROP TABLE file');
    }
}
