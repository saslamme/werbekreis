<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\Category;
use App\Entity\Company;
use App\Entity\CompanyImage;
use App\Entity\ContactPerson;
use App\Entity\OpeningHour;
use App\Service\CompanyStructuredData;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class PublicCompanyPresentationTest extends TestCase
{
    public function testImageHelpersSeparateLogoCoverAndGallery(): void
    {
        $company = new Company();
        foreach (['gallery', 'logo', 'cover', 'gallery', 'logo'] as $index => $type) {
            (new CompanyImage())->setType($type)->setFileName($type.$index)->setCompany($company);
        }
        self::assertSame('logo1', $company->getLogoImage()?->getFileName());
        self::assertSame('cover2', $company->getCoverImage()?->getFileName());
        self::assertSame(['gallery0', 'gallery3'], array_map(static fn (CompanyImage $image): string => $image->getFileName(), $company->getGalleryImages()));
        self::assertNull((new Company())->getLogoImage());
        self::assertSame([], (new Company())->getGalleryImages());
    }

    public function testPublicCategoriesAreActiveAndOrdered(): void
    {
        $company = (new Company())
            ->addCategory((new Category())->setName('B')->setPosition(2))
            ->addCategory((new Category())->setName('Versteckt')->setPosition(0)->setActive(false))
            ->addCategory((new Category())->setName('A')->setPosition(2))
            ->addCategory((new Category())->setName('Zuerst')->setPosition(1));
        self::assertSame(['Zuerst', 'A', 'B'], array_map(static fn (Category $category): string => $category->getName(), $company->getPublicCategories()));
    }

    public function testPublicContactsArePrimaryFirstThenSortOrderThenName(): void
    {
        $company = new Company();
        foreach ([['Zoe', 'Zahl', 0, false, true], ['Anna', 'Berg', 5, false, true], ['Paul', 'Prim', 9, true, true], ['Otto', 'Aus', 0, false, false], ['Ben', 'Adam', 5, false, true]] as [$first, $last, $sort, $primary, $active]) {
            $company->addContactPerson((new ContactPerson())->setFirstName($first)->setLastName($last)->setSortOrder($sort)->setPrimaryContact($primary)->setActive($active));
        }
        self::assertSame(['Prim', 'Zahl', 'Adam', 'Berg'], array_map(static fn (ContactPerson $contact): string => $contact->getLastName(), $company->getPublicContactPersons()));
    }

    public function testOpeningHoursAreGroupedByDayWithoutInventingClosedDays(): void
    {
        $company = new Company();
        $company->addOpeningHour((new OpeningHour())->setDayOfWeek(3)->setOpensAt(new \DateTimeImmutable('14:00'))->setClosesAt(new \DateTimeImmutable('18:00')));
        $company->addOpeningHour((new OpeningHour())->setDayOfWeek(1)->setOpensAt(new \DateTimeImmutable('09:00'))->setClosesAt(new \DateTimeImmutable('12:00')));
        $company->addOpeningHour((new OpeningHour())->setDayOfWeek(3)->setOpensAt(new \DateTimeImmutable('08:00'))->setClosesAt(new \DateTimeImmutable('12:30')));
        $company->addOpeningHour((new OpeningHour())->setDayOfWeek(7)->setClosed(true));
        $days = $company->getOpeningHoursByDay();
        self::assertSame([1, 3, 7], array_keys($days));
        self::assertSame(['08:00', '14:00'], array_map(static fn (OpeningHour $entry): string => $entry->getOpensAt()->format('H:i'), $days[3]));
        self::assertSame('Mittwoch', $days[3][0]->getDayName());
        self::assertTrue($days[7][0]->isClosed());
    }

    public function testTeaserPrefersShortDescriptionAndShortens(): void
    {
        self::assertSame('Kurz', (new Company())->setShortDescription('Kurz')->setDescription('Lang')->getTeaser());
        self::assertSame('Zeile eins zwei', (new Company())->setDescription("Zeile\n  eins zwei")->getTeaser());
        self::assertSame('abcdefghi…', (new Company())->setDescription(str_repeat('abcdefghij', 3))->getTeaser(10));
        self::assertNull((new Company())->getTeaser());
    }

    public function testStructuredDataOmitsMissingValues(): void
    {
        $urls = $this->createMock(UrlGeneratorInterface::class);
        $urls->method('generate')->willReturnCallback(static fn (string $route, array $parameters): string => 'https://portal.test/'.$route.'/'.implode('/', $parameters));
        $company = (new Company())->setName('Nur Name')->setSlug('nur-name');
        $company->addOpeningHour((new OpeningHour())->setDayOfWeek(6)->setClosed(true));
        self::assertSame(['@context' => 'https://schema.org', '@type' => 'LocalBusiness', 'name' => 'Nur Name', 'url' => 'https://portal.test/company_show/nur-name'], (new CompanyStructuredData($urls))->forCompany($company));
    }
}
