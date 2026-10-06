<?php

declare(strict_types=1);
namespace App\Security;
use App\Entity\{Company, ContentRevision, User};
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
final readonly class ContentAccess
{
    public function __construct(private EntityManagerInterface $em) {}
    public function allows(User $user, object $subject, string $permission, bool $fresh = false): bool
    {
        if (!$user->isActive()) { return false; }
        $reviewer = array_intersect(['ROLE_ADMIN','ROLE_EDITOR'], $user->getRoles()) !== [];
        if ($permission === ContentOwnershipVoter::REVIEW) { return $reviewer; }
        if ($reviewer) { return true; }
        if (!in_array('ROLE_MEMBER', $user->getRoles(), true)) { return false; }
        $target = $subject instanceof ContentRevision ? $subject->getTarget() : $subject;
        $company = $target instanceof Company ? $target : $target->getCompany();
        if ($company === null) { return false; }
        if (!$fresh) { return $user->canManageCompany($company); }
        return $this->em->createQueryBuilder()->select('owner.id')->from(User::class,'owner')->innerJoin('owner.companies','company')->where('owner.id = :user','company.id = :company')->setParameter('user',$user->getId())->setParameter('company',$company->getId())->getQuery()->setLockMode(LockMode::PESSIMISTIC_READ)->getScalarResult() !== [];
    }
}
