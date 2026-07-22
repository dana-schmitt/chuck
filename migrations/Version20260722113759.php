<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260722113759 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE joke_explanation (id INT AUTO_INCREMENT NOT NULL, joke_id INT NOT NULL, locale VARCHAR(5) NOT NULL, explanation LONGTEXT NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_4A8AE09630122C15 (joke_id), UNIQUE INDEX UNIQ_JOKE_EXPLANATION_JOKE_LOCALE (joke_id, locale), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE joke_explanation ADD CONSTRAINT FK_4A8AE09630122C15 FOREIGN KEY (joke_id) REFERENCES joke (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE joke_explanation DROP FOREIGN KEY FK_4A8AE09630122C15');
        $this->addSql('DROP TABLE joke_explanation');
    }
}
