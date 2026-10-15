<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\DataFixtures\DirectoryFixtures;
use App\DataFixtures\DirectoryImageFixtures;
use App\DataFixtures\EventFixtures;
use App\DataFixtures\JobFixtures;
use App\DataFixtures\NewsFixtures;
use App\DataFixtures\OfferFixtures;
use App\Enum\EmploymentType;
use App\Enum\NewsStatus;
use App\Enum\OfferType;
use App\Enum\WorkModel;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** These checks inspect definitions only: no kernel, fixture load(), persistence or database connection. */
final class DemoFixtureDataTest extends TestCase
{
    public function testCompanyProfilesCoverRequiredCategoriesAndDifferentValidOpeningHours(): void
    {
        $rows = DirectoryFixtures::data();
        self::assertCount(10, $rows);
        self::assertCount(10, array_unique(DirectoryFixtures::COMPANY_NAMES));
        self::assertCount(10, array_unique(DirectoryFixtures::COMPANY_DOMAINS));
        foreach (DirectoryFixtures::COMPANY_DOMAINS as $domain) {
            self::assertMatchesRegularExpression('/^[a-z-]+\.example\.(com|org|net)$/D', $domain);
        }
        self::assertSame([[0], [1], [2, 6], [4, 3], [5, 7, 0], [0], [2, 3], [0], [4, 0], [1, 7]], array_column($rows, 2));
        self::assertGreaterThan(5, count(array_unique(array_map(static fn (array $row): string => serialize($row[8]), $rows))));
        foreach ($rows as [$teaser, $description, $categories, $street, $number, $first, $last, $position, $hours, $saturday]) {
            self::assertGreaterThan(200, mb_strlen($description));
            foreach ([$teaser, $description, $street, $first, $last, $position] as $text) {
                self::assertNotSame('', $text);
                self::assertDoesNotMatchRegularExpression('/fiktiv|beispiel|entwicklung|\btest\b/ui', $text);
            }
            self::assertNotEmpty($categories);
            self::assertMatchesRegularExpression('/^\d+$/', $number);
            $previousEnd = null;
            foreach ($hours as [$start, $end]) {
                self::assertLessThan($end, $start);
                if ($previousEnd !== null) {
                    self::assertGreaterThanOrEqual($previousEnd, $start);
                }
                $previousEnd = $end;
            }
            if ($saturday !== null) {
                self::assertLessThan($saturday[1], $saturday[0]);
            }
        }
        self::assertCount(3, DirectoryFixtures::TECHNICAL_COMPANIES);
        self::assertContains(false, array_column(DirectoryFixtures::TECHNICAL_COMPANIES, 1));
        self::assertContains(null, array_column(DirectoryFixtures::TECHNICAL_COMPANIES, 2));
        // A public point outside ten kilometres and a public profile without coordinates stay available.
        self::assertTrue(DirectoryFixtures::TECHNICAL_COMPANIES[0][1]);
        self::assertTrue(DirectoryFixtures::TECHNICAL_COMPANIES[2][1]);
        self::assertGreaterThan(52.77, DirectoryFixtures::TECHNICAL_COMPANIES[2][2]);
    }

    public function testOffersKeepTenVisibleBusinessActionsAndAllWindowAndPriceCases(): void
    {
        $rows = OfferFixtures::data();
        self::assertCount(14, $rows);
        self::assertSame(DirectoryFixtures::COMPANY_NAMES, $this->sortedCompanies(array_column(array_slice($rows, 0, 10), 1)));
        foreach (array_slice($rows, 0, 10) as $row) {
            self::assertTrue($row[3]);
            self::assertTrue($row[5] === null || str_starts_with($row[5], '-'));
            self::assertTrue($row[6] === null || str_starts_with($row[6], '+'));
            self::assertNotSame('', $row[10]);
            self::assertNotSame('', $row[11]);
            self::assertNotSame('', $row[12]);
        }
        self::assertEqualsCanonicalizing(OfferType::cases(), array_values(array_unique(array_column($rows, 2), SORT_REGULAR)));
        self::assertContains(false, array_column($rows, 3));
        self::assertContains(true, array_column($rows, 4));
        self::assertContains(false, array_column($rows, 4));
        self::assertSame('+3 days', $rows[10][5]);
        self::assertSame('-1 day', $rows[11][6]);
        self::assertSame('20 % Rabatt', $rows[0][9]);
        self::assertSame(['31.80', '24.90'], array_slice($rows[1], 7, 2));
        self::assertSame('29.00', $rows[2][8]);
        self::assertSame('49.00', $rows[4][8]);
        self::assertSame('15 % Rabatt', $rows[5][9]);
        self::assertContains(null, array_column($rows, 8));
        self::assertSame('Gartenraum Abendgrün', $rows[13][1]);
    }

    public static function calendarDates(): iterable
    {
        yield 'spring DST' => ['2030-03-31'];
        yield 'autumn DST' => ['2030-10-27'];
        yield 'year boundary' => ['2030-12-31'];
    }

