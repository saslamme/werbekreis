<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Voucher;
use App\Enum\VoucherStatus;
use App\Service\VoucherCodeGenerator;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Clock\ClockInterface;

/** @extends ServiceEntityRepository<Voucher> */
final class VoucherRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry, private readonly ClockInterface $clock) { parent::__construct($registry, Voucher::class); }
    public function findByCode(string $code): ?Voucher
    {
        $code = VoucherCodeGenerator::normalize($code);
        if (!preg_match(VoucherCodeGenerator::PATTERN, $code)) { return null; }
        return $this->createQueryBuilder('voucher')->addSelect('product')->innerJoin('voucher.product', 'product')->where('voucher.code = :code')->setParameter('code', $code)->getQuery()->getOneOrNullResult();
    }
    public function usableQuery(): QueryBuilder
    {
        return $this->createQueryBuilder('voucher')->where('voucher.status IN (:usable)', 'voucher.remainingAmount > 0')
            ->andWhere('voucher.validFrom IS NULL OR voucher.validFrom <= :now')->andWhere('voucher.validUntil IS NULL OR voucher.validUntil >= :now')
            ->setParameter('usable', VoucherStatus::usableValues())->setParameter('now', $this->clock->now(), 'datetime_immutable');
    }
    public function findUsableByCode(string $code): ?Voucher
    {
        $code = VoucherCodeGenerator::normalize($code);
        return preg_match(VoucherCodeGenerator::PATTERN, $code) ? $this->usableQuery()->andWhere('voucher.code = :code')->setParameter('code', $code)->getQuery()->getOneOrNullResult() : null;
    }
    public function adminPage(array $filters, int $page): Paginator
    {
        $query = $this->createQueryBuilder('voucher')->addSelect('product')->innerJoin('voucher.product', 'product');
        if (($filters['code'] ?? '') !== '') { $query->andWhere('voucher.code = :code')->setParameter('code', VoucherCodeGenerator::normalize($filters['code'])); }
        if (ctype_digit($filters['product'] ?? '')) { $query->andWhere('product.id = :product')->setParameter('product', (int) $filters['product']); }
        if (($status = VoucherStatus::tryFrom($filters['status'] ?? '')) !== null) {
            if ($status === VoucherStatus::Expired) { $query->andWhere('voucher.validUntil < :now')->andWhere('voucher.status NOT IN (:terminal)')->setParameter('terminal', [VoucherStatus::Redeemed->value, VoucherStatus::Blocked->value]); }
            else { $query->andWhere('voucher.status = :status')->setParameter('status', $status->value); if (!in_array($status, [VoucherStatus::Redeemed, VoucherStatus::Blocked], true)) { $query->andWhere('voucher.validUntil IS NULL OR voucher.validUntil >= :now'); } }
        }
        if (($filters['validity'] ?? '') === 'expired') { $query->andWhere('voucher.validUntil < :now'); }
        if (($filters['validity'] ?? '') === 'usable') {
            $query->andWhere('voucher.status IN (:usable)', 'voucher.remainingAmount > 0')->andWhere('voucher.validFrom IS NULL OR voucher.validFrom <= :now')->andWhere('voucher.validUntil IS NULL OR voucher.validUntil >= :now')->setParameter('usable', VoucherStatus::usableValues());
        }
        if (str_contains($query->getDQL(), ':now')) { $query->setParameter('now', $this->clock->now(), 'datetime_immutable'); }
        return new Paginator($query->orderBy('voucher.issuedAt', 'DESC')->addOrderBy('voucher.id', 'DESC')->setFirstResult((max(1, $page) - 1) * 25)->setMaxResults(25), false);
    }
    public function statistics(): array
    {
        $query = $this->usableQuery();
        $usable = $query->select('COUNT(voucher.id) AS activeCount', 'COALESCE(SUM(voucher.remainingAmount), 0) AS openBalance')->getQuery()->getSingleResult();
        $all = $this->createQueryBuilder('voucher')->select('COALESCE(SUM(voucher.remainingAmount), 0)')->where('voucher.activatedAt IS NOT NULL', 'voucher.remainingAmount > 0')->andWhere('voucher.validUntil IS NULL OR voucher.validUntil >= :now')->setParameter('now', $this->clock->now(), 'datetime_immutable')->getQuery()->getSingleScalarResult();
        return ['active' => (int) $usable['activeCount'], 'openBalance' => (string) $all, 'blocked' => $this->count(['status' => VoucherStatus::Blocked])];
    }
}
