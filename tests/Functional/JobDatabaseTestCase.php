<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\DataFixtures\JobFixtures;
use App\Entity\JobPosting;
use App\Repository\JobPostingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\{Clock, ClockInterface, MockClock};

abstract class JobDatabaseTestCase extends PublicDirectoryTestCase
{
    protected MockClock $clock;
    private ClockInterface $previousClock;
    protected function setUp(): void
    {
        $this->previousClock = Clock::get(); $this->clock = new MockClock('2030-05-01 12:00Z'); Clock::set($this->clock);
        parent::setUp(); $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->createQuery('DELETE FROM App\Entity\JobPosting job')->execute(); $em->clear(); static::getContainer()->get(JobFixtures::class)->load($em);
    }
    protected function tearDown(): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class); if ($em->isOpen()) { $em->createQuery('DELETE FROM App\Entity\JobPosting job')->execute(); }
        parent::tearDown(); Clock::set($this->previousClock);
    }
    protected function job(string $slug = 'verkaeufer-im-einzelhandel-m-w-d'): JobPosting
    {
        $job = static::getContainer()->get(JobPostingRepository::class)->findOneBy(['slug' => $slug]); self::assertInstanceOf(JobPosting::class, $job); return $job;
    }
    protected function jobTitles(): array
    {
        return $this->client->getCrawler()->filter('.job-card-title')->each(static fn ($node): string => trim($node->text()));
    }
}
