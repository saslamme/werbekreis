<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\DataFixtures\DirectoryFixtures;
use App\Entity\Company;
use App\Repository\CompanyRepository;
use Doctrine\ORM\EntityManagerInterface;

abstract class DirectoryDatabaseTestCase extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        // DatabaseTestCase always uses the separate *_test database.
        $em->createQuery('DELETE FROM App\Entity\Company company')->execute();
        $em->createQuery('DELETE FROM App\Entity\Category category')->execute();
        $em->clear();
        static::getContainer()->get(DirectoryFixtures::class)->load($em);
    }

    protected function firstCompany(): Company
    {
        $company = static::getContainer()->get(CompanyRepository::class)->findOneBy(['name' => 'Musterladen Hasebogen']);
        self::assertInstanceOf(Company::class, $company);

        return $company;
    }
}
