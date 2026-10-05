<?php
declare(strict_types=1);
namespace App\Repository;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
/** @extends ServiceEntityRepository<User> */
final class UserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry) { parent::__construct($registry, User::class); }
    public function countAll(): int { return (int) $this->createQueryBuilder('u')->select('COUNT(u.id)')->getQuery()->getSingleScalarResult(); }
    public function countByRole(string $role): int
    {
        // Stored roles are JSON strings; quoted matching prevents partial role-name matches.
        return (int) $this->createQueryBuilder('u')->select('COUNT(u.id)')->where('u.roles LIKE :role')
            ->setParameter('role', '%"'.$role.'"%')->getQuery()->getSingleScalarResult();
    }
    /** @return list<User> */
    public function findLatest(int $limit = 5): array
    {
        return $this->createQueryBuilder('u')->orderBy('u.createdAt', 'DESC')->addOrderBy('u.id', 'DESC')
            ->setMaxResults(max(1, $limit))->getQuery()->getResult();
    }
}
