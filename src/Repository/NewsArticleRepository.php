<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Company;
use App\Entity\NewsArticle;
use App\Entity\NewsCategory;
use App\Enum\NewsStatus;
use App\Service\NewsPublishing;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<NewsArticle> */
final class NewsArticleRepository extends ServiceEntityRepository
{
    public const PAGE_SIZE = 12;
    public const ADMIN_PAGE_SIZE = 25;

    public function __construct(ManagerRegistry $registry, private readonly NewsPublishing $publishing)
    {
        parent::__construct($registry, NewsArticle::class);
    }

    /** Central publication predicate for every public page, card and image lookup. */
    public function createPublicQueryBuilder(?NewsCategory $category = null, ?Company $company = null): QueryBuilder
    {
        $query = $this->createQueryBuilder('article')->leftJoin('article.company', 'company')
            ->where('article.status IN (:publicStatuses)')
            ->andWhere('article.status != :scheduled OR article.publishedAt IS NOT NULL')
            ->andWhere('article.publishedAt IS NULL OR article.publishedAt <= :now')
            ->setParameter('publicStatuses', NewsStatus::publishableValues())->setParameter('scheduled', NewsStatus::Scheduled->value)
            ->setParameter('now', $this->publishing->now(), 'datetime_immutable');
        if ($category !== null) { $query->andWhere(':category MEMBER OF article.categories')->setParameter('category', $category); }
        if ($company !== null) { $query->andWhere('article.company = :company')->setParameter('company', $company); }

        return $query->addSelect('COALESCE(article.publishedAt, article.createdAt) AS HIDDEN publicationOrder')
            ->orderBy('publicationOrder', 'DESC')->addOrderBy('article.createdAt', 'DESC')->addOrderBy('article.id', 'DESC');
    }

    /** Scalar card projection + one bounded category query: no content or partial managed entities. @return list<array<string, mixed>> */
    private function cards(QueryBuilder $query): array
    {
        $rows = $query->select('article.id AS id', 'article.title AS title', 'article.slug AS slug', 'article.teaser AS teaser', 'article.featured AS featured',
            'article.imagePath AS imagePath', 'article.imageAltText AS imageAltText', 'article.publishedAt AS publishedAt', 'article.createdAt AS createdAt',
            'company.name AS companyName', 'company.slug AS companySlug', 'company.active AS companyActive',
            'COALESCE(article.publishedAt, article.createdAt) AS HIDDEN publicationOrder')->getQuery()->getArrayResult();
        if ($rows === []) { return []; }
        $categories = $this->getEntityManager()->createQueryBuilder()->select('article.id AS articleId', 'category.name AS name', 'category.slug AS slug')
            ->from(NewsArticle::class, 'article')->innerJoin('article.categories', 'category')->where('article.id IN (:ids)', 'category.active = true')
            ->setParameter('ids', array_column($rows, 'id'))->orderBy('category.position', 'ASC')->addOrderBy('category.name', 'ASC')->getQuery()->getArrayResult();
        $byArticle = [];
        foreach ($categories as $category) { $byArticle[$category['articleId']][] = ['name' => $category['name'], 'slug' => $category['slug']]; }
        foreach ($rows as &$row) {
            $date = $row['publishedAt'] ?? $row['createdAt'];
            $row['publicationDate'] = $date instanceof \DateTimeImmutable ? $date : new \DateTimeImmutable((string) $date, new \DateTimeZone('UTC'));
            $row['publicCategories'] = $byArticle[$row['id']] ?? [];
            $row['company'] = $row['companyName'] === null ? null : ['name' => $row['companyName'], 'slug' => $row['companySlug'], 'active' => (bool) $row['companyActive']];
        }
        unset($row);

        return $rows;
    }

    /** @return array{rows: list<array<string, mixed>>, total: int} */
    public function publicPage(int $page, ?NewsCategory $category = null, ?Company $company = null): array
    {
        $query = $this->createPublicQueryBuilder($category, $company);
        $count = clone $query;
        $total = (int) $count->select('COUNT(article.id)')->resetDQLPart('orderBy')->getQuery()->getSingleScalarResult();
        return ['rows' => $this->cards($query->setFirstResult((max(1, $page) - 1) * self::PAGE_SIZE)->setMaxResults(self::PAGE_SIZE)), 'total' => $total];
    }

