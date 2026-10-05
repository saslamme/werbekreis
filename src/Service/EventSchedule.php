<?php

declare(strict_types=1);
namespace App\Service;

use App\Entity\Event;
use App\Entity\EventOccurrence;
use App\Enum\EventRecurrence;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class EventSchedule
{
    public const MAX_OCCURRENCES = 367;
    public function __construct(#[Autowire('%portal.timezone%')] private string $timezone) {}

    /** Local calendar arithmetic preserves wall-clock times across DST; invalid month days are skipped. @return list<array{\DateTimeImmutable, \DateTimeImmutable}> */
    public function dates(Event $event): array
    {
        if ($event->getStartsAt() === null || $event->getEndsAt() === null) { throw new \InvalidArgumentException('Beginn und Ende erforderlich.'); }
        $zone = new \DateTimeZone($this->timezone);
        $start = $event->getStartsAt()->setTimezone($zone);
        $end = $event->getEndsAt()->setTimezone($zone);
        if ($event->isAllDay()) { $start = $start->setTime(0, 0); $end = $end->setTime(23, 59, 59); }
        if ($end < $start) { throw new \InvalidArgumentException('Ungültiger Zeitraum.'); }
        $calendarStart = new \DateTimeImmutable($start->format('Y-m-d'), new \DateTimeZone('UTC'));
        $calendarEnd = new \DateTimeImmutable($end->format('Y-m-d'), new \DateTimeZone('UTC'));
        $days = (int) $calendarStart->diff($calendarEnd)->format('%a');
        $until = $event->getRecurrenceUntil()?->format('Y-m-d');
        if ($event->getRecurrenceType() !== EventRecurrence::None && ($until === null || $until < $start->format('Y-m-d') || $until > $start->modify('+1 year')->format('Y-m-d'))) {
            throw new \InvalidArgumentException('Das Serienende muss zwischen Beginn und höchstens einem Jahr danach liegen.');
        }
        $dates = []; $utc = new \DateTimeZone('UTC');
        for ($index = 0; $index < self::MAX_OCCURRENCES; ++$index) {
            $candidate = match ($event->getRecurrenceType()) {
                EventRecurrence::None => $start,
                EventRecurrence::Daily => $start->modify('+'.$index.' days'),
                EventRecurrence::Weekly => $start->modify('+'.$index.' weeks'),
                EventRecurrence::Monthly => $start->modify('first day of this month')->modify('+'.$index.' months'),
            };
            if ($event->getRecurrenceType() === EventRecurrence::Monthly) {
                if ($until !== null && $candidate->format('Y-m-01') > $until) { break; }
                if ((int) $start->format('d') > (int) $candidate->format('t')) { continue; }
                $candidate = $candidate->setDate((int) $candidate->format('Y'), (int) $candidate->format('m'), (int) $start->format('d'));
            }
            if ($index > 0 && ($until === null || $candidate->format('Y-m-d') > $until)) { break; }
            $occurrenceEnd = $candidate->modify('+'.$days.' days')->setTime((int) $end->format('H'), (int) $end->format('i'), (int) $end->format('s'));
            if ($event->isAllDay()) { $occurrenceEnd = $occurrenceEnd->setTime(23, 59, 59); }
            if ($occurrenceEnd < $candidate) {
                throw new \InvalidArgumentException('Ein Wiederholungstermin hat durch die Zeitumstellung einen ungültigen Zeitraum. Bitte andere Uhrzeiten wählen.');
            }
            $dates[] = [$candidate->setTimezone($utc), $occurrenceEnd->setTimezone($utc)];
            if ($event->getRecurrenceType() === EventRecurrence::None) { break; }
        }
        return $dates;
    }

    /** Reuse unchanged occurrences so content edits preserve occurrence URLs. Removed dates become orphans. */
    public function synchronize(Event $event): void
    {
        $existing = [];
        foreach ($event->getOccurrences() as $occurrence) { $existing[$occurrence->getStartsAt()->format('c')] = $occurrence; }
        $kept = [];
        $dates = $this->dates($event);
        if ($event->isAllDay()) { $event->setStartsAt($dates[0][0])->setEndsAt($dates[0][1]); }
        if ($event->getRecurrenceType() === EventRecurrence::None) { $event->setRecurrenceUntil(null); }
        foreach ($dates as [$start, $end]) {
            $key = $start->format('c');
            $occurrence = $existing[$key] ?? new EventOccurrence($event, $start, $end);
            $occurrence->reschedule($start, $end);
            $event->addOccurrence($occurrence); $kept[] = $occurrence;
        }
        foreach ($event->getOccurrences()->toArray() as $occurrence) { if (!in_array($occurrence, $kept, true)) { $event->getOccurrences()->removeElement($occurrence); } }
    }
}
