<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260721200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add joke_comment and comment_reaction tables for comments and emoji reactions.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE comment_reaction (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, comment_id INT NOT NULL, emoji VARCHAR(8) NOT NULL, INDEX IDX_B99364F1A76ED395 (user_id), INDEX IDX_B99364F1F8697D13 (comment_id), UNIQUE INDEX UNIQ_USER_COMMENT_EMOJI (user_id, comment_id, emoji), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE joke_comment (id INT AUTO_INCREMENT NOT NULL, joke_id INT NOT NULL, author_id INT NOT NULL, body LONGTEXT NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_C07177EC30122C15 (joke_id), INDEX IDX_C07177ECF675F31B (author_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE comment_reaction ADD CONSTRAINT FK_B99364F1A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE comment_reaction ADD CONSTRAINT FK_B99364F1F8697D13 FOREIGN KEY (comment_id) REFERENCES joke_comment (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE joke_comment ADD CONSTRAINT FK_C07177EC30122C15 FOREIGN KEY (joke_id) REFERENCES joke (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE joke_comment ADD CONSTRAINT FK_C07177ECF675F31B FOREIGN KEY (author_id) REFERENCES user (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE comment_reaction DROP FOREIGN KEY FK_B99364F1A76ED395');
        $this->addSql('ALTER TABLE comment_reaction DROP FOREIGN KEY FK_B99364F1F8697D13');
        $this->addSql('ALTER TABLE joke_comment DROP FOREIGN KEY FK_C07177EC30122C15');
        $this->addSql('ALTER TABLE joke_comment DROP FOREIGN KEY FK_C07177ECF675F31B');
        $this->addSql('DROP TABLE comment_reaction');
        $this->addSql('DROP TABLE joke_comment');
    }
}
