<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260721160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add submitted_by_id and approved columns for user-submitted joke moderation.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE joke ADD submitted_by_id INT DEFAULT NULL, ADD approved TINYINT(1) NOT NULL DEFAULT 1, CHANGE categories categories JSON NOT NULL');
        $this->addSql('ALTER TABLE joke ADD CONSTRAINT FK_8D8563DD79F7D87D FOREIGN KEY (submitted_by_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_8D8563DD79F7D87D ON joke (submitted_by_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE joke DROP FOREIGN KEY FK_8D8563DD79F7D87D');
        $this->addSql('DROP INDEX IDX_8D8563DD79F7D87D ON joke');
        $this->addSql('ALTER TABLE joke DROP submitted_by_id, DROP approved, CHANGE categories categories JSON DEFAULT \'json_array()\' NOT NULL');
    }
}
