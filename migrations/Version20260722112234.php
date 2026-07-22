<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260722112234 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add moderation_result table for AI submission moderation.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE moderation_result (id INT AUTO_INCREMENT NOT NULL, joke_id INT NOT NULL, duplicate_of_id INT DEFAULT NULL, recommendation VARCHAR(20) NOT NULL, confidence DOUBLE PRECISION NOT NULL, reasons JSON NOT NULL, flags JSON NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_E4FBCCA92CC33300 (duplicate_of_id), UNIQUE INDEX UNIQ_MODERATION_RESULT_JOKE (joke_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE moderation_result ADD CONSTRAINT FK_E4FBCCA930122C15 FOREIGN KEY (joke_id) REFERENCES joke (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE moderation_result ADD CONSTRAINT FK_E4FBCCA92CC33300 FOREIGN KEY (duplicate_of_id) REFERENCES joke (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE moderation_result DROP FOREIGN KEY FK_E4FBCCA930122C15');
        $this->addSql('ALTER TABLE moderation_result DROP FOREIGN KEY FK_E4FBCCA92CC33300');
        $this->addSql('DROP TABLE moderation_result');
    }
}
