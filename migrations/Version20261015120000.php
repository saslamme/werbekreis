<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261015120000 extends AbstractMigration
{
    public function getDescription(): string { return 'Add optional website logo metadata to portal settings'; }
    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE PortalSettings ADD logoFileName VARCHAR(36) DEFAULT NULL, ADD logoOriginalName VARCHAR(255) DEFAULT NULL');
    }
    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE PortalSettings DROP logoFileName, DROP logoOriginalName');
    }
}
