<?php

declare(strict_types=1);
namespace App\Repository;
use App\Entity\{ContentRevision, User};
use App\Enum\{ContentType, ModerationStatus};
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;
final class ContentRevisionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry) { parent::__construct($registry, ContentRevision::class); }
    public function forTarget(object $target): ?ContentRevision { return $this->findOneBy([ContentType::forEntity($target)->association() => $target]); }
    public function queue(array $filters, int $page): Paginator
    {
        $q = $this->createQueryBuilder('revision')->addSelect('company','submitter','reviewer')->innerJoin('revision.ownerCompany','company')->leftJoin('revision.submittedBy','submitter')->leftJoin('revision.reviewedBy','reviewer')->where('revision.moderationStatus = :pending')->setParameter('pending', ModerationStatus::PendingReview);
        foreach (['type','company','submitter'] as $field) { if (($filters[$field] ?? '') !== '') { $q->andWhere(match ($field) { 'type' => 'revision.type = :type', 'company' => 'company.id = :company', default => 'submitter.id = :submitter' })->setParameter($field, $filters[$field]); } }
        foreach (['from','until'] as $field) { if (preg_match('/^\d{4}-\d{2}-\d{2}$/D', $filters[$field] ?? '')) { $date = new \DateTimeImmutable($filters[$field].' 00:00:00 UTC'); $q->andWhere($field === 'from' ? 'revision.submittedAt >= :from' : 'revision.submittedAt < :until')->setParameter($field, $field === 'from' ? $date : $date->modify('+1 day'), 'datetime_immutable'); } }
        return new Paginator($q->orderBy('revision.submittedAt','ASC')->addOrderBy('revision.id','ASC')->setFirstResult((max(1,$page)-1)*25)->setMaxResults(25), false);
    }
    public function counts(?User $user = null): array
    {
        $q = $this->createQueryBuilder('revision')->select('revision.moderationStatus AS status','revision.type AS type','COUNT(revision.id) AS amount')->groupBy('revision.moderationStatus','revision.type');
        if ($user !== null) { $q->innerJoin('revision.ownerCompany','company')->innerJoin(User::class,'owner','WITH','company MEMBER OF owner.companies')->andWhere('owner.id = :user')->setParameter('user', $user->getId()); }
        return $q->getQuery()->getArrayResult();
    }
    public function latest(User $user): array
    {
        return $this->createQueryBuilder('revision')->select('revision.id','revision.type','revision.moderationStatus','revision.updatedAt','company.name AS companyName')->innerJoin('revision.ownerCompany','company')->innerJoin(User::class,'owner','WITH','company MEMBER OF owner.companies')->where('owner.id = :user')->setParameter('user',$user->getId())->orderBy('revision.updatedAt','DESC')->setMaxResults(8)->getQuery()->getArrayResult();
    }
}
