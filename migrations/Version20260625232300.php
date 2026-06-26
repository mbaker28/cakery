<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260625232300 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE cake_order ADD required_size VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE cake_order ADD required_frosting_flavor VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE cake_order ADD required_toppings TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE cake_order ADD required_layers INT NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE cake_order DROP required_size');
        $this->addSql('ALTER TABLE cake_order DROP required_frosting_flavor');
        $this->addSql('ALTER TABLE cake_order DROP required_toppings');
        $this->addSql('ALTER TABLE cake_order DROP required_layers');
    }
}
