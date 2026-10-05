<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\DataFixtures\OfferFixtures;
use App\Entity\Offer;
use App\Repository\OfferRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Clock\MockClock;

abstract class OfferDatabaseTestCase extends PublicDirectoryTestCase
{
    protected MockClock $clock;
    private ClockInterface $previousClock;

    protected function setUp(): void
    {
        $this->previousClock = Clock::get();
        $this->clock = new MockClock('2030-05-01 12:00:00Z');
        Clock::set($this->clock);
        parent::setUp();
        static::getContainer()->get(OfferFixtures::class)->load(static::getContainer()->get(EntityManagerInterface::class));
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Clock::set($this->previousClock);
    }

    protected function offer(string $slug = 'lieblingsstuecke-zum-aktionspreis'): Offer
    {
        $offer = static::getContainer()->get(OfferRepository::class)->findOneBy(['slug' => $slug]);
        self::assertInstanceOf(Offer::class, $offer);

        return $offer;
    }

    protected function offerTitles(): array
    {
        return $this->client->getCrawler()->filter('.offer-card-title')->each(static fn ($node): string => trim($node->text()));
    }
}
