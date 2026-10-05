<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * PR5 offers and promotions, with decimal prices and scheduled visibility.
 */
final class Version20261005184734 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add offers with company ownership, decimal prices, scheduling and image metadata.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE Offer (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(180) NOT NULL, slug VARCHAR(180) NOT NULL, shortDescription VARCHAR(500) DEFAULT NULL, description LONGTEXT DEFAULT NULL, type VARCHAR(20) NOT NULL, regularPrice NUMERIC(10, 2) DEFAULT NULL, offerPrice NUMERIC(10, 2) DEFAULT NULL, discountText VARCHAR(120) DEFAULT NULL, startsAt DATETIME DEFAULT NULL, endsAt DATETIME DEFAULT NULL, active TINYINT NOT NULL, featured TINYINT NOT NULL, imagePath VARCHAR(100) DEFAULT NULL, imageAlt VARCHAR(255) DEFAULT NULL, externalUrl VARCHAR(2048) DEFAULT NULL, terms LONGTEXT DEFAULT NULL, createdAt DATETIME NOT NULL, updatedAt DATETIME NOT NULL, company_id INT NOT NULL, UNIQUE INDEX UNIQ_E817A83A989D9B62 (slug), INDEX offer_schedule (active, startsAt, endsAt), INDEX IDX_E817A83A979B1AD6 (company_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE Offer ADD CONSTRAINT FK_E817A83A979B1AD6 FOREIGN KEY (company_id) REFERENCES Company (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE Offer DROP FOREIGN KEY FK_E817A83A979B1AD6');
        $this->addSql('DROP TABLE Offer');
    }
}
