<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261015110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create singleton portal settings with optional social links';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE PortalSettings (id INT NOT NULL, facebookUrl VARCHAR(2048) DEFAULT NULL, instagramUrl VARCHAR(2048) DEFAULT NULL, createdAt DATETIME NOT NULL, updatedAt DATETIME NOT NULL, PRIMARY KEY (id), CONSTRAINT portal_settings_singleton CHECK (id = 1)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('INSERT INTO PortalSettings (id, facebookUrl, instagramUrl, createdAt, updatedAt) VALUES (1, NULL, NULL, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE PortalSettings');
    }
}
