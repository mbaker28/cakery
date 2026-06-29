<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260629045111 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE bakery ADD perfect_orders INT NOT NULL DEFAULT 0');
        $this->addSql('ALTER TABLE bakery ADD day_start_perfect_orders INT DEFAULT NULL');
        $this->addSql('ALTER TABLE bakery ALTER perfect_orders DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE bakery DROP perfect_orders');
        $this->addSql('ALTER TABLE bakery DROP day_start_perfect_orders');
    }
}
