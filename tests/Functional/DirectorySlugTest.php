<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Category;
use App\Entity\Company;
use App\Service\DirectorySlugger;
use Doctrine\ORM\EntityManagerInterface;

final class DirectorySlugTest extends DirectoryDatabaseTestCase
{
    public function testCompanySlugsAreUniqueAndStable(): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $slugs = static::getContainer()->get(DirectorySlugger::class);
        $first = (new Company())->setName('Modehaus Schröer GmbH')->setActive(false);
        $slugs->assign($first);
        self::assertSame('modehaus-schroeer-gmbh', $first->getSlug());
        $em->persist($first);
        $em->flush();
        $second = (new Company())->setName('Modehaus Schröer GmbH')->setActive(false);
        $slugs->assign($second);
        self::assertSame('modehaus-schroeer-gmbh-2', $second->getSlug());
        $em->persist($second);
        $em->flush();
        $first->setName('Ein anderer Name');
        $slugs->assign($first);
        self::assertSame('modehaus-schroeer-gmbh', $first->getSlug());
    }
    public function testCategorySlugCollisionAndLength(): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $slugs = static::getContainer()->get(DirectorySlugger::class);
        $first = (new Category())->setName('Gesundheit');
        $slugs->assign($first);
        self::assertSame('gesundheit-2', $first->getSlug());
        $em->persist($first);
        $em->flush();
        $second = (new Category())->setName(str_repeat('A', 180));
        $slugs->assign($second);
        self::assertLessThanOrEqual(180, strlen($second->getSlug()));
    }
}
