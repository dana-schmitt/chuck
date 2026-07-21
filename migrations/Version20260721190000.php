<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260721190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add joke_of_the_day table for the daily featured joke.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE joke_of_the_day (id INT AUTO_INCREMENT NOT NULL, joke_id INT NOT NULL, date DATE NOT NULL COMMENT \'(DC2Type:date_immutable)\', INDEX IDX_3D687F3730122C15 (joke_id), UNIQUE INDEX UNIQ_JOKE_OF_THE_DAY_DATE (date), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE joke_of_the_day ADD CONSTRAINT FK_3D687F3730122C15 FOREIGN KEY (joke_id) REFERENCES joke (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE joke_of_the_day DROP FOREIGN KEY FK_3D687F3730122C15');
        $this->addSql('DROP TABLE joke_of_the_day');
    }
}
