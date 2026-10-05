<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Company;
use App\Entity\Offer;
use App\Enum\OfferType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Clock\ClockInterface;

/** @extends ServiceEntityRepository<Offer> */
final class OfferRepository extends ServiceEntityRepository
{
    public const PAGE_SIZE = 12;
    public const ADMIN_PAGE_SIZE = 25;

    public function __construct(ManagerRegistry $registry, private readonly ClockInterface $clock)
    {
        parent::__construct($registry, Offer::class);
    }

    /** All public queries share inclusive scheduling and active-company visibility. */
    public function createCurrentPublicQueryBuilder(?OfferType $type = null, ?Company $company = null): QueryBuilder
    {
        $query = $this->createQueryBuilder('offer')->innerJoin('offer.company', 'company')->addSelect('company')
            ->where('offer.active = true', 'company.active = true')
            ->andWhere('offer.startsAt IS NULL OR offer.startsAt <= :now')
            ->andWhere('offer.endsAt IS NULL OR offer.endsAt >= :now')
            ->setParameter('now', $this->clock->now(), 'datetime_immutable');
        if ($type !== null) {
            $query->andWhere('offer.type = :type')->setParameter('type', $type->value);
        }
        if ($company !== null) {
            $query->andWhere('offer.company = :company')->setParameter('company', $company);
        }

        return $query->addSelect('CASE WHEN offer.endsAt IS NULL THEN 1 ELSE 0 END AS HIDDEN withoutEnd')
            ->orderBy('offer.featured', 'DESC')->addOrderBy('withoutEnd', 'ASC')->addOrderBy('offer.endsAt', 'ASC')
            ->addOrderBy('offer.title', 'ASC')->addOrderBy('offer.id', 'ASC');
    }

    /** @return Paginator<Offer> */
    public function publicPage(int $page, ?OfferType $type = null, ?Company $company = null): Paginator
    {
        return new Paginator($this->createCurrentPublicQueryBuilder($type, $company)
            ->setFirstResult((max(1, $page) - 1) * self::PAGE_SIZE)->setMaxResults(self::PAGE_SIZE), fetchJoinCollection: false);
    }

    /** Featured first, filled with other current offers. @return list<Offer> */
    public function findCurrentFeatured(int $limit = 3): array
    {
        return $this->createCurrentPublicQueryBuilder()->setMaxResults(max(1, $limit))->getQuery()->getResult();
    }

    /** @return list<Offer> */
    public function findCurrentForCompany(Company $company, int $limit = 4): array
    {
        return $this->createCurrentPublicQueryBuilder(company: $company)->setMaxResults(max(1, $limit))->getQuery()->getResult();
    }

    public function findPublicBySlug(string $slug): ?Offer
    {
        return $this->createCurrentPublicQueryBuilder()->andWhere('offer.slug = :slug')->setParameter('slug', $slug)->getQuery()->getOneOrNullResult();
    }

    /** @return Paginator<Offer> */
    public function adminPage(string $title, ?int $company, ?OfferType $type, ?bool $active, ?bool $featured, int $page): Paginator
    {
        $query = $this->createQueryBuilder('offer')->innerJoin('offer.company', 'company')->addSelect('company');
        if ($title !== '') {
            $query->andWhere('LOWER(offer.title) LIKE :title')->setParameter('title', '%'.addcslashes(mb_strtolower($title), '%_\\').'%');
        }
        if ($company !== null) {
            $query->andWhere('company.id = :company')->setParameter('company', $company);
        }
        if ($type !== null) {
            $query->andWhere('offer.type = :type')->setParameter('type', $type->value);
        }
        if ($active !== null) {
            $query->andWhere('offer.active = :active')->setParameter('active', $active);
        }
        if ($featured !== null) {
            $query->andWhere('offer.featured = :featured')->setParameter('featured', $featured);
        }

        return new Paginator($query->orderBy('offer.updatedAt', 'DESC')->addOrderBy('offer.id', 'DESC')
            ->setFirstResult((max(1, $page) - 1) * self::ADMIN_PAGE_SIZE)->setMaxResults(self::ADMIN_PAGE_SIZE), fetchJoinCollection: false);
    }

    /** @return list<string> */
    public function existingSlugs(string $base, ?int $excludeId): array
    {
        $query = $this->createQueryBuilder('offer')->select('offer.slug')->where('offer.slug = :base OR offer.slug LIKE :prefix')
            ->setParameter('base', $base)->setParameter('prefix', $base.'-%');
        if ($excludeId !== null) {
            $query->andWhere('offer.id != :id')->setParameter('id', $excludeId);
        }

        return array_column($query->getQuery()->getScalarResult(), 'slug');
    }
}
