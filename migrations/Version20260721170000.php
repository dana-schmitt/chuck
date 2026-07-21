<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260721170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add a FULLTEXT index on joke.joke for full-text search.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE joke CHANGE approved approved TINYINT(1) NOT NULL');
        $this->addSql('ALTER TABLE joke ADD FULLTEXT INDEX joke_fulltext (joke)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE joke DROP INDEX joke_fulltext');
        $this->addSql('ALTER TABLE joke CHANGE approved approved TINYINT(1) DEFAULT 1 NOT NULL');
    }
}
