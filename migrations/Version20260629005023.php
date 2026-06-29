<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260629005023 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql("ALTER TABLE bakery ADD upgrades JSON DEFAULT '{}'");
        $this->addSql("UPDATE bakery SET upgrades = '{}'");
        $this->addSql('ALTER TABLE bakery ALTER upgrades SET NOT NULL');
        $this->addSql('ALTER TABLE bakery ALTER upgrades DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE bakery DROP upgrades');
    }
}
