<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ContentPage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<ContentPage> */
final class ContentPageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ContentPage::class);
    }

    public function findOneByCode(string $code): ?ContentPage
    {
        return $this->findOneBy(['code' => $code]);
    }
}
