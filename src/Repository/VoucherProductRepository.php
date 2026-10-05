<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\VoucherProduct;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<VoucherProduct> */
final class VoucherProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry) { parent::__construct($registry, VoucherProduct::class); }
    public function findPublic(): array { return $this->findBy(['active' => true], ['position' => 'ASC', 'name' => 'ASC']); }
    public function findPublicBySlug(string $slug): ?VoucherProduct { return $this->findOneBy(['active' => true, 'slug' => $slug]); }
    public function adminPage(int $page): Paginator
    {
        return new Paginator($this->createQueryBuilder('product')->addSelect('companies')->leftJoin('product.acceptingCompanies', 'companies')
            ->orderBy('product.position', 'ASC')->addOrderBy('product.name', 'ASC')->setFirstResult((max(1, $page) - 1) * 25)->setMaxResults(25));
    }
    public function acceptanceCount(): int
    {
        return (int) $this->createQueryBuilder('product')->select('COUNT(DISTINCT company.id)')->innerJoin('product.acceptingCompanies', 'company')->where('product.active = true', 'company.active = true')->getQuery()->getSingleScalarResult();
    }
    public function publicSellers(): array
    {
        $rows = $this->createQueryBuilder('product')->select('product.id AS productId', 'company.name AS name', 'company.slug AS slug', 'company.city AS city')
            ->innerJoin('product.sellingCompanies', 'company')->where('product.active = true', 'company.active = true')->orderBy('company.name', 'ASC')->getQuery()->getArrayResult();
        $groups = []; foreach ($rows as $row) { $groups[$row['productId']][] = $row; } return $groups;
    }
    public function existingSlugs(string $base, ?int $excludeId): array
    {
        $query = $this->createQueryBuilder('product')->select('product.slug')->where('product.slug = :base OR product.slug LIKE :prefix')->setParameter('base', $base)->setParameter('prefix', $base.'-%');
        if ($excludeId !== null) { $query->andWhere('product.id != :id')->setParameter('id', $excludeId); }
        return array_column($query->getQuery()->getScalarResult(), 'slug');
    }
}
