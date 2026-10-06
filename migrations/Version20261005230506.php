<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Member ownership and approval revisions; existing content remains trusted.
 */
final class Version20261005230506 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add member company ownership, moderation audit and isolated content revisions';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE ContentRevision (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(16) NOT NULL, payload JSON NOT NULL, imageNames JSON NOT NULL, baseFingerprint VARCHAR(64) NOT NULL, createdAt DATETIME NOT NULL, updatedAt DATETIME NOT NULL, moderationVersion INT DEFAULT 1 NOT NULL, moderationStatus VARCHAR(24) DEFAULT \'approved\' NOT NULL, submittedAt DATETIME DEFAULT NULL, reviewedAt DATETIME DEFAULT NULL, reviewNote LONGTEXT DEFAULT NULL, ownerCompany_id INT NOT NULL, company_id INT DEFAULT NULL, offer_id INT DEFAULT NULL, event_id INT DEFAULT NULL, newsArticle_id INT DEFAULT NULL, jobPosting_id INT DEFAULT NULL, submittedBy_id INT DEFAULT NULL, reviewedBy_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_B79071CA979B1AD6 (company_id), UNIQUE INDEX UNIQ_B79071CA53C674EE (offer_id), UNIQUE INDEX UNIQ_B79071CA71F7E88B (event_id), UNIQUE INDEX UNIQ_B79071CA6C707B90 (newsArticle_id), UNIQUE INDEX UNIQ_B79071CA5F98C82A (jobPosting_id), INDEX revision_queue (moderationStatus, submittedAt), INDEX IDX_B79071CA9721CBA8 (ownerCompany_id), INDEX IDX_B79071CAF00FFD7C (submittedBy_id), INDEX IDX_B79071CA9C6A92E (reviewedBy_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE user_company (user_id INT NOT NULL, company_id INT NOT NULL, INDEX IDX_17B21745A76ED395 (user_id), INDEX IDX_17B21745979B1AD6 (company_id), PRIMARY KEY (user_id, company_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE ContentRevision ADD CONSTRAINT FK_B79071CA9721CBA8 FOREIGN KEY (ownerCompany_id) REFERENCES Company (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE ContentRevision ADD CONSTRAINT FK_B79071CA979B1AD6 FOREIGN KEY (company_id) REFERENCES Company (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE ContentRevision ADD CONSTRAINT FK_B79071CA53C674EE FOREIGN KEY (offer_id) REFERENCES Offer (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE ContentRevision ADD CONSTRAINT FK_B79071CA71F7E88B FOREIGN KEY (event_id) REFERENCES Event (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE ContentRevision ADD CONSTRAINT FK_B79071CA6C707B90 FOREIGN KEY (newsArticle_id) REFERENCES NewsArticle (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE ContentRevision ADD CONSTRAINT FK_B79071CA5F98C82A FOREIGN KEY (jobPosting_id) REFERENCES JobPosting (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE ContentRevision ADD CONSTRAINT FK_B79071CAF00FFD7C FOREIGN KEY (submittedBy_id) REFERENCES portal_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE ContentRevision ADD CONSTRAINT FK_B79071CA9C6A92E FOREIGN KEY (reviewedBy_id) REFERENCES portal_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE user_company ADD CONSTRAINT FK_17B21745A76ED395 FOREIGN KEY (user_id) REFERENCES portal_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_company ADD CONSTRAINT FK_17B21745979B1AD6 FOREIGN KEY (company_id) REFERENCES Company (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE Company ADD moderationVersion INT DEFAULT 1 NOT NULL, ADD moderationStatus VARCHAR(24) DEFAULT \'approved\' NOT NULL, ADD submittedAt DATETIME DEFAULT NULL, ADD reviewedAt DATETIME DEFAULT NULL, ADD reviewNote LONGTEXT DEFAULT NULL, ADD submittedBy_id INT DEFAULT NULL, ADD reviewedBy_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE Company ADD CONSTRAINT FK_800230D3F00FFD7C FOREIGN KEY (submittedBy_id) REFERENCES portal_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE Company ADD CONSTRAINT FK_800230D39C6A92E FOREIGN KEY (reviewedBy_id) REFERENCES portal_user (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_800230D3F00FFD7C ON Company (submittedBy_id)');
        $this->addSql('CREATE INDEX IDX_800230D39C6A92E ON Company (reviewedBy_id)');
        $this->addSql('ALTER TABLE Event ADD moderationVersion INT DEFAULT 1 NOT NULL, ADD moderationStatus VARCHAR(24) DEFAULT \'approved\' NOT NULL, ADD submittedAt DATETIME DEFAULT NULL, ADD reviewedAt DATETIME DEFAULT NULL, ADD reviewNote LONGTEXT DEFAULT NULL, ADD submittedBy_id INT DEFAULT NULL, ADD reviewedBy_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE Event ADD CONSTRAINT FK_FA6F25A3F00FFD7C FOREIGN KEY (submittedBy_id) REFERENCES portal_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE Event ADD CONSTRAINT FK_FA6F25A39C6A92E FOREIGN KEY (reviewedBy_id) REFERENCES portal_user (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_FA6F25A3F00FFD7C ON Event (submittedBy_id)');
        $this->addSql('CREATE INDEX IDX_FA6F25A39C6A92E ON Event (reviewedBy_id)');
        $this->addSql('ALTER TABLE JobPosting ADD moderationVersion INT DEFAULT 1 NOT NULL, ADD moderationStatus VARCHAR(24) DEFAULT \'approved\' NOT NULL, ADD submittedAt DATETIME DEFAULT NULL, ADD reviewedAt DATETIME DEFAULT NULL, ADD reviewNote LONGTEXT DEFAULT NULL, ADD submittedBy_id INT DEFAULT NULL, ADD reviewedBy_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE JobPosting ADD CONSTRAINT FK_94F0AD3DF00FFD7C FOREIGN KEY (submittedBy_id) REFERENCES portal_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE JobPosting ADD CONSTRAINT FK_94F0AD3D9C6A92E FOREIGN KEY (reviewedBy_id) REFERENCES portal_user (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_94F0AD3DF00FFD7C ON JobPosting (submittedBy_id)');
        $this->addSql('CREATE INDEX IDX_94F0AD3D9C6A92E ON JobPosting (reviewedBy_id)');
        $this->addSql('ALTER TABLE NewsArticle ADD moderationVersion INT DEFAULT 1 NOT NULL, ADD moderationStatus VARCHAR(24) DEFAULT \'approved\' NOT NULL, ADD submittedAt DATETIME DEFAULT NULL, ADD reviewedAt DATETIME DEFAULT NULL, ADD reviewNote LONGTEXT DEFAULT NULL, ADD submittedBy_id INT DEFAULT NULL, ADD reviewedBy_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE NewsArticle ADD CONSTRAINT FK_3E819CDAF00FFD7C FOREIGN KEY (submittedBy_id) REFERENCES portal_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE NewsArticle ADD CONSTRAINT FK_3E819CDA9C6A92E FOREIGN KEY (reviewedBy_id) REFERENCES portal_user (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_3E819CDAF00FFD7C ON NewsArticle (submittedBy_id)');
        $this->addSql('CREATE INDEX IDX_3E819CDA9C6A92E ON NewsArticle (reviewedBy_id)');
        $this->addSql('ALTER TABLE Offer ADD moderationVersion INT DEFAULT 1 NOT NULL, ADD moderationStatus VARCHAR(24) DEFAULT \'approved\' NOT NULL, ADD submittedAt DATETIME DEFAULT NULL, ADD reviewedAt DATETIME DEFAULT NULL, ADD reviewNote LONGTEXT DEFAULT NULL, ADD submittedBy_id INT DEFAULT NULL, ADD reviewedBy_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE Offer ADD CONSTRAINT FK_E817A83AF00FFD7C FOREIGN KEY (submittedBy_id) REFERENCES portal_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE Offer ADD CONSTRAINT FK_E817A83A9C6A92E FOREIGN KEY (reviewedBy_id) REFERENCES portal_user (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_E817A83AF00FFD7C ON Offer (submittedBy_id)');
        $this->addSql('CREATE INDEX IDX_E817A83A9C6A92E ON Offer (reviewedBy_id)');
        $this->addSql("INSERT INTO user_company (user_id, company_id) SELECT id, company_id FROM portal_user WHERE company_id IS NOT NULL AND roles LIKE '%\"ROLE_MEMBER\"%'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ContentRevision DROP FOREIGN KEY FK_B79071CA9721CBA8');
        $this->addSql('ALTER TABLE ContentRevision DROP FOREIGN KEY FK_B79071CA979B1AD6');
        $this->addSql('ALTER TABLE ContentRevision DROP FOREIGN KEY FK_B79071CA53C674EE');
        $this->addSql('ALTER TABLE ContentRevision DROP FOREIGN KEY FK_B79071CA71F7E88B');
        $this->addSql('ALTER TABLE ContentRevision DROP FOREIGN KEY FK_B79071CA6C707B90');
        $this->addSql('ALTER TABLE ContentRevision DROP FOREIGN KEY FK_B79071CA5F98C82A');
        $this->addSql('ALTER TABLE ContentRevision DROP FOREIGN KEY FK_B79071CAF00FFD7C');
        $this->addSql('ALTER TABLE ContentRevision DROP FOREIGN KEY FK_B79071CA9C6A92E');
        $this->addSql('ALTER TABLE user_company DROP FOREIGN KEY FK_17B21745A76ED395');
        $this->addSql('ALTER TABLE user_company DROP FOREIGN KEY FK_17B21745979B1AD6');
        $this->addSql('DROP TABLE ContentRevision');
        $this->addSql('DROP TABLE user_company');
        $this->addSql('ALTER TABLE Company DROP FOREIGN KEY FK_800230D3F00FFD7C');
        $this->addSql('ALTER TABLE Company DROP FOREIGN KEY FK_800230D39C6A92E');
        $this->addSql('DROP INDEX IDX_800230D3F00FFD7C ON Company');
        $this->addSql('DROP INDEX IDX_800230D39C6A92E ON Company');
        $this->addSql('ALTER TABLE Company DROP moderationVersion, DROP moderationStatus, DROP submittedAt, DROP reviewedAt, DROP reviewNote, DROP submittedBy_id, DROP reviewedBy_id');
        $this->addSql('ALTER TABLE Event DROP FOREIGN KEY FK_FA6F25A3F00FFD7C');
        $this->addSql('ALTER TABLE Event DROP FOREIGN KEY FK_FA6F25A39C6A92E');
        $this->addSql('DROP INDEX IDX_FA6F25A3F00FFD7C ON Event');
        $this->addSql('DROP INDEX IDX_FA6F25A39C6A92E ON Event');
        $this->addSql('ALTER TABLE Event DROP moderationVersion, DROP moderationStatus, DROP submittedAt, DROP reviewedAt, DROP reviewNote, DROP submittedBy_id, DROP reviewedBy_id');
        $this->addSql('ALTER TABLE JobPosting DROP FOREIGN KEY FK_94F0AD3DF00FFD7C');
        $this->addSql('ALTER TABLE JobPosting DROP FOREIGN KEY FK_94F0AD3D9C6A92E');
        $this->addSql('DROP INDEX IDX_94F0AD3DF00FFD7C ON JobPosting');
        $this->addSql('DROP INDEX IDX_94F0AD3D9C6A92E ON JobPosting');
        $this->addSql('ALTER TABLE JobPosting DROP moderationVersion, DROP moderationStatus, DROP submittedAt, DROP reviewedAt, DROP reviewNote, DROP submittedBy_id, DROP reviewedBy_id');
        $this->addSql('ALTER TABLE NewsArticle DROP FOREIGN KEY FK_3E819CDAF00FFD7C');
        $this->addSql('ALTER TABLE NewsArticle DROP FOREIGN KEY FK_3E819CDA9C6A92E');
        $this->addSql('DROP INDEX IDX_3E819CDAF00FFD7C ON NewsArticle');
        $this->addSql('DROP INDEX IDX_3E819CDA9C6A92E ON NewsArticle');
        $this->addSql('ALTER TABLE NewsArticle DROP moderationVersion, DROP moderationStatus, DROP submittedAt, DROP reviewedAt, DROP reviewNote, DROP submittedBy_id, DROP reviewedBy_id');
        $this->addSql('ALTER TABLE Offer DROP FOREIGN KEY FK_E817A83AF00FFD7C');
        $this->addSql('ALTER TABLE Offer DROP FOREIGN KEY FK_E817A83A9C6A92E');
        $this->addSql('DROP INDEX IDX_E817A83AF00FFD7C ON Offer');
        $this->addSql('DROP INDEX IDX_E817A83A9C6A92E ON Offer');
        $this->addSql('ALTER TABLE Offer DROP moderationVersion, DROP moderationStatus, DROP submittedAt, DROP reviewedAt, DROP reviewNote, DROP submittedBy_id, DROP reviewedBy_id');
    }
}
