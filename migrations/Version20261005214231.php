<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Voucher products, immutable redemption ledger and scoped redeemer company.
 */
final class Version20261005214231 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add voucher products, balances, redemption audit and user company assignment';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE Voucher (id INT AUTO_INCREMENT NOT NULL, code VARCHAR(40) NOT NULL, initialAmount NUMERIC(10, 2) NOT NULL, remainingAmount NUMERIC(10, 2) NOT NULL, status VARCHAR(24) NOT NULL, statusBeforeBlock VARCHAR(24) DEFAULT NULL, issuedAt DATETIME NOT NULL, validFrom DATETIME DEFAULT NULL, validUntil DATETIME DEFAULT NULL, activatedAt DATETIME DEFAULT NULL, redeemedAt DATETIME DEFAULT NULL, blockedAt DATETIME DEFAULT NULL, validityMonths INT DEFAULT NULL, note LONGTEXT DEFAULT NULL, externalReference VARCHAR(180) DEFAULT NULL, createdAt DATETIME NOT NULL, updatedAt DATETIME NOT NULL, product_id INT NOT NULL, UNIQUE INDEX UNIQ_DC2F9C4477153098 (code), INDEX voucher_validity (status, validFrom, validUntil), INDEX IDX_DC2F9C444584665A (product_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE VoucherProduct (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(180) NOT NULL, slug VARCHAR(180) NOT NULL, description LONGTEXT NOT NULL, terms LONGTEXT DEFAULT NULL, active TINYINT NOT NULL, featured TINYINT NOT NULL, position INT NOT NULL, validityMonths INT DEFAULT NULL, minimumAmount NUMERIC(10, 2) DEFAULT NULL, maximumAmount NUMERIC(10, 2) DEFAULT NULL, fixedAmount NUMERIC(10, 2) DEFAULT NULL, createdAt DATETIME NOT NULL, updatedAt DATETIME NOT NULL, UNIQUE INDEX UNIQ_E9040497989D9B62 (slug), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE voucherproduct_company (voucherproduct_id INT NOT NULL, company_id INT NOT NULL, INDEX IDX_54F23A2C9CE25B9B (voucherproduct_id), INDEX IDX_54F23A2C979B1AD6 (company_id), PRIMARY KEY (voucherproduct_id, company_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE voucher_product_selling_company (voucherproduct_id INT NOT NULL, company_id INT NOT NULL, INDEX IDX_65859489CE25B9B (voucherproduct_id), INDEX IDX_6585948979B1AD6 (company_id), PRIMARY KEY (voucherproduct_id, company_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE VoucherRedemption (id INT AUTO_INCREMENT NOT NULL, actorIdentifier VARCHAR(180) NOT NULL, companyName VARCHAR(180) NOT NULL, amount NUMERIC(10, 2) NOT NULL, balanceBefore NUMERIC(10, 2) NOT NULL, balanceAfter NUMERIC(10, 2) NOT NULL, redeemedAt DATETIME NOT NULL, createdAt DATETIME NOT NULL, reference VARCHAR(180) DEFAULT NULL, note LONGTEXT DEFAULT NULL, idempotencyHash VARCHAR(64) NOT NULL, requestFingerprint VARCHAR(64) NOT NULL, voucher_id INT NOT NULL, company_id INT NOT NULL, performedBy_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_94F938C8C9A0BE92 (idempotencyHash), INDEX redemption_date (redeemedAt), INDEX IDX_94F938C828AA1B6F (voucher_id), INDEX IDX_94F938C8979B1AD6 (company_id), INDEX IDX_94F938C8AA03708 (performedBy_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE Voucher ADD CONSTRAINT FK_DC2F9C444584665A FOREIGN KEY (product_id) REFERENCES VoucherProduct (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE voucherproduct_company ADD CONSTRAINT FK_54F23A2C9CE25B9B FOREIGN KEY (voucherproduct_id) REFERENCES VoucherProduct (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE voucherproduct_company ADD CONSTRAINT FK_54F23A2C979B1AD6 FOREIGN KEY (company_id) REFERENCES Company (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE voucher_product_selling_company ADD CONSTRAINT FK_65859489CE25B9B FOREIGN KEY (voucherproduct_id) REFERENCES VoucherProduct (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE voucher_product_selling_company ADD CONSTRAINT FK_6585948979B1AD6 FOREIGN KEY (company_id) REFERENCES Company (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE VoucherRedemption ADD CONSTRAINT FK_94F938C828AA1B6F FOREIGN KEY (voucher_id) REFERENCES Voucher (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE VoucherRedemption ADD CONSTRAINT FK_94F938C8979B1AD6 FOREIGN KEY (company_id) REFERENCES Company (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE VoucherRedemption ADD CONSTRAINT FK_94F938C8AA03708 FOREIGN KEY (performedBy_id) REFERENCES portal_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE portal_user ADD company_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE portal_user ADD CONSTRAINT FK_76511E4979B1AD6 FOREIGN KEY (company_id) REFERENCES Company (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_76511E4979B1AD6 ON portal_user (company_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE Voucher DROP FOREIGN KEY FK_DC2F9C444584665A');
        $this->addSql('ALTER TABLE voucherproduct_company DROP FOREIGN KEY FK_54F23A2C9CE25B9B');
        $this->addSql('ALTER TABLE voucherproduct_company DROP FOREIGN KEY FK_54F23A2C979B1AD6');
        $this->addSql('ALTER TABLE voucher_product_selling_company DROP FOREIGN KEY FK_65859489CE25B9B');
        $this->addSql('ALTER TABLE voucher_product_selling_company DROP FOREIGN KEY FK_6585948979B1AD6');
        $this->addSql('ALTER TABLE VoucherRedemption DROP FOREIGN KEY FK_94F938C828AA1B6F');
        $this->addSql('ALTER TABLE VoucherRedemption DROP FOREIGN KEY FK_94F938C8979B1AD6');
        $this->addSql('ALTER TABLE VoucherRedemption DROP FOREIGN KEY FK_94F938C8AA03708');
        $this->addSql('DROP TABLE Voucher');
        $this->addSql('DROP TABLE VoucherProduct');
        $this->addSql('DROP TABLE voucherproduct_company');
        $this->addSql('DROP TABLE voucher_product_selling_company');
        $this->addSql('DROP TABLE VoucherRedemption');
        $this->addSql('ALTER TABLE portal_user DROP FOREIGN KEY FK_76511E4979B1AD6');
        $this->addSql('DROP INDEX IDX_76511E4979B1AD6 ON portal_user');
        $this->addSql('ALTER TABLE portal_user DROP company_id');
    }
}
