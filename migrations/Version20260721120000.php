<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add the joke_like table so users can like jokes and revisit them.
 */
final class Version20260721120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create joke_like table (user <-> joke likes with timestamp).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE joke_like (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, joke_id INT NOT NULL, liked_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_JOKE_LIKE_USER (user_id), INDEX IDX_JOKE_LIKE_JOKE (joke_id), UNIQUE INDEX UNIQ_USER_JOKE (user_id, joke_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE joke_like ADD CONSTRAINT FK_JOKE_LIKE_USER FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE joke_like ADD CONSTRAINT FK_JOKE_LIKE_JOKE FOREIGN KEY (joke_id) REFERENCES joke (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE joke_like DROP FOREIGN KEY FK_JOKE_LIKE_USER');
        $this->addSql('ALTER TABLE joke_like DROP FOREIGN KEY FK_JOKE_LIKE_JOKE');
        $this->addSql('DROP TABLE joke_like');
    }
}
