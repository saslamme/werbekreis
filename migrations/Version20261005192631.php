<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * PR6 independent event categories, bounded series and indexed event occurrences.
 */
final class Version20261005192631 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add events, event categories and scheduled occurrences with optional company ownership.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE Event (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(180) NOT NULL, slug VARCHAR(180) NOT NULL, shortDescription VARCHAR(500) DEFAULT NULL, description LONGTEXT DEFAULT NULL, active TINYINT NOT NULL, featured TINYINT NOT NULL, startsAt DATETIME NOT NULL, endsAt DATETIME NOT NULL, allDay TINYINT NOT NULL, recurrenceType VARCHAR(20) NOT NULL, recurrenceUntil DATE DEFAULT NULL, cancelled TINYINT NOT NULL, cancellationNotice LONGTEXT DEFAULT NULL, freeAdmission TINYINT NOT NULL, admissionText VARCHAR(255) DEFAULT NULL, organizerName VARCHAR(180) DEFAULT NULL, organizerEmail VARCHAR(180) DEFAULT NULL, organizerPhone VARCHAR(80) DEFAULT NULL, organizerWebsite VARCHAR(2048) DEFAULT NULL, locationName VARCHAR(180) DEFAULT NULL, street VARCHAR(180) DEFAULT NULL, houseNumber VARCHAR(30) DEFAULT NULL, postalCode VARCHAR(20) DEFAULT NULL, city VARCHAR(180) DEFAULT NULL, externalUrl VARCHAR(2048) DEFAULT NULL, ticketUrl VARCHAR(2048) DEFAULT NULL, imagePath VARCHAR(100) DEFAULT NULL, imageAlt VARCHAR(255) DEFAULT NULL, latitude DOUBLE PRECISION DEFAULT NULL, longitude DOUBLE PRECISION DEFAULT NULL, createdAt DATETIME NOT NULL, updatedAt DATETIME NOT NULL, company_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_FA6F25A3989D9B62 (slug), INDEX IDX_FA6F25A3979B1AD6 (company_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE event_eventcategory (event_id INT NOT NULL, eventcategory_id INT NOT NULL, INDEX IDX_D30AE1BF71F7E88B (event_id), INDEX IDX_D30AE1BF29E3B4B5 (eventcategory_id), PRIMARY KEY (event_id, eventcategory_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE EventCategory (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(180) NOT NULL, slug VARCHAR(180) NOT NULL, description LONGTEXT DEFAULT NULL, icon VARCHAR(80) DEFAULT NULL, position INT NOT NULL, active TINYINT NOT NULL, createdAt DATETIME NOT NULL, updatedAt DATETIME NOT NULL, UNIQUE INDEX UNIQ_BD5B78B0989D9B62 (slug), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE EventOccurrence (id INT AUTO_INCREMENT NOT NULL, startsAt DATETIME NOT NULL, endsAt DATETIME NOT NULL, event_id INT NOT NULL, UNIQUE INDEX event_occurrence_date (event_id, startsAt), INDEX event_occurrence_range (startsAt, endsAt), INDEX IDX_B26C239D71F7E88B (event_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE Event ADD CONSTRAINT FK_FA6F25A3979B1AD6 FOREIGN KEY (company_id) REFERENCES Company (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE event_eventcategory ADD CONSTRAINT FK_D30AE1BF71F7E88B FOREIGN KEY (event_id) REFERENCES Event (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE event_eventcategory ADD CONSTRAINT FK_D30AE1BF29E3B4B5 FOREIGN KEY (eventcategory_id) REFERENCES EventCategory (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE EventOccurrence ADD CONSTRAINT FK_B26C239D71F7E88B FOREIGN KEY (event_id) REFERENCES Event (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE Event DROP FOREIGN KEY FK_FA6F25A3979B1AD6');
        $this->addSql('ALTER TABLE event_eventcategory DROP FOREIGN KEY FK_D30AE1BF71F7E88B');
        $this->addSql('ALTER TABLE event_eventcategory DROP FOREIGN KEY FK_D30AE1BF29E3B4B5');
        $this->addSql('ALTER TABLE EventOccurrence DROP FOREIGN KEY FK_B26C239D71F7E88B');
        $this->addSql('DROP TABLE Event');
        $this->addSql('DROP TABLE event_eventcategory');
        $this->addSql('DROP TABLE EventCategory');
        $this->addSql('DROP TABLE EventOccurrence');
    }
}
