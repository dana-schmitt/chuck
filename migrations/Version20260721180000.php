<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260721180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add display_name and avatar_url columns to user (profile area).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user ADD display_name VARCHAR(60) DEFAULT NULL, ADD avatar_url VARCHAR(500) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user DROP display_name, DROP avatar_url');
    }
}
