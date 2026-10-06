<?php

declare(strict_types=1);
namespace App\Repository;

use App\Entity\Company;
use App\Entity\Event;
use App\Entity\EventCategory;
use App\Entity\EventOccurrence;
use App\Service\EventDateRangeResolver;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Event> */
final class EventRepository extends ServiceEntityRepository
{
    public const PAGE_SIZE = 12;
    public const ADMIN_PAGE_SIZE = 25;
    public function __construct(ManagerRegistry $registry, private readonly EventDateRangeResolver $dates) { parent::__construct($registry, Event::class); }
    private function publicOccurrences(?EventCategory $category = null, ?Company $company = null): QueryBuilder
    {
        $query = $this->getEntityManager()->createQueryBuilder()->select('occurrence', 'event', 'company', 'categories')->from(EventOccurrence::class, 'occurrence')
            ->innerJoin('occurrence.event', 'event')->leftJoin('event.company', 'company')->leftJoin('event.categories', 'categories')
            ->where('event.active = true', "event.moderationStatus = 'approved'", "company.id IS NULL OR company.moderationStatus = 'approved'")->orderBy('occurrence.startsAt', 'ASC')->addOrderBy('event.featured', 'DESC')->addOrderBy('event.title', 'ASC')->addOrderBy('occurrence.id', 'ASC');
        if ($category !== null) { $query->andWhere(':category MEMBER OF event.categories')->setParameter('category', $category); }
        if ($company !== null) { $query->andWhere('event.company = :company')->setParameter('company', $company); }
        return $query;
    }
    public function createUpcomingPublicQueryBuilder(?EventCategory $category = null, ?Company $company = null): QueryBuilder
    {
        return $this->publicOccurrences($category, $company)->andWhere('occurrence.endsAt >= :now')->setParameter('now', $this->dates->now(), 'datetime_immutable');
    }
    private function range(QueryBuilder $query, ?\DateTimeImmutable $start, ?\DateTimeImmutable $end): QueryBuilder
    {
        if ($start !== null) { $query->andWhere('occurrence.endsAt >= :start')->setParameter('start', $start, 'datetime_immutable'); }
        if ($end !== null) { $query->andWhere('occurrence.startsAt < :end')->setParameter('end', $end, 'datetime_immutable'); }
        return $query;
    }
    public function publicPage(int $page, string $period, ?EventCategory $category = null, ?Company $company = null): Paginator
    {
        [$start, $end] = $this->dates->resolve($period);
        return new Paginator($this->range($this->createUpcomingPublicQueryBuilder($category, $company), $start, $end)->setFirstResult((max(1, $page) - 1) * self::PAGE_SIZE)->setMaxResults(self::PAGE_SIZE));
    }
    public function findUpcoming(int $limit = 3): array
    {
        return iterator_to_array(new Paginator($this->createUpcomingPublicQueryBuilder()->setMaxResults(max(1, $limit))));
    }
    /** Featured selection, ordinary fallback, then chronological presentation. */
    public function findUpcomingFeatured(int $limit = 3): array
    {
        $query = $this->createUpcomingPublicQueryBuilder()->orderBy('event.featured', 'DESC')->addOrderBy('occurrence.startsAt', 'ASC')->addOrderBy('occurrence.id', 'ASC')->setMaxResults(max(1, $limit));
        $items = iterator_to_array(new Paginator($query));
        usort($items, static fn (EventOccurrence $a, EventOccurrence $b): int => ($a->getStartsAt() <=> $b->getStartsAt()) ?: ($a->getId() <=> $b->getId()));
        return $items;
    }
    public function findUpcomingForCompany(Company $company, int $limit = 4): array
    {
        return iterator_to_array(new Paginator($this->createUpcomingPublicQueryBuilder(company: $company)->setMaxResults(max(1, $limit))));
    }
    public function findForDateRange(\DateTimeImmutable $start, \DateTimeImmutable $end, ?EventCategory $category = null, ?Company $company = null): array
    {
        return $this->range($this->publicOccurrences($category, $company), $start, $end)->getQuery()->getResult();
    }
    public function findForMonth(\DateTimeImmutable $month, ?EventCategory $category = null, ?Company $company = null): array
    {
        return $this->findForDateRange($month->modify('monday this week'), $month->modify('last day of this month')->modify('sunday this week')->modify('+1 day'), $category, $company);
    }
    public function findPublicBySlug(string $slug): ?Event
    {
        return $this->createQueryBuilder('event')->addSelect('company', 'categories', 'occurrences')->leftJoin('event.company', 'company')->leftJoin('event.categories', 'categories')->leftJoin('event.occurrences', 'occurrences')
            ->where('event.active = true', 'event.slug = :slug', "event.moderationStatus = 'approved'", "company.id IS NULL OR company.moderationStatus = 'approved'")->setParameter('slug', $slug)->orderBy('occurrences.startsAt', 'ASC')->getQuery()->getOneOrNullResult();
    }
    public function upcomingCount(bool $featured = false, bool $month = false): int
    {
        $query = $this->createUpcomingPublicQueryBuilder()->select('COUNT(DISTINCT occurrence.id)')->resetDQLPart('orderBy');
        if ($featured) { $query->andWhere('event.featured = true'); }
        if ($month) { [$start, $end] = $this->dates->resolve('month'); $this->range($query, $start, $end); }
        return (int) $query->getQuery()->getSingleScalarResult();
    }
    public function adminPage(array $filters, int $page): Paginator
    {
        $query = $this->createQueryBuilder('event')->addSelect('company', 'categories', 'occurrences')->leftJoin('event.company', 'company')->leftJoin('event.categories', 'categories')->leftJoin('event.occurrences', 'occurrences');
        foreach (['active', 'featured'] as $key) { if (in_array($filters[$key] ?? '', ['0', '1'], true)) { $query->andWhere('event.'.$key.' = :'.$key)->setParameter($key, $filters[$key] === '1'); } }
        if (($filters['title'] ?? '') !== '') { $query->andWhere('LOWER(event.title) LIKE :title')->setParameter('title', '%'.addcslashes(mb_strtolower(mb_substr($filters['title'], 0, 180)), '%_\\').'%'); }
        if (ctype_digit($filters['company'] ?? '')) { $query->andWhere('company.id = :companyId')->setParameter('companyId', (int) $filters['company']); }
        if (ctype_digit($filters['category'] ?? '')) { $query->innerJoin('event.categories', 'filterCategory')->andWhere('filterCategory.id = :categoryId')->setParameter('categoryId', (int) $filters['category']); }
        // A separate join filters matching dates while the fetch join retains the complete series.
        if (($filters['status'] ?? '') !== '' || ($filters['period'] ?? 'upcoming') !== 'upcoming') {
            $query->innerJoin('event.occurrences', 'matchDate');
            $now = $this->dates->now();
            $condition = match ($filters['status'] ?? '') {
                'planned' => 'event.active = true AND event.cancelled = false AND matchDate.startsAt > :now AND NOT EXISTS (SELECT running.id FROM App\\Entity\\EventOccurrence running WHERE running.event = event AND running.startsAt <= :now AND running.endsAt >= :now)',
                'running' => 'event.active = true AND event.cancelled = false AND matchDate.startsAt <= :now AND matchDate.endsAt >= :now',
                'past' => 'event.active = true AND event.cancelled = false AND NOT EXISTS (SELECT future.id FROM App\\Entity\\EventOccurrence future WHERE future.event = event AND future.endsAt >= :now)',
                'disabled' => 'event.active = false', 'cancelled' => 'event.active = true AND event.cancelled = true', default => null,
            };
            if ($condition !== null) { $query->andWhere($condition); if (str_contains($condition, ':now')) { $query->setParameter('now', $now, 'datetime_immutable'); } }
            [$start, $end] = $this->dates->resolve($filters['period'] ?? 'upcoming');
            if ($start !== null) { $query->andWhere('matchDate.endsAt >= :rangeStart')->setParameter('rangeStart', $start, 'datetime_immutable'); }
            if ($end !== null) { $query->andWhere('matchDate.startsAt < :rangeEnd')->setParameter('rangeEnd', $end, 'datetime_immutable'); }
        }
        return new Paginator($query->orderBy('event.updatedAt', 'DESC')->addOrderBy('event.id', 'DESC')->setFirstResult((max(1, $page) - 1) * self::ADMIN_PAGE_SIZE)->setMaxResults(self::ADMIN_PAGE_SIZE));
    }
    public function existingSlugs(string $base, ?int $excludeId): array
    {
        $query = $this->createQueryBuilder('event')->select('event.slug')->where('event.slug = :base OR event.slug LIKE :prefix')->setParameter('base', $base)->setParameter('prefix', $base.'-%');
        if ($excludeId !== null) { $query->andWhere('event.id != :id')->setParameter('id', $excludeId); }
        return array_column($query->getQuery()->getScalarResult(), 'slug');
    }
}
