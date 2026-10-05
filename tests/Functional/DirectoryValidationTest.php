<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Company;
use App\Entity\ContactPerson;
use App\Entity\OpeningHour;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class DirectoryValidationTest extends DirectoryDatabaseTestCase
{
    #[DataProvider('invalidCompanyData')]
    public function testCompanyConstraints(string $setter, mixed $value, string $property): void
    {
        $company = $this->firstCompany();
        $company->{$setter}($value);
        $violations = static::getContainer()->get(ValidatorInterface::class)->validate($company);
        self::assertContains($property, array_map(static fn ($violation) => $violation->getPropertyPath(), iterator_to_array($violations)));
    }
    public static function invalidCompanyData(): array
    {
        return [['setName', '', 'name'], ['setEmail', 'not-an-email', 'email'], ['setWebsite', 'javascript:alert(1)', 'website'], ['setLatitude', 91.0, 'latitude'], ['setLongitude', -181.0, 'longitude']];
    }
    #[DataProvider('invalidHours')]
    public function testInvalidOpeningHours(?string $open, ?string $close, bool $closed, int $day): void
    {
        $entry = (new OpeningHour())->setCompany($this->firstCompany())->setOpensAt($open !== null ? new \DateTimeImmutable($open) : null)->setClosesAt($close !== null ? new \DateTimeImmutable($close) : null)->setClosed($closed)->setDayOfWeek($day);
        self::assertGreaterThan(0, count(static::getContainer()->get(ValidatorInterface::class)->validate($entry)));
    }
    public static function invalidHours(): array
    {
        return [['18:00', '09:00', false, 1], ['09:00', '09:00', false, 1], [null, '18:00', false, 1], ['09:00', '18:00', true, 1], [null, null, true, 8]];
    }
    public function testMultipleNonOverlappingWindowsAreValid(): void
    {
        $company = $this->firstCompany();
        self::assertCount(2, $company->getOpeningHours()->filter(static fn (OpeningHour $entry): bool => $entry->getDayOfWeek() === 1));
        self::assertCount(0, static::getContainer()->get(ValidatorInterface::class)->validate($company));
    }
    public function testOverlappingWindowsAreRejected(): void
    {
        $company = $this->firstCompany();
        $company->addOpeningHour((new OpeningHour())->setDayOfWeek(1)->setOpensAt(new \DateTimeImmutable('11:00'))->setClosesAt(new \DateTimeImmutable('15:00')));
        $errors = static::getContainer()->get(ValidatorInterface::class)->validate($company);
        self::assertGreaterThan(0, count($errors));
    }
    public function testClosedAndOpenWindowsOnSameDayAreRejected(): void
    {
        $company = $this->firstCompany();
        $company->addOpeningHour((new OpeningHour())->setDayOfWeek(1)->setClosed(true));
        self::assertGreaterThan(0, count(static::getContainer()->get(ValidatorInterface::class)->validate($company)));
    }
    public function testActiveCompanyNeedsCategory(): void
    {
        $company = (new Company())->setName('Test')->setSlug('test');
        $validator = static::getContainer()->get(ValidatorInterface::class);
        self::assertCount(1, $validator->validate($company));
        $company->setActive(false);
        self::assertCount(0, $validator->validate($company));
    }
    public function testContactValidation(): void
    {
        $contact = (new ContactPerson())->setCompany($this->firstCompany())->setFirstName('')->setLastName('Beispiel')->setEmail('invalid');
        self::assertGreaterThanOrEqual(2, count(static::getContainer()->get(ValidatorInterface::class)->validate($contact)));
    }
}
