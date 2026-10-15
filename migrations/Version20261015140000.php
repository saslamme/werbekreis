<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261015140000 extends AbstractMigration
{
    public function getDescription(): string { return 'Add independent optional footer logo and display dimensions'; }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE PortalSettings ADD footerLogoFileName VARCHAR(36) DEFAULT NULL, ADD footerLogoOriginalName VARCHAR(255) DEFAULT NULL, ADD footerLogoMaxWidth INT DEFAULT NULL, ADD footerLogoMaxHeight INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE PortalSettings DROP footerLogoFileName, DROP footerLogoOriginalName, DROP footerLogoMaxWidth, DROP footerLogoMaxHeight');
    }
}
