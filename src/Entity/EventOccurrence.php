<?php

declare(strict_types=1);
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\HasLifecycleCallbacks]
#[ORM\UniqueConstraint(name: 'event_occurrence_date', columns: ['event_id', 'startsAt'])]
#[ORM\Index(name: 'event_occurrence_range', columns: ['startsAt', 'endsAt'])]
final class EventOccurrence
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;
    #[ORM\ManyToOne(inversedBy: 'occurrences')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Event $event;
    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $startsAt;
    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $endsAt;

    public function __construct(Event $event, \DateTimeImmutable $startsAt, \DateTimeImmutable $endsAt)
    {
        $this->event = $event; $this->reschedule($startsAt, $endsAt);
    }
    public function reschedule(\DateTimeImmutable $start, \DateTimeImmutable $end): void
    {
        $zone = new \DateTimeZone('UTC');
        $this->startsAt = $start->setTimezone($zone); $this->endsAt = $end->setTimezone($zone);
    }
    /** Generated occurrences are stored in UTC, unlike the editable local Event dates. */
    #[ORM\PostLoad]
    public function restoreUtcTimezone(): void
    {
        $zone = new \DateTimeZone('UTC');
        // MariaDB retains only the clock value; reattach its storage zone without conversion.
        $this->startsAt = new \DateTimeImmutable($this->startsAt->format('Y-m-d H:i:s'), $zone);
        $this->endsAt = new \DateTimeImmutable($this->endsAt->format('Y-m-d H:i:s'), $zone);
    }
    public function getId(): ?int { return $this->id; }
    public function getEvent(): Event { return $this->event; }
    public function getStartsAt(): \DateTimeImmutable { return $this->startsAt; }
    public function getEndsAt(): \DateTimeImmutable { return $this->endsAt; }
    public function statusAt(\DateTimeImmutable $now): string
    {
        return match (true) {
            !$this->event->isActive() => 'Deaktiviert',
            $this->event->isCancelled() => 'Abgesagt',
            $this->endsAt < $now => 'Vergangen',
            $this->startsAt > $now => 'Geplant',
            default => 'Läuft',
        };
    }
}
