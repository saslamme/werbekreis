<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Category;
use App\Entity\Company;
use App\Entity\CompanyImage;
use App\Geo\GeoPoint;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Company> */
final class CompanyRepository extends ServiceEntityRepository
{
    public const PAGE_SIZE = 25;
    public const PUBLIC_PAGE_SIZE = 12;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Company::class);
    }

    /** @return Paginator<Company> */
    public function adminPage(string $name, ?int $category, ?bool $active, ?bool $featured, int $page, string $sort): Paginator
    {
        $query = $this->createQueryBuilder('company')->leftJoin('company.categories', 'category')->addSelect('category');
        if ($name !== '') {
            $query->andWhere('LOWER(company.name) LIKE :name')->setParameter('name', '%'.mb_strtolower($name).'%');
        }
        if ($category !== null) {
            $query->innerJoin('company.categories', 'filterCategory')->andWhere('filterCategory.id = :category')->setParameter('category', $category);
        }
        if ($active !== null) {
            $query->andWhere('company.active = :active')->setParameter('active', $active);
        }
        if ($featured !== null) {
            $query->andWhere('company.featured = :featured')->setParameter('featured', $featured);
        }
        $query->orderBy($sort === 'updated' ? 'company.updatedAt' : 'company.name', $sort === 'updated' ? 'DESC' : 'ASC')->addOrderBy('company.id', 'ASC');
        $query->setFirstResult((max(1, $page) - 1) * self::PAGE_SIZE)->setMaxResults(self::PAGE_SIZE);

        return new Paginator($query, fetchJoinCollection: true);
    }

    /**
     * Single source for every public company listing: only active companies, optional search over name,
     * descriptions, city and active category names, optional category, fixed order (featured first, then name).
     * Categories and images are fetch-joined for the cards; opening hours and contacts are not loaded.
     */
    public function createPublicDirectoryQueryBuilder(string $search = '', ?Category $category = null): QueryBuilder
    {
        $query = $this->createQueryBuilder('company')
            ->leftJoin('company.categories', 'category')->addSelect('category')
            ->leftJoin('company.images', 'image')->addSelect('image')
            ->where("company.active = true", "company.moderationStatus = 'approved'");
        $search = trim($search);
        if ($search !== '') {
            $categoryMatch = $this->getEntityManager()->createQueryBuilder()->select('searchCategory.id')->from(Category::class, 'searchCategory')
                ->innerJoin('searchCategory.companies', 'searchCompany')
                ->where('searchCompany = company', 'searchCategory.active = true', 'LOWER(searchCategory.name) LIKE :search');
            // Parameters are bound; LIKE wildcards in the input are escaped so "%" or "_" match literally.
            $query->andWhere($query->expr()->orX(
                'LOWER(company.name) LIKE :search',
                'LOWER(company.shortDescription) LIKE :search',
                'LOWER(company.description) LIKE :search',
                'LOWER(company.city) LIKE :search',
                $query->expr()->exists($categoryMatch->getDQL()),
            ))->setParameter('search', '%'.addcslashes(mb_strtolower($search), '%_\\').'%');
        }
        if ($category !== null) {
            $query->andWhere(':category MEMBER OF company.categories')->setParameter('category', $category);
        }

        return $query->orderBy('company.featured', 'DESC')->addOrderBy('company.name', 'ASC')->addOrderBy('company.id', 'ASC');
    }

    /** @return Paginator<Company> */
    public function publicDirectoryPage(string $search, ?Category $category, int $page, ?GeoPoint $point = null, int $radius = 10): Paginator
    {
        $query = $this->createPublicDirectoryQueryBuilder($search, $category)
            ->setFirstResult((max(1, $page) - 1) * self::PUBLIC_PAGE_SIZE)->setMaxResults(self::PUBLIC_PAGE_SIZE);

        if ($point !== null) {
            $distance = 'GEO_DISTANCE(company.latitude, company.longitude, :originLat, :originLng)';
            $query->andWhere('company.latitude BETWEEN -90 AND 90', 'company.longitude BETWEEN -180 AND 180')
                ->andWhere($distance.' <= :radius')
                ->setParameter('originLat', $point->latitude)->setParameter('originLng', $point->longitude)->setParameter('radius', $radius)
                ->addSelect($distance.' AS HIDDEN geoDistance')
                ->orderBy('geoDistance', 'ASC')->addOrderBy('company.featured', 'DESC')->addOrderBy('company.name', 'ASC')->addOrderBy('company.id', 'ASC');
        }

        return new Paginator($query, fetchJoinCollection: true);
    }

    /**
     * Homepage selection: active featured companies first, filled up with further active companies.
     *
     * @return list<Company>
     */
    public function findFeaturedPublic(int $limit = 6): array
    {
        return iterator_to_array(new Paginator($this->createPublicDirectoryQueryBuilder()->setMaxResults(max(1, $limit)), fetchJoinCollection: true), false);
    }

    /** Active company for the public detail page; opening hours and contacts are loaded lazily with one query each. */
    public function findPublicBySlug(string $slug): ?Company
    {
        return $this->createQueryBuilder('company')
            ->leftJoin('company.categories', 'category')->addSelect('category')
            ->leftJoin('company.images', 'image')->addSelect('image')
            ->where('company.slug = :slug', "company.active = true", "company.moderationStatus = 'approved'")->setParameter('slug', $slug)
            ->getQuery()->getOneOrNullResult();
    }

    /** Image of an active company, addressed by its random file name instead of a database ID. */
    public function findPublicImage(string $slug, string $fileName): ?CompanyImage
    {
        return $this->getEntityManager()->createQueryBuilder()->select('image')->from(CompanyImage::class, 'image')
            ->innerJoin('image.company', 'company')
            ->where('company.slug = :slug', 'company.active = true', 'image.fileName = :fileName', "company.moderationStatus = 'approved'")
            ->setParameter('slug', $slug)->setParameter('fileName', $fileName)
            ->getQuery()->getOneOrNullResult();
    }

    /** @return array{total: int, active: int, featured: int} */
    public function statistics(): array
    {
        $row = $this->createQueryBuilder('company')->select('COUNT(company.id) AS total', 'COALESCE(SUM(CASE WHEN company.active = true THEN 1 ELSE 0 END), 0) AS active', 'COALESCE(SUM(CASE WHEN company.featured = true THEN 1 ELSE 0 END), 0) AS featured')->getQuery()->getSingleResult();

        return array_map(intval(...), $row);
    }

    /** @return list<Company> */
    public function findActive(): array
    {
        return $this->findBy(['active' => true, 'moderationStatus' => \App\Enum\ModerationStatus::Approved], ['name' => 'ASC', 'id' => 'ASC']);
    }
    /** @return list<Company> */
    public function findFeatured(): array
    {
        return $this->findBy(['active' => true, 'featured' => true, 'moderationStatus' => \App\Enum\ModerationStatus::Approved], ['name' => 'ASC', 'id' => 'ASC']);
    }
    /** @return list<Company> */
    public function findLatest(int $limit = 5): array
    {
        return $this->findBy([], ['updatedAt' => 'DESC', 'id' => 'DESC'], max(1, $limit));
    }

    /** @return list<string> */
    public function existingSlugs(string $base, ?int $excludeId): array
    {
        $query = $this->createQueryBuilder('company')->select('company.slug')->where('company.slug = :base OR company.slug LIKE :prefix')->setParameter('base', $base)->setParameter('prefix', $base.'-%');
        if ($excludeId !== null) {
            $query->andWhere('company.id != :id')->setParameter('id', $excludeId);
        }

        return array_column($query->getQuery()->getScalarResult(), 'slug');
    }
}
