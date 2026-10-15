<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261015130000 extends AbstractMigration
{
    public function getDescription(): string { return 'Add optional logo display dimensions to portal settings'; }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE PortalSettings ADD logoMaxWidth INT DEFAULT NULL, ADD logoMaxHeight INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE PortalSettings DROP logoMaxWidth, DROP logoMaxHeight');
    }
}
