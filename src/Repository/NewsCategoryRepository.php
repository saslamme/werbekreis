<?php

declare(strict_types=1);
namespace App\Repository;
use App\Entity\NewsCategory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<NewsCategory> */
final class NewsCategoryRepository extends ServiceEntityRepository
{
    public const PAGE_SIZE = 25;
    public function __construct(ManagerRegistry $registry) { parent::__construct($registry, NewsCategory::class); }
    public function findActiveOrdered(): array { return $this->findBy(['active' => true], ['position' => 'ASC', 'name' => 'ASC', 'id' => 'ASC']); }
    public function findPublicBySlug(string $slug): ?NewsCategory { return $this->findOneBy(['slug' => $slug, 'active' => true]); }
    public function adminPage(int $page): array { return $this->findBy([], ['position' => 'ASC', 'name' => 'ASC', 'id' => 'ASC'], self::PAGE_SIZE, (max(1, $page) - 1) * self::PAGE_SIZE); }
    public function countAll(): int { return $this->count([]); }
    public function countArticles(NewsCategory $category): int
    {
        return (int) $this->createQueryBuilder('category')->select('COUNT(article.id)')->leftJoin('category.articles', 'article')->where('category = :category')->setParameter('category', $category)->getQuery()->getSingleScalarResult();
    }
    public function existingSlugs(string $base, ?int $excludeId): array
    {
        $query = $this->createQueryBuilder('category')->select('category.slug')->where('category.slug = :base OR category.slug LIKE :prefix')->setParameter('base', $base)->setParameter('prefix', $base.'-%');
        if ($excludeId !== null) { $query->andWhere('category.id != :id')->setParameter('id', $excludeId); }
        return array_column($query->getQuery()->getScalarResult(), 'slug');
    }
}
