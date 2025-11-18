<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251025204842 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TEMPORARY TABLE __temp__announcements AS SELECT id, message, timestamp FROM announcements');
        $this->addSql('DROP TABLE announcements');
        $this->addSql('CREATE TABLE announcements (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, message VARCHAR(255) NOT NULL, timestamp DATETIME NOT NULL)');
        $this->addSql('INSERT INTO announcements (id, message, timestamp) SELECT id, message, timestamp FROM __temp__announcements');
        $this->addSql('DROP TABLE __temp__announcements');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE announcements ADD COLUMN author VARCHAR(255) NOT NULL');
    }
}