    /** @return list<array<string, mixed>> */
    public function findLatestPublic(int $limit = 3): array
    {
        return $this->cards($this->createPublicQueryBuilder()->setMaxResults(max(1, $limit)));
    }
    /** Featured selection with recent ordinary fallback, displayed chronologically. @return list<array<string, mixed>> */
    public function findFeaturedPublic(int $limit = 3): array
    {
        $rows = $this->cards($this->createPublicQueryBuilder()->orderBy('article.featured', 'DESC')->addOrderBy('publicationOrder', 'DESC')->addOrderBy('article.createdAt', 'DESC')->addOrderBy('article.id', 'DESC')->setMaxResults(max(1, $limit)));
        usort($rows, static fn (array $a, array $b): int => ($b['publicationDate'] <=> $a['publicationDate']) ?: ($b['createdAt'] <=> $a['createdAt']) ?: ($b['id'] <=> $a['id']));
        return $rows;
    }
    /** @return list<array<string, mixed>> */
    public function findForCompany(Company $company, int $limit = 4): array
    {
        return $this->cards($this->createPublicQueryBuilder(company: $company)->setMaxResults(max(1, $limit)));
    }
    /** @return array{rows: list<array<string, mixed>>, total: int} */
    public function findByCategory(NewsCategory $category, int $page = 1): array
    {
        return $this->publicPage($page, $category);
    }
    public function findPublicBySlug(string $slug): ?NewsArticle
    {
        return $this->createPublicQueryBuilder()->addSelect('company', 'categories')->leftJoin('article.categories', 'categories')
            ->andWhere('article.slug = :slug')->setParameter('slug', $slug)->getQuery()->getOneOrNullResult();
    }
    /** @return Paginator<NewsArticle> */
    public function adminPage(array $filters, int $page): Paginator
    {
        $query = $this->createQueryBuilder('article')->addSelect('company', 'categories')->leftJoin('article.company', 'company')->leftJoin('article.categories', 'categories');
        if (($filters['title'] ?? '') !== '') { $query->andWhere('LOWER(article.title) LIKE :title')->setParameter('title', '%'.addcslashes(mb_strtolower(mb_substr(trim($filters['title']), 0, 180)), '%_\\').'%'); }
        if (ctype_digit($filters['company'] ?? '')) { $query->andWhere('company.id = :company')->setParameter('company', (int) $filters['company']); }
        if (ctype_digit($filters['category'] ?? '')) { $query->innerJoin('article.categories', 'filterCategory')->andWhere('filterCategory.id = :category')->setParameter('category', (int) $filters['category']); }
        if (($status = NewsStatus::tryFrom($filters['status'] ?? '')) !== null) { $query->andWhere('article.status = :status')->setParameter('status', $status->value); }
        if (in_array($filters['featured'] ?? '', ['0', '1'], true)) { $query->andWhere('article.featured = :featured')->setParameter('featured', $filters['featured'] === '1'); }
        return new Paginator($query->orderBy('article.updatedAt', 'DESC')->addOrderBy('article.id', 'DESC')->setFirstResult((max(1, $page) - 1) * self::ADMIN_PAGE_SIZE)->setMaxResults(self::ADMIN_PAGE_SIZE));
    }
    public function countPublic(): int
    {
        return (int) $this->createPublicQueryBuilder()->select('COUNT(article.id)')->resetDQLPart('orderBy')->getQuery()->getSingleScalarResult();
    }
    /** @return list<string> */
    public function existingSlugs(string $base, ?int $excludeId): array
    {
        $query = $this->createQueryBuilder('article')->select('article.slug')->where('article.slug = :base OR article.slug LIKE :prefix')->setParameter('base', $base)->setParameter('prefix', $base.'-%');
        if ($excludeId !== null) { $query->andWhere('article.id != :id')->setParameter('id', $excludeId); }
        return array_column($query->getQuery()->getScalarResult(), 'slug');
    }
}
