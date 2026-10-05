<?php

declare(strict_types=1);
namespace App\Service;

use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class EventDateRangeResolver
{
    public const LABELS = ['upcoming' => 'Alle kommenden', 'today' => 'Heute', 'tomorrow' => 'Morgen', 'weekend' => 'Dieses Wochenende', 'week' => 'Diese Woche', 'month' => 'Dieser Monat'];
    public function __construct(private ClockInterface $clock, #[Autowire('%portal.timezone%')] private string $timezone) {}
    public function now(): \DateTimeImmutable { return $this->clock->now(); }
    public function localNow(): \DateTimeImmutable { return $this->now()->setTimezone(new \DateTimeZone($this->timezone)); }
    /** Half-open local calendar range, converted to UTC for database comparisons. @return array{?\DateTimeImmutable, ?\DateTimeImmutable} */
    public function resolve(string $period): array
    {
        $today = $this->localNow()->setTime(0, 0);
        [$start, $end] = match ($period) {
            'today' => [$today, $today->modify('+1 day')],
            'tomorrow' => [$today->modify('+1 day'), $today->modify('+2 days')],
            'weekend' => [$today->modify('monday this week')->modify('+5 days'), $today->modify('monday this week')->modify('+7 days')],
            'week' => [$today->modify('monday this week'), $today->modify('monday this week')->modify('+7 days')],
            'month' => [$today->modify('first day of this month'), $today->modify('first day of next month')],
            default => [null, null],
        };
        $utc = new \DateTimeZone('UTC');
        return [$start?->setTimezone($utc), $end?->setTimezone($utc)];
    }
    public function month(string $value): \DateTimeImmutable
    {
        if (preg_match('/^(20[0-9]{2}|2100)-(0[1-9]|1[0-2])$/D', $value)) { return new \DateTimeImmutable($value.'-01 00:00:00', new \DateTimeZone($this->timezone)); }
        return $this->localNow()->modify('first day of this month')->setTime(0, 0);
    }
    /** @param iterable<\App\Entity\EventOccurrence> $occurrences @return list<array{date: \DateTimeImmutable, inMonth: bool, events: array}> */
    public function calendar(\DateTimeImmutable $month, iterable $occurrences): array
    {
        $first = $month->modify('monday this week');
        $last = $month->modify('last day of this month')->modify('sunday this week');
        $days = []; $zone = new \DateTimeZone($this->timezone);
        for ($date = $first; $date <= $last; $date = $date->modify('+1 day')) {
            $items = [];
            foreach ($occurrences as $occurrence) {
                if ($occurrence->getStartsAt()->setTimezone($zone) < $date->modify('+1 day') && $occurrence->getEndsAt()->setTimezone($zone) >= $date) { $items[] = $occurrence; }
            }
            $days[] = ['date' => $date, 'inMonth' => $date->format('Y-m') === $month->format('Y-m'), 'events' => $items];
        }
        return $days;
    }
}
