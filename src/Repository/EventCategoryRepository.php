<?php

declare(strict_types=1);
namespace App\Repository;
use App\Entity\EventCategory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<EventCategory> */
final class EventCategoryRepository extends ServiceEntityRepository
{
    public const PAGE_SIZE = 25;
    public function __construct(ManagerRegistry $registry) { parent::__construct($registry, EventCategory::class); }
    public function findActiveOrdered(): array { return $this->findBy(['active' => true], ['position' => 'ASC', 'name' => 'ASC', 'id' => 'ASC']); }
    public function findPublicBySlug(string $slug): ?EventCategory { return $this->findOneBy(['slug' => $slug, 'active' => true]); }
    public function adminPage(int $page): array { return $this->findBy([], ['position' => 'ASC', 'name' => 'ASC', 'id' => 'ASC'], self::PAGE_SIZE, (max(1, $page) - 1) * self::PAGE_SIZE); }
    public function countAll(): int { return $this->count([]); }
    public function countEvents(EventCategory $category): int
    {
        return (int) $this->createQueryBuilder('category')->select('COUNT(event.id)')->leftJoin('category.events', 'event')->where('category = :category')->setParameter('category', $category)->getQuery()->getSingleScalarResult();
    }
    public function existingSlugs(string $base, ?int $excludeId): array
    {
        $query = $this->createQueryBuilder('category')->select('category.slug')->where('category.slug = :base OR category.slug LIKE :prefix')->setParameter('base', $base)->setParameter('prefix', $base.'-%');
        if ($excludeId !== null) { $query->andWhere('category.id != :id')->setParameter('id', $excludeId); }
        return array_column($query->getQuery()->getScalarResult(), 'slug');
    }
}
