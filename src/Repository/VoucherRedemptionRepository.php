<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\{Company, Voucher, VoucherRedemption};
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<VoucherRedemption> */
final class VoucherRedemptionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry) { parent::__construct($registry, VoucherRedemption::class); }
    public function historyForVoucher(Voucher $voucher, int $page = 1): Paginator
    {
        return new Paginator($this->createQueryBuilder('entry')->addSelect('company', 'actor')->innerJoin('entry.company', 'company')->leftJoin('entry.performedBy', 'actor')
            ->where('entry.voucher = :voucher')->setParameter('voucher', $voucher)->orderBy('entry.redeemedAt', 'DESC')->addOrderBy('entry.id', 'DESC')->setFirstResult((max(1, $page) - 1) * 25)->setMaxResults(25), false);
    }
    public function historyForCompany(Company $company, int $limit = 25): array
    {
        return $this->findBy(['company' => $company], ['redeemedAt' => 'DESC', 'id' => 'DESC'], max(1, $limit));
    }
    public function sumBetween(\DateTimeImmutable $from, \DateTimeImmutable $until): string
    {
        return (string) $this->createQueryBuilder('entry')->select('COALESCE(SUM(entry.amount), 0)')->where('entry.redeemedAt >= :from', 'entry.redeemedAt < :until')->setParameter('from', $from, 'datetime_immutable')->setParameter('until', $until, 'datetime_immutable')->getQuery()->getSingleScalarResult();
    }
}
