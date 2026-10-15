<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\BoardMember;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<BoardMember> */
final class BoardMemberRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BoardMember::class);
    }

    /** @return list<BoardMember> */
    public function findPublic(): array
    {
        return $this->findBy(['active' => true], ['position' => 'ASC', 'name' => 'ASC', 'id' => 'ASC']);
    }

    /** @return list<BoardMember> */
    public function findOrdered(): array
    {
        return $this->findBy([], ['position' => 'ASC', 'name' => 'ASC', 'id' => 'ASC']);
    }
}
