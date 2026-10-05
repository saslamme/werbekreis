<?php

declare(strict_types=1);
namespace App\Tests\Functional;

use App\DataFixtures\VoucherFixtures;
use App\Entity\{Voucher, VoucherProduct, VoucherRedemption};
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\{Clock, ClockInterface, MockClock};

abstract class VoucherDatabaseTestCase extends PublicDirectoryTestCase
{
    private ClockInterface $previousClock;
    protected MockClock $clock;
    protected function setUp(): void
    {
        $this->previousClock = Clock::get(); $this->clock = new MockClock('2030-05-01 12:00:00 UTC'); Clock::set($this->clock);
        parent::setUp(); $em = static::getContainer()->get(EntityManagerInterface::class);
        static::getContainer()->get(VoucherFixtures::class)->load($em); $em->clear();
        static::getContainer()->get('cache.rate_limiter')->clear();
    }
    protected function tearDown(): void
    {
        // Test-only synthetic fixture purge; production exposes no ledger deletion operation.
        if (static::$kernel !== null) {
            $em = static::getContainer()->get('doctrine')->getManager();
            foreach ([VoucherRedemption::class, Voucher::class, VoucherProduct::class] as $class) { $em->createQuery('DELETE FROM '.$class.' e')->execute(); }
            $em->clear();
        }
        Clock::set($this->previousClock); parent::tearDown();
    }
    protected function voucher(int $index): Voucher
    {
        $v = static::getContainer()->get('doctrine')->getRepository(Voucher::class)->findOneBy(['code' => VoucherFixtures::code($index)]); self::assertInstanceOf(Voucher::class, $v); return $v;
    }
}