    #[DataProvider('calendarDates')]
    public function testEventWindowsRemainValidAcrossCalendarBoundaries(string $date): void
    {
        $today = new \DateTimeImmutable($date, new \DateTimeZone('Europe/Berlin'));
        $rows = EventFixtures::data($today);
        self::assertCount(12, $rows);
        self::assertSame($today->format('Y-m-d'), $rows[0][1]->format('Y-m-d'));
        self::assertLessThan($today, $rows[5][2]);
        self::assertGreaterThan($today, $rows[3][1]);
        self::assertGreaterThan($rows[4][1], $rows[4][2]);
        self::assertContains(true, array_column($rows, 7));
        self::assertContains(false, array_column($rows, 5));
        self::assertContains(null, array_column($rows, 3));
        foreach ($rows as $row) {
            self::assertLessThanOrEqual($row[2], $row[1]);
            self::assertNotSame('', $row[8]);
            self::assertNotSame('', $row[9]);
            if ($row[3] !== null) {
                self::assertContains($row[3], DirectoryFixtures::COMPANY_NAMES);
            }
        }
        // The weekly series retains a past anchor; future occurrences are resolved by EventSchedule.
        self::assertEquals($today->modify('-7 days')->setTime(17, 0), $rows[8][1]);
    }

    public function testNewsKeepAllPublicationStatesAndOffsets(): void
    {
        $rows = NewsFixtures::data();
        self::assertCount(12, $rows);
        self::assertEqualsCanonicalizing(NewsStatus::cases(), array_values(array_unique(array_column($rows, 1), SORT_REGULAR)));
        self::assertSame([NewsStatus::Draft, null], array_slice($rows[3], 1, 2));
        self::assertSame([NewsStatus::Scheduled, '+3 days'], array_slice($rows[4], 1, 2));
        self::assertSame([NewsStatus::Scheduled, '-1 hour'], array_slice($rows[5], 1, 2));
        self::assertSame([NewsStatus::Published, '+2 days'], array_slice($rows[6], 1, 2));
        self::assertSame([NewsStatus::Published, null], array_slice($rows[11], 1, 2));
        self::assertContains(null, array_column($rows, 4));
        foreach ($rows as $row) {
            self::assertGreaterThan(150, mb_strlen($row[7]));
            self::assertDoesNotMatchRegularExpression('/fiktiv|beispiel|entwicklung|\btest\b/ui', $row[6].$row[7]);
            if ($row[4] !== null) {
                self::assertContains($row[4], DirectoryFixtures::COMPANY_NAMES);
            }
        }
    }

    public function testJobsKeepTenOpenRolesAndEveryEmploymentAndWorkModel(): void
    {
        $rows = JobFixtures::data();
        self::assertCount(19, $rows);
        self::assertSame(DirectoryFixtures::COMPANY_NAMES, $this->sortedCompanies(array_column(array_slice($rows, 0, 10), 1)));
        self::assertEqualsCanonicalizing(EmploymentType::cases(), array_values(array_unique(array_column($rows, 2), SORT_REGULAR)));
        self::assertEqualsCanonicalizing(WorkModel::cases(), array_values(array_unique(array_column($rows, 3), SORT_REGULAR)));
        self::assertEqualsCanonicalizing(NewsStatus::cases(), array_values(array_unique(array_column($rows, 4), SORT_REGULAR)));
        self::assertSame([NewsStatus::Draft, null], array_slice($rows[14], 4, 2));
        self::assertSame([NewsStatus::Scheduled, '+3 days'], array_slice($rows[15], 4, 2));
        self::assertSame('-1 day', $rows[16][6]);
        self::assertSame([NewsStatus::Scheduled, '-1 hour'], array_slice($rows[17], 4, 2));
        self::assertSame([NewsStatus::Published, '+2 days'], array_slice($rows[18], 4, 2));
        foreach ($rows as $row) {
            self::assertContains($row[1], DirectoryFixtures::COMPANY_NAMES);
            foreach (array_slice($row, 8) as $text) {
                self::assertNotSame('', $text);
                self::assertDoesNotMatchRegularExpression('/fiktiv|beispiel|entwicklung|\btest\b/ui', $text);
            }
        }
    }

    public function testCompanyArtworkCoverageAndDimensions(): void
    {
        $rows = DirectoryImageFixtures::data();
        $logos = array_filter($rows, static fn (array $row): bool => $row[1] === 'logo');
        $covers = array_filter($rows, static fn (array $row): bool => $row[1] === 'cover');
        $galleries = array_filter($rows, static fn (array $row): bool => $row[1] === 'gallery');
        self::assertSame(DirectoryFixtures::COMPANY_NAMES, array_column($logos, 0));
        self::assertCount(7, $covers);
        self::assertCount(4, array_unique(array_column($galleries, 0)));
        self::assertCount(8, $galleries);
        foreach ($rows as $row) {
            self::assertGreaterThan(0, $row[3]);
            self::assertGreaterThan(0, $row[4]);
            self::assertNotSame('', $row[5]);
        }
    }

    /** @param list<string> $names @return list<string> */
    private function sortedCompanies(array $names): array
    {
        return array_values(array_filter(DirectoryFixtures::COMPANY_NAMES, static fn (string $name): bool => in_array($name, $names, true)));
    }
}
