<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260626005630 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE cake ALTER size DROP NOT NULL');
        $this->addSql('ALTER TABLE cake ALTER layers DROP NOT NULL');
        $this->addSql('ALTER TABLE cake ALTER frosting_flavor DROP NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE cake ALTER size SET NOT NULL');
        $this->addSql('ALTER TABLE cake ALTER layers SET NOT NULL');
        $this->addSql('ALTER TABLE cake ALTER frosting_flavor SET NOT NULL');
    }
}
