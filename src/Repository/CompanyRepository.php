<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Company;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Company> */
final class CompanyRepository extends ServiceEntityRepository
{
    public const PAGE_SIZE = 25;

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

    /** @return array{total: int, active: int, featured: int} */
    public function statistics(): array
    {
        $row = $this->createQueryBuilder('company')->select('COUNT(company.id) AS total', 'COALESCE(SUM(CASE WHEN company.active = true THEN 1 ELSE 0 END), 0) AS active', 'COALESCE(SUM(CASE WHEN company.featured = true THEN 1 ELSE 0 END), 0) AS featured')->getQuery()->getSingleResult();

        return array_map(intval(...), $row);
    }

    /** @return list<Company> */
    public function findActive(): array
    {
        return $this->findBy(['active' => true], ['name' => 'ASC', 'id' => 'ASC']);
    }
    /** @return list<Company> */
    public function findFeatured(): array
    {
        return $this->findBy(['active' => true, 'featured' => true], ['name' => 'ASC', 'id' => 'ASC']);
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
