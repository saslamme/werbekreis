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

    /** @return list<Category> */
    public function findActiveOrdered(): array
    {
        return $this->findBy(['active' => true], ['position' => 'ASC', 'name' => 'ASC', 'id' => 'ASC']);
    }

    public function findPublicBySlug(string $slug): ?Category
    {
        return $this->findOneBy(['slug' => $slug, 'active' => true]);
    }

    /**
     * Active categories with the number of active companies, in one grouped query.
     *
     * @return list<array{category: Category, companyCount: int}>
     */
    public function findActiveWithPublicCompanyCount(): array
    {
        $rows = $this->createQueryBuilder('category')->select('category', 'COUNT(company.id) AS companyCount')
            ->leftJoin('category.companies', 'company', 'WITH', "company.active = true AND company.moderationStatus = 'approved'")
            ->where('category.active = true')
            ->groupBy('category.id, category.name, category.slug, category.description, category.icon, category.position, category.active, category.createdAt, category.updatedAt')
            ->orderBy('category.position', 'ASC')->addOrderBy('category.name', 'ASC')->addOrderBy('category.id', 'ASC')
            ->getQuery()->getResult();

        return array_map(static fn (array $row): array => ['category' => $row[0], 'companyCount' => (int) $row['companyCount']], $rows);
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
