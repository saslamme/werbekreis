<?php

declare(strict_types=1);
namespace App\Tests\Unit;

use App\Entity\{Company, Event, EventCategory, EventOccurrence};
use App\Enum\EventRecurrence;
use App\Service\{EventSchedule, EventDateRangeResolver};
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class EventScheduleTest extends TestCase
{
    private function event(string $start = '2030-05-01 18:00:00 Europe/Berlin', string $end = '2030-05-01 20:00:00 Europe/Berlin'): Event
    {
        return (new Event())->setStartsAt(new \DateTimeImmutable($start))->setEndsAt(new \DateTimeImmutable($end));
    }
    public static function statuses(): iterable
    {
        yield ['2030-05-01 15:59:59Z', 'Geplant']; yield ['2030-05-01 16:00:00Z', 'Läuft'];
        yield ['2030-05-01 18:00:00Z', 'Läuft']; yield ['2030-05-01 18:00:01Z', 'Vergangen'];
    }
    #[DataProvider('statuses')]
    public function testInclusiveStatusBoundaries(string $time, string $status): void
    {
        $event = $this->event(); [$start, $end] = (new EventSchedule('Europe/Berlin'))->dates($event)[0];
        $occurrence = new EventOccurrence($event, $start, $end); $now = new \DateTimeImmutable($time);
        self::assertSame($status, $occurrence->statusAt($now));
        $event->setCancelled(true); self::assertSame('Abgesagt', $occurrence->statusAt($now));
        $event->setActive(false); self::assertSame('Deaktiviert', $occurrence->statusAt($now));
    }
    public function testCompanyAndCategoryHelpersMaintainBothSides(): void
    {
        $a = new Company(); $b = new Company(); $event = new Event(); $category = new EventCategory();
        $a->addEvent($event); self::assertSame($a, $event->getCompany()); $event->setCompany($b);
        self::assertCount(0, $a->getEvents()); self::assertTrue($b->getEvents()->contains($event));
        $b->removeEvent($event); self::assertNull($event->getCompany());
        $category->addEvent($event); self::assertTrue($event->getCategories()->contains($category));
        $category->setActive(false); self::assertSame([], $event->getPublicCategories());
        $event->removeCategory($category); self::assertCount(0, $category->getEvents());
        $event->setCompany($a->setName('Firma')); self::assertSame('Firma', $event->getOrganizerLabel());
        $event->setOrganizerName('Initiative'); self::assertSame('Initiative', $event->getOrganizerLabel());
    }
    public static function recurrences(): iterable
    {
        yield [EventRecurrence::None, '2030-05-10', 1];
        yield [EventRecurrence::Daily, '2030-05-04', 4];
        yield [EventRecurrence::Weekly, '2030-05-22', 4];
        yield [EventRecurrence::Monthly, '2030-08-01', 4];
    }
    #[DataProvider('recurrences')]
    public function testBoundedRecurrenceHasNoDuplicates(EventRecurrence $type, string $until, int $count): void
    {
        $event = $this->event()->setRecurrenceType($type)->setRecurrenceUntil(new \DateTimeImmutable($until));
        $schedule = new EventSchedule('Europe/Berlin'); $dates = $schedule->dates($event);
        self::assertCount($count, $dates); self::assertCount($count, array_unique(array_map(static fn ($d) => $d[0]->format('c'), $dates)));
        $schedule->synchronize($event); $first = $event->getOccurrences()->first(); $schedule->synchronize($event);
        self::assertCount($count, $event->getOccurrences()); self::assertSame($first, $event->getOccurrences()->first());
        $event->setRecurrenceType(EventRecurrence::None); $schedule->synchronize($event); self::assertCount(1, $event->getOccurrences());
    }
    public function testMonthlyDay31SkipsShortMonthsWithoutDrifting(): void
    {
        $dates = (new EventSchedule('Europe/Berlin'))->dates($this->event('2030-01-31 18:00 Europe/Berlin', '2030-01-31 20:00 Europe/Berlin')->setRecurrenceType(EventRecurrence::Monthly)->setRecurrenceUntil(new \DateTimeImmutable('2030-05-31')));
        self::assertSame(['2030-01-31', '2030-03-31', '2030-05-31'], array_map(static fn ($d) => $d[0]->format('Y-m-d'), $dates));
    }
    public static function dstSeries(): iterable
    {
        yield ['2030-03-30', '2030-04-01', ['18:00 +01:00', '18:00 +02:00', '18:00 +02:00']];
        yield ['2030-10-26', '2030-10-28', ['18:00 +02:00', '18:00 +01:00', '18:00 +01:00']];
    }
    #[DataProvider('dstSeries')]
    public function testRecurrencesKeepWallClockTimeAcrossDst(string $start, string $until, array $expected): void
    {
        $dates = (new EventSchedule('Europe/Berlin'))->dates($this->event($start.' 18:00 Europe/Berlin', $start.' 20:00 Europe/Berlin')->setRecurrenceType(EventRecurrence::Daily)->setRecurrenceUntil(new \DateTimeImmutable($until)));
        self::assertSame($expected, array_map(static fn ($d) => $d[0]->setTimezone(new \DateTimeZone('Europe/Berlin'))->format('H:i P'), $dates));
        foreach ($dates as [$a, $b]) { self::assertSame('20:00', $b->setTimezone(new \DateTimeZone('Europe/Berlin'))->format('H:i')); self::assertSame(7200, $b->getTimestamp() - $a->getTimestamp()); }
    }
    public function testAllDayAndMultidayCoverLocalCalendarDatesAcrossDst(): void
    {
        $schedule = new EventSchedule('Europe/Berlin'); $event = $this->event('2030-03-30 14:00 Europe/Berlin', '2030-04-01 17:00 Europe/Berlin')->setAllDay(true);
        [$a, $b] = $schedule->dates($event)[0];
        self::assertSame('2030-03-30 00:00:00', $a->setTimezone(new \DateTimeZone('Europe/Berlin'))->format('Y-m-d H:i:s'));
        self::assertSame('2030-04-01 23:59:59', $b->setTimezone(new \DateTimeZone('Europe/Berlin'))->format('Y-m-d H:i:s'));
        $single = $this->event('2030-03-31 00:00 Europe/Berlin', '2030-03-31 00:00 Europe/Berlin')->setAllDay(true)->setRecurrenceType(EventRecurrence::Daily)->setRecurrenceUntil(new \DateTimeImmutable('2030-04-01'));
        $dates = $schedule->dates($single); self::assertCount(2, $dates);
        foreach ($dates as [$a, $b]) { self::assertSame('23:59:59', $b->setTimezone(new \DateTimeZone('Europe/Berlin'))->format('H:i:s')); }
    }
    public static function invalidRecurrence(): iterable
    {
        yield [null]; yield ['2030-04-30']; yield ['2031-05-02'];
    }
    #[DataProvider('invalidRecurrence')]
    public function testInvalidAndUnboundedSeriesRejected(?string $until): void
    {
        $event = $this->event()->setRecurrenceType(EventRecurrence::Daily)->setRecurrenceUntil($until ? new \DateTimeImmutable($until) : null);
        $this->expectException(\InvalidArgumentException::class); (new EventSchedule('Europe/Berlin'))->dates($event);
    }
    public function testDstGapCannotGenerateNegativeDuration(): void
    {
        $event = $this->event('2030-03-30 02:30 Europe/Berlin', '2030-03-30 03:00 Europe/Berlin')->setRecurrenceType(EventRecurrence::Daily)->setRecurrenceUntil(new \DateTimeImmutable('2030-04-01'));
        $this->expectException(\InvalidArgumentException::class);
        (new EventSchedule('Europe/Berlin'))->dates($event);
    }

    public function testLeapYearMaximumIsFinite(): void
    {
        $event = $this->event('2032-01-01 18:00 Europe/Berlin', '2032-01-01 20:00 Europe/Berlin')->setRecurrenceType(EventRecurrence::Daily)->setRecurrenceUntil(new \DateTimeImmutable('2033-01-01'));
        self::assertCount(367, (new EventSchedule('Europe/Berlin'))->dates($event));
    }
    public static function ranges(): iterable
    {
        yield ['today', '2030-04-30T22:00:00+00:00', '2030-05-01T22:00:00+00:00'];
        yield ['tomorrow', '2030-05-01T22:00:00+00:00', '2030-05-02T22:00:00+00:00'];
        yield ['weekend', '2030-05-03T22:00:00+00:00', '2030-05-05T22:00:00+00:00'];
        yield ['week', '2030-04-28T22:00:00+00:00', '2030-05-05T22:00:00+00:00'];
        yield ['month', '2030-04-30T22:00:00+00:00', '2030-05-31T22:00:00+00:00'];
    }
    #[DataProvider('ranges')]
    public function testDateRangeFiltersUseLocalCalendarAndClock(string $period, string $start, string $end): void
    {
        $resolver = new EventDateRangeResolver(new MockClock('2030-05-01 12:00Z'), 'Europe/Berlin'); [$a, $b] = $resolver->resolve($period);
        self::assertSame($start, $a->format('c')); self::assertSame($end, $b->format('c'));
        self::assertSame([null, null], $resolver->resolve('unknown'));
    }
    public function testDstDayHas23Or25HoursAndLocalMidnightIsCorrect(): void
    {
        foreach (['2030-03-31 12:00Z' => 23, '2030-10-27 12:00Z' => 25] as $now => $hours) {
            [$a, $b] = (new EventDateRangeResolver(new MockClock($now), 'Europe/Berlin'))->resolve('today'); self::assertSame($hours * 3600, $b->getTimestamp() - $a->getTimestamp());
        }
        [$a] = (new EventDateRangeResolver(new MockClock('2030-04-30 22:30Z'), 'Europe/Berlin'))->resolve('today'); self::assertSame('2030-04-30T22:00:00+00:00', $a->format('c'));
    }
    public function testMonthValidationAndCalendarDays(): void
    {
        $resolver = new EventDateRangeResolver(new MockClock('2030-05-01 12:00Z'), 'Europe/Berlin');
        foreach (['bad', '2030-13', '2030-00', '9999-01', '2030-05-foo'] as $value) { self::assertSame('2030-05-01', $resolver->month($value)->format('Y-m-d')); }
        self::assertSame('2030-04-01', $resolver->month('2030-04')->format('Y-m-d'));
        $days = $resolver->calendar($resolver->month('2030-05'), []); self::assertCount(35, $days); self::assertSame('2030-04-29', $days[0]['date']->format('Y-m-d')); self::assertSame('2030-06-02', $days[34]['date']->format('Y-m-d'));
    }
}
