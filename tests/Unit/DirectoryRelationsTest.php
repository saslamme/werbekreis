<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\Category;
use App\Entity\Company;
use App\Entity\CompanyImage;
use App\Entity\ContactPerson;
use App\Entity\OpeningHour;
use PHPUnit\Framework\TestCase;

final class DirectoryRelationsTest extends TestCase
{
    public function testCategoryRelationIsConsistentFromEitherSide(): void
    {
        $company = new Company();
        $category = new Category();
        $company->addCategory($category)->addCategory($category);
        self::assertCount(1, $company->getCategories());
        self::assertTrue($category->getCompanies()->contains($company));
        $category->removeCompany($company);
        self::assertCount(0, $company->getCategories());
        self::assertCount(0, $category->getCompanies());
        $category->addCompany($company);
        $company->removeCategory($category);
        self::assertCount(0, $category->getCompanies());
    }

    public function testChildRelationsSupportReassignmentAndRemoval(): void
    {
        $a = new Company();
        $b = new Company();
        foreach ([[new CompanyImage(), 'Images', 'Image'], [new OpeningHour(), 'OpeningHours', 'OpeningHour'], [new ContactPerson(), 'ContactPersons', 'ContactPerson']] as [$entry, $plural, $singular]) {
            $a->{'add'.$singular}($entry);
            self::assertSame($a, $entry->getCompany());
            $entry->setCompany($b);
            self::assertCount(0, $a->{'get'.$plural}());
            self::assertTrue($b->{'get'.$plural}()->contains($entry));
            $b->{'remove'.$singular}($entry);
            self::assertNull($entry->getCompany());
            self::assertCount(0, $b->{'get'.$plural}());
        }
    }

    public function testInitialStateAndTimestamps(): void
    {
        $company = new Company();
        self::assertTrue($company->isActive());
        self::assertFalse($company->isFeatured());
        self::assertNull($company->getCity()); // Default comes from portal configuration, not the entity.
        self::assertNull($company->getLatitude());
        self::assertCount(0, $company->getImages());
        self::assertEquals($company->getCreatedAt(), $company->getUpdatedAt());
    }
}
