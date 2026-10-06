<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\{Company, JobPosting};
use App\Enum\{EmploymentType, WorkModel, NewsStatus};
use App\Service\NewsPublishing;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<JobPosting> */
final class JobPostingRepository extends ServiceEntityRepository
{
    public const PAGE_SIZE = 12;
    public const ADMIN_PAGE_SIZE = 25;

    public function __construct(ManagerRegistry $registry, private readonly NewsPublishing $publishing)
    {
        parent::__construct($registry, JobPosting::class);
    }

    /** One publication/expiry predicate shared by overview, details, homepage and Company cards. */
    public function createPublicQueryBuilder(?Company $company = null): QueryBuilder
    {
        $query = $this->createQueryBuilder('job')->innerJoin('job.company', 'company')
            ->where('company.active = true', "job.moderationStatus = 'approved'", "company.moderationStatus = 'approved'")->andWhere('job.status IN (:statuses)')
            ->andWhere('job.status != :scheduled OR job.publishedAt IS NOT NULL')
            ->andWhere('job.publishedAt IS NULL OR job.publishedAt <= :now')
            ->andWhere('job.validThrough IS NULL OR job.validThrough >= :now')
            ->setParameter('statuses', NewsStatus::publishableValues())->setParameter('scheduled', NewsStatus::Scheduled->value)
            ->setParameter('now', $this->publishing->now(), 'datetime_immutable');
        if ($company !== null) { $query->andWhere('job.company = :company')->setParameter('company', $company); }
        return $query->addSelect('COALESCE(job.publishedAt, job.createdAt) AS HIDDEN publicationOrder')
            ->orderBy('job.featured', 'DESC')->addOrderBy('publicationOrder', 'DESC')->addOrderBy('job.title', 'ASC')->addOrderBy('job.id', 'DESC');
    }

    public function createSearchQueryBuilder(array $filters, ?Company $company = null): QueryBuilder
    {
        $query = $this->createPublicQueryBuilder($company);
        $this->applyEmploymentFilters($query, $filters);
        $search = mb_substr(trim($filters['q'] ?? ''), 0, 100);
        if ($search !== '') {
            $query->andWhere('LOWER(job.title) LIKE :search OR LOWER(job.shortDescription) LIKE :search OR LOWER(job.description) LIKE :search OR LOWER(company.name) LIKE :search OR LOWER(job.city) LIKE :search')
                ->setParameter('search', '%'.addcslashes(mb_strtolower($search), '%_\\').'%');
        }
        return $query;
    }

    /** Scalar cards avoid loading descriptions or complete Company graphs. @return list<array<string, mixed>> */
    private function cards(QueryBuilder $query): array
    {
        $rows = $query->select('job.id AS id', 'job.title AS title', 'job.slug AS slug', 'job.shortDescription AS shortDescription',
            'job.featured AS featured', 'job.employmentType AS employmentType', 'job.workModel AS workModel', 'job.city AS city',
            'job.locationName AS locationName', 'job.publishedAt AS publishedAt', 'job.createdAt AS createdAt', 'job.validThrough AS validThrough',
            'job.salaryMin AS salaryMin', 'job.salaryMax AS salaryMax', 'job.salaryPeriod AS salaryPeriod',
            'company.name AS companyName', 'company.slug AS companySlug', 'COALESCE(job.publishedAt, job.createdAt) AS HIDDEN publicationOrder')
            ->getQuery()->getArrayResult();
        foreach ($rows as &$row) {
            $row['company'] = ['name' => $row['companyName'], 'slug' => $row['companySlug']];
            $row['publicationDate'] = $row['publishedAt'] ?? $row['createdAt'];
        }
        unset($row);
        return $rows;
    }

    /** @return array{rows: list<array<string, mixed>>, total: int} */
    public function publicPage(array $filters, int $page, ?Company $company = null): array
    {
        $query = $this->createSearchQueryBuilder($filters, $company); $count = clone $query;
        $total = (int) $count->select('COUNT(job.id)')->resetDQLPart('orderBy')->getQuery()->getSingleScalarResult();
        return ['rows' => $this->cards($query->setFirstResult((max(1, $page) - 1) * self::PAGE_SIZE)->setMaxResults(self::PAGE_SIZE)), 'total' => $total];
    }

