<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\DataFixtures\DirectoryImageFixtures;
use App\Entity\Company;
use App\Repository\CompanyRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Filesystem\Filesystem;

abstract class PublicDirectoryTestCase extends DirectoryDatabaseTestCase
{
    protected function tearDown(): void
    {
        (new Filesystem())->remove(dirname(__DIR__, 2).'/var/uploads/test_companies');
        parent::tearDown();
    }

    /** Adds generated logo/cover/gallery files; only tests that need images pay for disk writes. */
    protected function loadImages(): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        static::getContainer()->get(DirectoryImageFixtures::class)->load($em);
        $em->clear();
    }

    protected function company(string $name): Company
    {
        $company = static::getContainer()->get(CompanyRepository::class)->findOneBy(['name' => $name]);
        self::assertInstanceOf(Company::class, $company);

        return $company;
    }

    /** @return list<string> company names in the result cards, in display order */
    protected function cardNames(): array
    {
        return $this->client->getCrawler()->filter('.company-card-title')->each(static fn ($node): string => trim($node->text()));
    }
}
