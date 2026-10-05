<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\DataFixtures\NewsFixtures;
use App\Entity\NewsArticle;
use App\Repository\NewsArticleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\{Clock, ClockInterface, MockClock};

abstract class NewsDatabaseTestCase extends PublicDirectoryTestCase
{
    protected MockClock $clock;
    private ClockInterface $previousClock;
    protected function setUp(): void
    {
        $this->previousClock = Clock::get(); $this->clock = new MockClock('2030-05-01 12:00Z'); Clock::set($this->clock);
        parent::setUp(); $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->createQuery('DELETE FROM App\Entity\NewsArticle article')->execute(); $em->createQuery('DELETE FROM App\Entity\NewsCategory category')->execute(); $em->clear();
        static::getContainer()->get(NewsFixtures::class)->load($em);
    }
    protected function tearDown(): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        if ($em->isOpen()) { $em->createQuery('DELETE FROM App\Entity\NewsArticle article')->execute(); $em->createQuery('DELETE FROM App\Entity\NewsCategory category')->execute(); }
        parent::tearDown(); Clock::set($this->previousClock);
    }
    protected function article(string $slug = 'gemeinsam-vor-ort'): NewsArticle
    {
        $article = static::getContainer()->get(NewsArticleRepository::class)->findOneBy(['slug' => $slug]); self::assertInstanceOf(NewsArticle::class, $article); return $article;
    }
    protected function newsTitles(): array
    {
        return $this->client->getCrawler()->filter('.news-card-title')->each(static fn ($node): string => trim($node->text()));
    }
}