    public function findLatestPublic(int $limit = 3): array
    {
        return $this->cards($this->createPublicQueryBuilder()->orderBy('publicationOrder', 'DESC')->addOrderBy('job.title', 'ASC')->setMaxResults(max(1, $limit)));
    }
    public function findFeaturedPublic(int $limit = 3): array
    {
        return $this->cards($this->createPublicQueryBuilder()->setMaxResults(max(1, $limit)));
    }
    public function findForCompany(Company $company, int $limit = 4): array
    {
        return $this->cards($this->createPublicQueryBuilder($company)->setMaxResults(max(1, $limit)));
    }
    public function findPublicBySlug(string $slug): ?JobPosting
    {
        return $this->createPublicQueryBuilder()->addSelect('company')->andWhere('job.slug = :slug')->setParameter('slug', $slug)->getQuery()->getOneOrNullResult();
    }

    /** @return Paginator<JobPosting> */
    public function adminPage(array $filters, int $page): Paginator
    {
        $query = $this->createQueryBuilder('job')->addSelect('company')->innerJoin('job.company', 'company');
        $this->applyEmploymentFilters($query, $filters);
        if (($filters['title'] ?? '') !== '') { $query->andWhere('LOWER(job.title) LIKE :title')->setParameter('title', '%'.addcslashes(mb_strtolower(mb_substr(trim($filters['title']), 0, 180)), '%_\\').'%'); }
        if (ctype_digit($filters['company'] ?? '')) { $query->andWhere('company.id = :company')->setParameter('company', (int) $filters['company']); }
        if (($status = NewsStatus::tryFrom($filters['status'] ?? '')) !== null) { $query->andWhere('job.status = :status')->setParameter('status', $status->value); }
        if (in_array($filters['featured'] ?? '', ['0', '1'], true)) { $query->andWhere('job.featured = :featured')->setParameter('featured', $filters['featured'] === '1'); }
        return new Paginator($query->orderBy('job.updatedAt', 'DESC')->addOrderBy('job.id', 'DESC')
            ->setFirstResult((max(1, $page) - 1) * self::ADMIN_PAGE_SIZE)->setMaxResults(self::ADMIN_PAGE_SIZE), false);
    }

    private function applyEmploymentFilters(QueryBuilder $query, array $filters): void
    {
        if (($type = EmploymentType::tryFrom($filters['employmentType'] ?? '')) !== null) { $query->andWhere('job.employmentType = :employment')->setParameter('employment', $type->value); }
        if (($model = WorkModel::tryFrom($filters['workModel'] ?? '')) !== null) { $query->andWhere('job.workModel = :model')->setParameter('model', $model->value); }
    }

    public function countPublic(): int
    {
        return (int) $this->createPublicQueryBuilder()->select('COUNT(job.id)')->resetDQLPart('orderBy')->getQuery()->getSingleScalarResult();
    }
    public function countUpcoming(): int
    {
        return (int) $this->createQueryBuilder('job')->select('COUNT(job.id)')->innerJoin('job.company', 'company')->where('company.active = true', 'job.status IN (:statuses)', 'job.publishedAt > :now', "job.moderationStatus = 'approved'", "company.moderationStatus = 'approved'")
            ->andWhere('job.validThrough IS NULL OR job.validThrough >= job.publishedAt')->setParameter('statuses', NewsStatus::publishableValues())->setParameter('now', $this->publishing->now(), 'datetime_immutable')->getQuery()->getSingleScalarResult();
    }
    public function countExpiring(): int
    {
        return (int) $this->createPublicQueryBuilder()->select('COUNT(job.id)')->resetDQLPart('orderBy')->andWhere('job.validThrough <= :soon')
            ->setParameter('soon', $this->publishing->now()->modify('+7 days'), 'datetime_immutable')->getQuery()->getSingleScalarResult();
    }
    public function existingSlugs(string $base, ?int $excludeId): array
    {
        $query = $this->createQueryBuilder('job')->select('job.slug')->where('job.slug = :base OR job.slug LIKE :prefix')->setParameter('base', $base)->setParameter('prefix', $base.'-%');
        if ($excludeId !== null) { $query->andWhere('job.id != :id')->setParameter('id', $excludeId); }
        return array_column($query->getQuery()->getScalarResult(), 'slug');
    }
}
