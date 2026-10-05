<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\{Company, NewsArticle, NewsCategory};
use App\Enum\NewsStatus;
use App\Service\NewsPublishing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Constraints\Callback;

final class NewsArticleTest extends TestCase
{
    public static function visibility(): iterable
    {
        yield [NewsStatus::Draft, null, false, 'Entwurf'];
        yield [NewsStatus::Draft, '2030-04-01', false, 'Entwurf'];
        yield [NewsStatus::Draft, '2031-01-01', false, 'Entwurf'];
        yield [NewsStatus::Scheduled, null, false, 'Geplant'];
        yield [NewsStatus::Scheduled, '2030-05-01 12:00:01Z', false, 'Geplant'];
        yield [NewsStatus::Scheduled, '2030-05-01 12:00:00Z', true, 'Veröffentlicht'];
        yield [NewsStatus::Scheduled, '2030-04-01', true, 'Veröffentlicht'];
        yield [NewsStatus::Published, null, true, 'Veröffentlicht'];
        yield [NewsStatus::Published, '2030-05-01 12:00:01Z', false, 'Geplant'];
        yield [NewsStatus::Published, '2030-05-01 12:00:00Z', true, 'Veröffentlicht'];
        yield [NewsStatus::Published, '2030-04-01', true, 'Veröffentlicht'];
    }
    #[DataProvider('visibility')]
    public function testPublicationBoundaries(NewsStatus $status, ?string $date, bool $visible, string $label): void
    {
        $article = (new NewsArticle())->setStatus($status)->setPublishedAt($date ? new \DateTimeImmutable($date) : null); $clock = new MockClock('2030-05-01 12:00Z');
        self::assertSame($visible, $article->isPubliclyVisible($clock->now())); self::assertSame($label, $article->statusAt($clock->now()));
    }
    public function testClockAdvancePublishesScheduledArticleWithoutMutatingStoredStatus(): void
    {
        $clock = new MockClock('2030-05-01 12:00Z'); $article = (new NewsArticle())->setStatus(NewsStatus::Scheduled)->setPublishedAt($clock->now()->modify('+1 minute'));
        self::assertFalse($article->isPubliclyVisible($clock->now())); $clock->modify('+1 minute'); self::assertTrue($article->isPubliclyVisible($clock->now())); self::assertSame(NewsStatus::Scheduled, $article->getStatus());
    }
    public function testPublishWithoutTimestampUsesClockAndPreservesExplicitDates(): void
    {
        $clock = new MockClock('2030-05-01 12:00Z'); $publishing = new NewsPublishing($clock); $article = (new NewsArticle($clock->now()))->setStatus(NewsStatus::Published);
        $publishing->prepareForSave($article); self::assertEquals($clock->now(), $article->getPublishedAt()); self::assertEquals($clock->now(), $article->getPublicationDate());
        $explicit = new \DateTimeImmutable('2030-04-01'); $article->setPublishedAt($explicit); $publishing->prepareForSave($article); self::assertSame($explicit, $article->getPublishedAt());
        $draft = new NewsArticle($clock->now()); $publishing->prepareForSave($draft); self::assertNull($draft->getPublishedAt()); self::assertSame($draft->getCreatedAt(), $draft->getPublicationDate());
    }
    public function testNewSchedulingRequiresFutureAndValidationRequiresDate(): void
    {
        $clock = new MockClock('2030-05-01 12:00Z'); $publishing = new NewsPublishing($clock); $article = (new NewsArticle())->setStatus(NewsStatus::Scheduled);
        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
        $errors = Validation::createValidator()->validate($article, new Callback('validatePublication')); self::assertContains('publishedAt', array_map(static fn ($e) => $e->getPropertyPath(), iterator_to_array($errors)));
        $article->setPublishedAt($clock->now()); self::assertTrue($publishing->invalidScheduleChange($article, NewsStatus::Draft, null));
        $article->setPublishedAt($clock->now()->modify('+1 second')); self::assertFalse($publishing->invalidScheduleChange($article, NewsStatus::Draft, null));
    }
    public function testModificationTimestampUsesTheSameClock(): void
    {
        $previous = \Symfony\Component\Clock\Clock::get(); $clock = new MockClock('2030-05-01 12:00Z');
        \Symfony\Component\Clock\Clock::set($clock);
        try {
            $article = new NewsArticle(); self::assertEquals($clock->now(), $article->getCreatedAt());
            $clock->modify('+1 hour'); $article->touch(); self::assertEquals($clock->now(), $article->getUpdatedAt());
        } finally { \Symfony\Component\Clock\Clock::set($previous); }
    }

    public function testCompanyAndCategoryHelpersMaintainBothSides(): void
    {
        $a = new Company(); $b = new Company(); $article = new NewsArticle(); $category = new NewsCategory();
        $a->addNewsArticle($article); self::assertSame($a, $article->getCompany()); $article->setCompany($b); self::assertCount(0, $a->getNewsArticles()); self::assertTrue($b->getNewsArticles()->contains($article));
        $b->removeNewsArticle($article); self::assertNull($article->getCompany());
        $category->addArticle($article); self::assertTrue($article->getCategories()->contains($category)); $article->addCategory($category); self::assertCount(1, $category->getArticles());
        $category->setActive(false); self::assertSame([], $article->getPublicCategories()); $article->removeCategory($category); self::assertCount(0, $category->getArticles());
    }
    public function testContentTitleAndTeaserValidation(): void
    {
        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator(); $article = (new NewsArticle())->setTitle('')->setContent('')->setTeaser(str_repeat('x', 501));
        foreach (['title', 'content', 'teaser'] as $path) { self::assertCount(1, $validator->validateProperty($article, $path)); }
        self::assertSame('Entwurf', NewsStatus::Draft->label()); self::assertSame('Geplant', NewsStatus::Scheduled->label()); self::assertSame('Veröffentlicht', NewsStatus::Published->label());
    }
}
