<?php

declare(strict_types=1);

namespace App\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Purger\PurgerFactory;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;

/** Keep migration-owned content when refreshing the demo data. */
final class CmsPreservingPurgerFactory implements PurgerFactory
{
    public function createForEntityManager(?string $emName, EntityManagerInterface $em, array $excluded = [], bool $purgeWithTruncate = false): ORMPurger
    {
        $purger = new ORMPurger($em, array_unique([...$excluded, 'ContentPage', 'ContentPageSection', 'ContentElement', 'ContentMedia', 'BoardMember', 'PortalSettings']));
        $purger->setPurgeMode($purgeWithTruncate ? ORMPurger::PURGE_MODE_TRUNCATE : ORMPurger::PURGE_MODE_DELETE);

        return $purger;
    }
}
