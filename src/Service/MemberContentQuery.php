<?php

declare(strict_types=1);
namespace App\Service;
use App\Entity\{ContentRevision, User};
use App\Enum\ContentType;
use Doctrine\ORM\{EntityManagerInterface, QueryBuilder};
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Component\Clock\ClockInterface;
final readonly class MemberContentQuery
{
    public function __construct(private EntityManagerInterface $em, private ClockInterface $clock) {}
    private function scope(ContentType $type, User $user): QueryBuilder
    {
        $q = $this->em->createQueryBuilder()->from($type->entityClass(),'content');
        $company = $type === ContentType::Company ? 'content' : 'company';
        if ($type !== ContentType::Company) { $q->innerJoin('content.company','company'); }
        return $q->innerJoin(User::class,'owner','WITH',$company.' MEMBER OF owner.companies')->where('owner.id = :user')->setParameter('user',$user->getId());
    }
    public function rows(ContentType $type, User $user, int $page): array
    {
        $company = $type === ContentType::Company ? 'content' : 'company'; $title = $type === ContentType::Company ? 'name' : 'title';
        $q = $this->scope($type,$user)->select('content.id','content.'.$title.' AS title', $company.'.name AS companyName','content.updatedAt','content.moderationStatus','revision.id AS revisionId','revision.moderationStatus AS revisionStatus','revision.updatedAt AS draftUpdatedAt','revision.reviewNote','revision.moderationVersion AS revisionVersion')
            ->leftJoin(ContentRevision::class,'revision','WITH','revision.'.$type->association().' = content');
        $total = (int) (clone $q)->select('COUNT(content.id)')->getQuery()->getSingleScalarResult();
        return ['total'=>$total,'rows'=>$q->orderBy('content.updatedAt','DESC')->addOrderBy('content.id','DESC')->setFirstResult((max(1,$page)-1)*25)->setMaxResults(25)->getQuery()->getArrayResult()];
    }
    public function counts(User $user): array
    {
        $counts = [];
        foreach (ContentType::cases() as $type) { $counts[$type->label()] = (int) $this->scope($type,$user)->select('COUNT(content.id)')->getQuery()->getSingleScalarResult(); }
        return $counts;
    }
    private function publicQuery(ContentType $type): QueryBuilder
    {
        $repo = $this->em->getRepository($type->entityClass());
        return match ($type) { ContentType::Company => $repo->createPublicDirectoryQueryBuilder(), ContentType::Offer => $repo->createCurrentPublicQueryBuilder(), ContentType::Event => $repo->createUpcomingPublicQueryBuilder(), default => $repo->createPublicQueryBuilder() };
    }
    public function publishedCount(User $user): int
    {
        $total=0;
        foreach (ContentType::cases() as $type) {
            $alias=match ($type) { ContentType::Company=>'company',ContentType::Offer=>'offer',ContentType::Event=>'event',ContentType::News=>'article',ContentType::Job=>'job' };
            $query=$this->publicQuery($type)->select('COUNT(DISTINCT '.$alias.'.id)')->resetDQLPart('orderBy')->innerJoin(User::class,'memberOwner','WITH','company MEMBER OF memberOwner.companies')->andWhere('memberOwner.id = :memberId')->setParameter('memberId',$user->getId());
            $total += (int) $query->getQuery()->getSingleScalarResult();
        }
        return $total;
    }
    public function publicIds(ContentType $type, array $ids): array
    {
        if ($ids===[]) { return []; }
        $alias=match ($type) { ContentType::Company=>'company',ContentType::Offer=>'offer',ContentType::Event=>'event',ContentType::News=>'article',ContentType::Job=>'job' };
        return array_map('intval',array_column($this->publicQuery($type)->select('DISTINCT '.$alias.'.id')->resetDQLPart('orderBy')->andWhere($alias.'.id IN (:memberIds)')->setParameter('memberIds',$ids)->getQuery()->getScalarResult(),'id'));
    }

}
