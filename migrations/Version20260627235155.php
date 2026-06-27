<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260627235155 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE bakery ADD day_start_money DOUBLE PRECISION DEFAULT NULL');
        $this->addSql('ALTER TABLE bakery ADD day_start_reputation INT DEFAULT NULL');
        $this->addSql('ALTER TABLE bakery ADD day_start_orders_completed INT DEFAULT NULL');
        $this->addSql('ALTER TABLE bakery ADD day_start_orders_failed INT DEFAULT NULL');
        $this->addSql('ALTER TABLE bakery ADD day_total_orders INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE bakery DROP day_start_money');
        $this->addSql('ALTER TABLE bakery DROP day_start_reputation');
        $this->addSql('ALTER TABLE bakery DROP day_start_orders_completed');
        $this->addSql('ALTER TABLE bakery DROP day_start_orders_failed');
        $this->addSql('ALTER TABLE bakery DROP day_total_orders');
    }
}
