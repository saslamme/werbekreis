<?php

declare(strict_types=1);
namespace App\Tests\Functional;
use App\DataFixtures\MemberFixtures;
use App\Entity\{ContentRevision, Event, JobPosting, NewsArticle, Offer};
use App\Enum\ContentType;
use Symfony\Component\Clock\{Clock, ClockInterface, MockClock};
abstract class MemberDatabaseTestCase extends PublicDirectoryTestCase
{
    protected MockClock $clock;
    private ClockInterface $previousClock;
    protected function setUp(): void
    {
        $this->previousClock=Clock::get(); $this->clock=new MockClock('2030-05-01 12:00 UTC'); Clock::set($this->clock); parent::setUp();
        $em=static::getContainer()->get('doctrine')->getManager(); static::getContainer()->get(MemberFixtures::class)->load($em); $em->clear();
    }
    protected function tearDown(): void
    {
        if (static::$kernel!==null) { $em=static::getContainer()->get('doctrine')->getManager(); foreach ([ContentRevision::class,Offer::class,Event::class,NewsArticle::class,JobPosting::class] as $class) { $em->createQuery('DELETE FROM '.$class.' e')->execute(); } $em->clear(); }
        Clock::set($this->previousClock); parent::tearDown();
    }
    protected function content(ContentType $type, string $state='draft'): object
    {
        $entity=static::getContainer()->get('doctrine')->getRepository($type->entityClass())->findOneBy(['title'=>'TEST '.$type->value.' '.$state]); self::assertNotNull($entity); return $entity;
    }
}
