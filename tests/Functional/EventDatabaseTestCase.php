<?php

declare(strict_types=1);
namespace App\Tests\Functional;
use App\DataFixtures\EventFixtures;
use App\Entity\Event;
use App\Repository\EventRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\{Clock, ClockInterface, MockClock};

abstract class EventDatabaseTestCase extends PublicDirectoryTestCase
{
    protected MockClock $clock;
    private ClockInterface $previousClock;
    protected function setUp(): void
    {
        $this->previousClock = Clock::get(); $this->clock = new MockClock('2030-05-01 12:00Z'); Clock::set($this->clock);
        parent::setUp();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->createQuery('DELETE FROM App\Entity\Event event')->execute(); $em->createQuery('DELETE FROM App\Entity\EventCategory category')->execute(); $em->clear();
        static::getContainer()->get(EventFixtures::class)->load($em);
    }
    protected function tearDown(): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        if ($em->isOpen()) { $em->createQuery('DELETE FROM App\Entity\Event event')->execute(); $em->createQuery('DELETE FROM App\Entity\EventCategory category')->execute(); }
        parent::tearDown(); Clock::set($this->previousClock);
    }
    protected function event(string $slug = 'beispielabend-heute'): Event
    {
        $event = static::getContainer()->get(EventRepository::class)->findOneBy(['slug' => $slug]); self::assertInstanceOf(Event::class, $event); return $event;
    }
    protected function eventTitles(): array { return $this->client->getCrawler()->filter('.event-card-title')->each(static fn ($node): string => trim($node->text())); }
}
