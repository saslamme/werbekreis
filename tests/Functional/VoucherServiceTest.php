<?php

declare(strict_types=1);
namespace App\Tests\Functional;
use App\Entity\{User, Voucher, VoucherProduct, VoucherRedemption};
use App\Enum\VoucherStatus;
use App\Service\{VoucherCodeGenerator, VoucherCodeGeneratorInterface, VoucherDashboard, VoucherException, VoucherLifecycle, VoucherRedemptionService};
use Doctrine\ORM\EntityManagerInterface;

final class VoucherServiceTest extends VoucherDatabaseTestCase
{
    public function testSecureCodesAndNormalizedLookup(): void
    {
        $generator = static::getContainer()->get(VoucherCodeGenerator::class); $codes = [];
        for ($i = 0; $i < 100; ++$i) { $code = $generator->generate(); self::assertMatchesRegularExpression('/^WK-(?:[23456789ABCDEFGHJKMNPQRSTUVWXYZ]{4}-){3}[23456789ABCDEFGHJKMNPQRSTUVWXYZ]{4}$/D', $code); $codes[] = $code; }
        self::assertCount(100, array_unique($codes));
        $repo = static::getContainer()->get('doctrine')->getRepository(Voucher::class);
        self::assertSame($this->voucher(1), $repo->findByCode('  '.strtolower($this->voucher(1)->getCode())." \n"));
        self::assertNull($repo->findByCode('not a code'));
    }
    public function testIssuanceAndDatabaseCollisionRetry(): void
    {
        $admin = $this->login('admin'); $product = $this->voucher(1)->getProduct(); $oldCode = $this->voucher(1)->getCode();
        $fake = new class($oldCode) implements VoucherCodeGeneratorInterface {
            public int $calls = 0;
            public function __construct(private string $existing) {}
            public function generate(): string { return ++$this->calls === 1 ? $this->existing : 'WK-ZZZZ-YYYY-XXXX-WWWW'; }
        };
        $service = new VoucherLifecycle(static::getContainer()->get('doctrine'), $this->clock, $fake);
        $created = $service->issue($product->getId(), '10.01', $admin->getId());
        self::assertSame(2, $fake->calls); self::assertSame('10.01', $created->getInitialAmount()); self::assertSame(VoucherStatus::Created, $created->getStatus());
        self::assertSame(16, static::getContainer()->get('doctrine')->getRepository(Voucher::class)->count([]));
        $service->change($created->getId(), 'activate', $admin->getId());
        self::assertSame(VoucherStatus::Active, static::getContainer()->get('doctrine')->getRepository(Voucher::class)->find($created->getId())->getStatus());
        $this->expectException(VoucherException::class); $service->change($created->getId(), 'delete', $admin->getId());
    }
    public function testPartialFullAuditAndIdempotency(): void
    {
        $actor = $this->login('redeemer'); $voucher = $this->voucher(1); $key = bin2hex(random_bytes(32)); $service = static::getContainer()->get(VoucherRedemptionService::class);
        $first = $service->redeem($voucher->getId(), $actor->getCompany()->getId(), '12.50', $actor->getId(), $key, 'TEST-RECEIPT', 'TEST note');
        self::assertSame('50.00', $first->getBalanceBefore()); self::assertSame('37.50', $first->getBalanceAfter()); self::assertSame('12.50', $first->getAmount());
        self::assertSame($actor->getEmail(), $first->getActorIdentifier()); self::assertSame($actor->getCompany()->getName(), $first->getCompanyName()); self::assertEquals($this->clock->now(), $first->getRedeemedAt());
        $again = $service->redeem($voucher->getId(), $actor->getCompany()->getId(), '12.5', $actor->getId(), $key, 'TEST-RECEIPT', 'TEST note'); self::assertSame($first->getId(), $again->getId());
        $lastKey = bin2hex(random_bytes(32)); $last = $service->redeem($voucher->getId(), $actor->getCompany()->getId(), '37.50', $actor->getId(), $lastKey);
        self::assertSame('0.00', $last->getBalanceAfter()); self::assertSame(VoucherStatus::Redeemed, $last->getResultStatus());
        self::assertSame($last->getId(), $service->redeem($voucher->getId(), $actor->getCompany()->getId(), '37.50', $actor->getId(), $lastKey)->getId());
        self::assertSame(6, static::getContainer()->get('doctrine')->getRepository(VoucherRedemption::class)->count([]));
        $this->expectException(VoucherException::class); $service->redeem($voucher->getId(), $actor->getCompany()->getId(), '12.51', $actor->getId(), $key);
    }
    public function testAuthorizationAcceptanceAndUnavailableStatesLeaveNoTrace(): void
    {
        $actor = $this->login('redeemer'); $companyId = $actor->getCompany()->getId(); $actorId = $actor->getId(); $service = static::getContainer()->get(VoucherRedemptionService::class); $admin = $this->login('admin'); $editor = $this->login('editor'); $member = $this->login('member');
        $calls = [];
        foreach ([0, 3, 4, 5, 6] as $index) { $calls[] = [$this->voucher($index)->getId(), $companyId, '1', $actorId]; }
        foreach (['0', '50.01', '-1', '1.001'] as $amount) { $calls[] = [$this->voucher(1)->getId(), $companyId, $amount, $actorId]; }
        $calls[] = [$this->voucher(1)->getId(), $companyId, '1', $editor->getId()]; $calls[] = [$this->voucher(1)->getId(), $companyId, '1', $member->getId()];
        $calls[] = [$this->voucher(1)->getId(), $this->company('Beispielcafé Uferpause')->getId(), '1', $actorId];
        $calls[] = [$this->voucher(1)->getId(), $this->company('Demo-Werkstatt Stadtblick')->getId(), '1', $admin->getId()];
        foreach ($calls as $args) { try { $service->redeem(...[...$args, bin2hex(random_bytes(32))]); self::fail('Invalid redemption accepted'); } catch (VoucherException) { self::assertTrue(true); } }
        $em = static::getContainer()->get('doctrine')->getManager(); self::assertSame(4, $em->getRepository(VoucherRedemption::class)->count([])); self::assertSame('50.00', $this->voucher(1)->getRemainingAmount());
    }
    public function testFreshStateOverridesIdentityMapAndRoleRevocation(): void
    {
        $actor = $this->login('redeemer'); $voucher = $this->voucher(1); $id = $voucher->getId(); $actorId = $actor->getId(); $companyId = $actor->getCompany()->getId(); $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->getConnection()->executeStatement('UPDATE Voucher SET remainingAmount = ? WHERE id = ?', ['20.00', $id]);
        $service = static::getContainer()->get(VoucherRedemptionService::class);
        $entry = $service->redeem($id, $companyId, '10', $actorId, bin2hex(random_bytes(32))); self::assertSame('20.00', $entry->getBalanceBefore()); self::assertSame('10.00', $entry->getBalanceAfter());
        $em->getConnection()->executeStatement('UPDATE portal_user SET roles = ? WHERE id = ?', ['["ROLE_MEMBER"]', $actorId]);
        $this->expectException(VoucherException::class); $service->redeem($id, $companyId, '1', $actorId, bin2hex(random_bytes(32)));
    }
    public function testRollbackOnLedgerWriteFailure(): void
    {
        $actor = $this->login('redeemer'); $voucherId = $this->voucher(1)->getId(); $companyId = $actor->getCompany()->getId(); $actorId = $actor->getId(); $db = static::getContainer()->get('doctrine')->getConnection();
        $db->executeStatement("CREATE TRIGGER test_voucher_ledger_failure BEFORE INSERT ON VoucherRedemption FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Synthetic ledger failure'");
        try {
            try { static::getContainer()->get(VoucherRedemptionService::class)->redeem($voucherId, $companyId, '10', $actorId, bin2hex(random_bytes(32))); self::fail('Failed ledger write accepted'); }
            catch (VoucherException $e) { self::assertStringNotContainsString('Synthetic', $e->getMessage()); }
        } finally { $db->executeStatement('DROP TRIGGER test_voucher_ledger_failure'); }
        self::assertSame('50.00', $db->fetchOne('SELECT remainingAmount FROM Voucher WHERE id = ?', [$voucherId])); self::assertSame(4, (int) $db->fetchOne('SELECT COUNT(*) FROM VoucherRedemption'));
    }
    public function testLedgerCannotBeUpdatedOrRemovedAndSnapshotsSurviveUserDeletion(): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class); $entry = $em->getRepository(VoucherRedemption::class)->findOneBy([]); self::assertNotNull($entry);
        $actorIdentifier = $entry->getActorIdentifier(); $em->getConnection()->executeStatement('DELETE FROM portal_user WHERE id = ?', [$entry->getPerformedBy()->getId()]); $em->clear();
        $entry = $em->find(VoucherRedemption::class, $entry->getId()); self::assertNull($entry->getPerformedBy()); self::assertSame($actorIdentifier, $entry->getActorIdentifier());
        $this->expectException(\LogicException::class); $em->remove($entry);
    }
    public function testSqlFinancialStatisticsAndCalendarBoundaries(): void
    {
        $dashboard = static::getContainer()->get(VoucherDashboard::class)->counts();
        self::assertSame(10, $dashboard['Aktive Gutscheine']); self::assertSame('747,50 €', $dashboard['Offenes Gutschein-Guthaben']);
        self::assertSame('87,50 €', $dashboard['Heute eingelöst']); self::assertSame('87,50 €', $dashboard['Diesen Monat eingelöst']); self::assertSame(4, $dashboard['Anzahl Einlösungen']); self::assertSame(1, $dashboard['Gesperrte Gutscheine']);
    }
    public function testLocalDayMonthBoundariesAreExclusiveAndExact(): void
    {
        $db = static::getContainer()->get('doctrine')->getConnection(); $ids = $db->fetchFirstColumn('SELECT id FROM VoucherRedemption ORDER BY id');
        // Berlin May 1 starts April 30 at 22:00 UTC. At next midnight the entry belongs to tomorrow.
        foreach (['2030-04-30 21:59:59', '2030-04-30 22:00:00', '2030-05-01 21:59:59', '2030-05-01 22:00:00'] as $i => $date) { $db->executeStatement('UPDATE VoucherRedemption SET amount = ?, redeemedAt = ? WHERE id = ?', ['0.10', $date, $ids[$i]]); }
        $counts = static::getContainer()->get(VoucherDashboard::class)->counts(); self::assertSame('0,20 €', $counts['Heute eingelöst']); self::assertSame('0,30 €', $counts['Diesen Monat eingelöst']);
        // DST transition: the Berlin calendar day is 23 hours, not a fixed 86400 seconds.
        $this->clock->modify('2030-03-31 12:00 UTC');
        foreach (['2030-03-30 22:59:59', '2030-03-30 23:00:00', '2030-03-31 21:59:59', '2030-03-31 22:00:00'] as $i => $date) { $db->executeStatement('UPDATE VoucherRedemption SET redeemedAt = ? WHERE id = ?', [$date, $ids[$i]]); }
        self::assertSame('0,20 €', static::getContainer()->get(VoucherDashboard::class)->counts()['Heute eingelöst']);
    }

    public function testFreshAcceptanceAndCompanyUserChangesRejectRedemption(): void
    {
        $actor = $this->login('redeemer'); $actorId = $actor->getId(); $companyId = $actor->getCompany()->getId(); $voucher = $this->voucher(1); $id = $voucher->getId(); $productId = $voucher->getProduct()->getId();
        // Populate the collection before changing the owning join directly, simulating another request.
        self::assertCount(2, $voucher->getProduct()->getAcceptingCompanies()); $db = static::getContainer()->get('doctrine')->getConnection(); $service = static::getContainer()->get(VoucherRedemptionService::class);
        $db->executeStatement('DELETE FROM voucherproduct_company WHERE voucherproduct_id = ? AND company_id = ?', [$productId, $companyId]);
        $this->reject($service, $id, $companyId, $actorId);
        $db->executeStatement('INSERT INTO voucherproduct_company (voucherproduct_id, company_id) VALUES (?, ?)', [$productId, $companyId]);
        $db->executeStatement('UPDATE Company SET active = 0 WHERE id = ?', [$companyId]); $this->reject($service, $id, $companyId, $actorId);
        $db->executeStatement('UPDATE Company SET active = 1 WHERE id = ?', [$companyId]);
        $db->executeStatement('UPDATE portal_user SET company_id = NULL WHERE id = ?', [$actorId]); $this->reject($service, $id, $companyId, $actorId);
        $db->executeStatement('UPDATE portal_user SET company_id = ?, active = 0 WHERE id = ?', [$companyId, $actorId]); $this->reject($service, $id, $companyId, $actorId);
        self::assertSame('50.00', $db->fetchOne('SELECT remainingAmount FROM Voucher WHERE id = ?', [$id])); self::assertSame(4, (int) $db->fetchOne('SELECT COUNT(*) FROM VoucherRedemption'));
    }
    public function testInsertedLedgerRollsBackWhenBalanceWriteFails(): void
    {
        $actor = $this->login('redeemer'); $voucherId = $this->voucher(1)->getId(); $companyId = $actor->getCompany()->getId(); $actorId = $actor->getId(); $db = static::getContainer()->get('doctrine')->getConnection();
        $db->executeStatement("CREATE TRIGGER test_voucher_balance_failure AFTER UPDATE ON Voucher FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Synthetic balance failure'");
        try { $this->reject(static::getContainer()->get(VoucherRedemptionService::class), $voucherId, $companyId, $actorId); }
        finally { $db->executeStatement('DROP TRIGGER test_voucher_balance_failure'); }
        self::assertSame('50.00', $db->fetchOne('SELECT remainingAmount FROM Voucher WHERE id = ?', [$voucherId])); self::assertSame(4, (int) $db->fetchOne('SELECT COUNT(*) FROM VoucherRedemption'));
    }
    private function reject(VoucherRedemptionService $service, int $voucherId, int $companyId, int $actorId): void
    {
        try { $service->redeem($voucherId, $companyId, '10', $actorId, bin2hex(random_bytes(32))); self::fail('Forbidden or failed redemption committed'); }
        catch (VoucherException) { self::assertTrue(true); }
    }

}
