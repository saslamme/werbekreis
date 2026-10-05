<?php

declare(strict_types=1);
namespace App\Tests\Unit;

use App\Entity\{Voucher, VoucherProduct};
use App\Enum\VoucherStatus;
use App\Service\{DecimalAmount, VoucherException};
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class VoucherDomainTest extends TestCase
{
    #[DataProvider('amounts')]
    public function testExactDecimalArithmetic(string $input, int $minor, string $normalized): void
    {
        self::assertSame($minor, DecimalAmount::toMinor($input));
        self::assertSame($normalized, DecimalAmount::normalize($input));
        self::assertSame($normalized, DecimalAmount::fromMinor($minor));
    }
    public static function amounts(): array { return [['0', 0, '0.00'], ['0.1', 10, '0.10'], ['10.01', 1001, '10.01'], ['99999999.99', 9999999999, '99999999.99']]; }
    #[DataProvider('invalidAmounts')]
    public function testAmountsAreNeverRoundedOrCoerced(string $input): void
    {
        $this->expectException(VoucherException::class); DecimalAmount::toMinor($input);
    }
    public static function invalidAmounts(): array { return array_map(static fn ($s) => [$s], ['-1', '1.001', '1e2', 'NaN', '1,00', '100000000', '', ' 1', '.25']); }
    public function testLargeAggregateFormatsWithoutFloat(): void { self::assertSame('12.345.678.901.234.567,89 €', DecimalAmount::format('12345678901234567.89')); }
    public function testFixedAndVariableIssueRules(): void
    {
        $fixed = (new VoucherProduct())->setFixedAmount('25');
        self::assertSame('25.00', $fixed->amountForIssue(null));
        self::assertSame('25.00', $fixed->amountForIssue('25.0'));
        $variable = (new VoucherProduct())->setMinimumAmount('10')->setMaximumAmount('250');
        self::assertSame('10.01', $variable->amountForIssue('10.01'));
        foreach ([fn () => $fixed->amountForIssue('24'), fn () => $variable->amountForIssue('9.99'), fn () => $variable->amountForIssue('250.01'), fn () => $variable->amountForIssue(null), fn () => $fixed->setActive(false)->amountForIssue(null)] as $action) {
            try { $action(); self::fail('Invalid issuance accepted'); } catch (VoucherException) { self::assertTrue(true); }
        }
    }
    public function testActivationSnapshotsValidityAndClampsMonthEnd(): void
    {
        $clock = new MockClock('2032-01-31 12:00:00 UTC'); $product = (new VoucherProduct())->setFixedAmount('50')->setValidityMonths(1);
        $voucher = $this->voucher($product, $clock); $product->setValidityMonths(99);
        self::assertFalse($voucher->isUsable($clock->now())); $voucher->activate($clock->now());
        self::assertSame('2032-02-29 12:00:00', $voucher->getValidUntil()->format('Y-m-d H:i:s'));
        $clock->sleep(29 * 86400); self::assertTrue($voucher->isUsable($clock->now()));
        $clock->sleep(1); self::assertFalse($voucher->isUsable($clock->now())); self::assertSame(VoucherStatus::Expired, $voucher->effectiveStatus($clock->now()));
    }
    public function testPartialFullAndBlockedLifecycleHasNoResurrection(): void
    {
        $clock = new MockClock('2030-05-01 12:00 UTC'); $voucher = $this->voucher((new VoucherProduct())->setFixedAmount('50'), $clock);
        $voucher->activate($clock->now()); $voucher->redeem('12.50', $clock->now());
        self::assertSame('37.50', $voucher->getRemainingAmount()); self::assertSame('50.00', $voucher->getInitialAmount()); self::assertSame(VoucherStatus::PartiallyRedeemed, $voucher->getStatus());
        $voucher->block($clock->now()); self::assertFalse($voucher->isUsable($clock->now()));
        $voucher->unblock($clock->now()); self::assertSame(VoucherStatus::PartiallyRedeemed, $voucher->getStatus()); self::assertNotNull($voucher->getBlockedAt());
        $voucher->redeem('37.50', $clock->now()); self::assertSame('0.00', $voucher->getRemainingAmount()); self::assertSame(VoucherStatus::Redeemed, $voucher->getStatus()); self::assertNotNull($voucher->getRedeemedAt());
        foreach (['block', 'unblock', 'activate'] as $action) { try { $voucher->$action($clock->now()); self::fail('Redeemed voucher resurrected'); } catch (VoucherException) { self::assertSame(VoucherStatus::Redeemed, $voucher->getStatus()); } }
        foreach (['setStatus', 'setRemainingAmount', 'setInitialAmount', 'setCode'] as $setter) { self::assertFalse(method_exists($voucher, $setter)); }
    }
    #[DataProvider('illegalRedemptions')]
    public function testInvalidRedemptionLeavesBalanceUntouched(string $amount): void
    {
        $clock = new MockClock('2030-05-01 UTC'); $voucher = $this->voucher((new VoucherProduct())->setFixedAmount('50'), $clock); $voucher->activate($clock->now());
        try { $voucher->redeem($amount, $clock->now()); self::fail('Invalid redemption accepted'); } catch (VoucherException) { self::assertSame('50.00', $voucher->getRemainingAmount()); self::assertSame(VoucherStatus::Active, $voucher->getStatus()); }
    }
    public static function illegalRedemptions(): array { return [['0'], ['50.01'], ['-1'], ['0.001']]; }
    public function testFutureValidityAndExpiredBlockedVoucher(): void
    {
        $clock = new MockClock('2030-01-01 UTC'); $product = (new VoucherProduct())->setFixedAmount('50')->setValidityMonths(1);
        $voucher = new Voucher($product, 'WK-AAAA-BBBB-CCCC-DDDD', '50', $clock->now(), $clock->now()->modify('+1 day')); $voucher->activate($clock->now());
        self::assertFalse($voucher->isUsable($clock->now())); self::assertSame('Noch nicht gültig', $voucher->publicStatusLabel($clock->now()));
        $clock->sleep(86400); self::assertTrue($voucher->isUsable($clock->now())); $voucher->block($clock->now()); $clock->sleep(32 * 86400);
        $this->expectException(VoucherException::class); $voucher->unblock($clock->now());
    }
    private function voucher(VoucherProduct $product, MockClock $clock): Voucher { return new Voucher($product, 'WK-AAAA-BBBB-CCCC-DDDD', '50', $clock->now()); }
}
