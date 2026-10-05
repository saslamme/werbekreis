<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * PR7 independent news categories and scheduled editorial articles.
 */
final class Version20261005200912 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add news articles, independent news categories and optional company ownership.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE NewsArticle (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(180) NOT NULL, slug VARCHAR(180) NOT NULL, teaser VARCHAR(500) NOT NULL, content LONGTEXT NOT NULL, status VARCHAR(20) NOT NULL, publishedAt DATETIME DEFAULT NULL, featured TINYINT NOT NULL, imagePath VARCHAR(100) DEFAULT NULL, imageAltText VARCHAR(255) DEFAULT NULL, authorName VARCHAR(180) DEFAULT NULL, externalUrl VARCHAR(2048) DEFAULT NULL, createdAt DATETIME NOT NULL, updatedAt DATETIME NOT NULL, company_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_3E819CDA989D9B62 (slug), INDEX news_publication (status, publishedAt), INDEX IDX_3E819CDA979B1AD6 (company_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE newsarticle_newscategory (newsarticle_id INT NOT NULL, newscategory_id INT NOT NULL, INDEX IDX_A1AECB8B232D7840 (newsarticle_id), INDEX IDX_A1AECB8B9D72BAE3 (newscategory_id), PRIMARY KEY (newsarticle_id, newscategory_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE NewsCategory (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(180) NOT NULL, slug VARCHAR(180) NOT NULL, description LONGTEXT DEFAULT NULL, position INT NOT NULL, active TINYINT NOT NULL, createdAt DATETIME NOT NULL, updatedAt DATETIME NOT NULL, UNIQUE INDEX UNIQ_C4A75DF4989D9B62 (slug), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE NewsArticle ADD CONSTRAINT FK_3E819CDA979B1AD6 FOREIGN KEY (company_id) REFERENCES Company (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE newsarticle_newscategory ADD CONSTRAINT FK_A1AECB8B232D7840 FOREIGN KEY (newsarticle_id) REFERENCES NewsArticle (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE newsarticle_newscategory ADD CONSTRAINT FK_A1AECB8B9D72BAE3 FOREIGN KEY (newscategory_id) REFERENCES NewsCategory (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE NewsArticle DROP FOREIGN KEY FK_3E819CDA979B1AD6');
        $this->addSql('ALTER TABLE newsarticle_newscategory DROP FOREIGN KEY FK_A1AECB8B232D7840');
        $this->addSql('ALTER TABLE newsarticle_newscategory DROP FOREIGN KEY FK_A1AECB8B9D72BAE3');
        $this->addSql('DROP TABLE NewsArticle');
        $this->addSql('DROP TABLE newsarticle_newscategory');
        $this->addSql('DROP TABLE NewsCategory');
    }
}
