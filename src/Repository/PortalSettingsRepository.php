<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\PortalSettings;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<PortalSettings> */
final class PortalSettingsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PortalSettings::class);
    }

    public function getSettings(): PortalSettings
    {
        return $this->find(PortalSettings::ID)
            ?? throw new \LogicException('Portal settings missing. Run Doctrine migrations.');
    }
}
