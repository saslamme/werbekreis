<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Jobs with required Company relation, unique slugs and publication/employment indexes. */
final class Version20261005205439 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add JobPosting with employment, publication, location, application and salary data';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE JobPosting (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(180) NOT NULL, slug VARCHAR(180) NOT NULL, shortDescription VARCHAR(500) NOT NULL, city VARCHAR(180) NOT NULL, description LONGTEXT NOT NULL, requirements LONGTEXT DEFAULT NULL, benefits LONGTEXT DEFAULT NULL, status VARCHAR(20) NOT NULL, employmentType VARCHAR(20) NOT NULL, workModel VARCHAR(20) NOT NULL, salaryPeriod VARCHAR(20) DEFAULT NULL, featured TINYINT NOT NULL, publishedAt DATETIME DEFAULT NULL, validThrough DATETIME DEFAULT NULL, startsAt DATETIME DEFAULT NULL, addressCountry VARCHAR(2) DEFAULT NULL, locationName VARCHAR(180) DEFAULT NULL, street VARCHAR(180) DEFAULT NULL, houseNumber VARCHAR(30) DEFAULT NULL, postalCode VARCHAR(20) DEFAULT NULL, applicationEmail VARCHAR(180) DEFAULT NULL, applicationUrl VARCHAR(2048) DEFAULT NULL, contactName VARCHAR(180) DEFAULT NULL, contactPhone VARCHAR(50) DEFAULT NULL, referenceNumber VARCHAR(100) DEFAULT NULL, latitude DOUBLE PRECISION DEFAULT NULL, longitude DOUBLE PRECISION DEFAULT NULL, salaryMin NUMERIC(10, 2) DEFAULT NULL, salaryMax NUMERIC(10, 2) DEFAULT NULL, createdAt DATETIME NOT NULL, updatedAt DATETIME NOT NULL, company_id INT NOT NULL, UNIQUE INDEX UNIQ_94F0AD3D989D9B62 (slug), INDEX job_publication (status, publishedAt, validThrough), INDEX job_employment (employmentType, workModel), INDEX IDX_94F0AD3D979B1AD6 (company_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE JobPosting ADD CONSTRAINT FK_94F0AD3D979B1AD6 FOREIGN KEY (company_id) REFERENCES Company (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE JobPosting DROP FOREIGN KEY FK_94F0AD3D979B1AD6');
        $this->addSql('DROP TABLE JobPosting');
    }
}
