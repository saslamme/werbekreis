<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Category;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Category> */
final class CategoryRepository extends ServiceEntityRepository
{
    public const PAGE_SIZE = 25;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Category::class);
    }
    public function countAll(): int
    {
        return $this->count([]);
    }
    public function countCompanies(Category $category): int
    {
        return (int) $this->createQueryBuilder('category')->select('COUNT(company.id)')->leftJoin('category.companies', 'company')->where('category = :category')->setParameter('category', $category)->getQuery()->getSingleScalarResult();
    }

    /** @return list<array{0: Category, companyCount: int|string}> */
    public function adminPage(int $page): array
    {
        return $this->createQueryBuilder('category')->select('category', 'COUNT(company.id) AS companyCount')
            ->leftJoin('category.companies', 'company')
            ->groupBy('category.id, category.name, category.slug, category.description, category.icon, category.position, category.active, category.createdAt, category.updatedAt')
            ->orderBy('category.position', 'ASC')->addOrderBy('category.name', 'ASC')->addOrderBy('category.id', 'ASC')
            ->setFirstResult((max(1, $page) - 1) * self::PAGE_SIZE)->setMaxResults(self::PAGE_SIZE)->getQuery()->getResult();
    }

    /** @return list<string> */
    public function existingSlugs(string $base, ?int $excludeId): array
    {
        $query = $this->createQueryBuilder('category')->select('category.slug')->where('category.slug = :base OR category.slug LIKE :prefix')->setParameter('base', $base)->setParameter('prefix', $base.'-%');
        if ($excludeId !== null) {
            $query->andWhere('category.id != :id')->setParameter('id', $excludeId);
        }

        return array_column($query->getQuery()->getScalarResult(), 'slug');
    }
}
