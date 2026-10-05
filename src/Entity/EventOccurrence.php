<?php

declare(strict_types=1);
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
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
        $this->event = $event; $this->startsAt = $startsAt; $this->endsAt = $endsAt;
    }
    public function reschedule(\DateTimeImmutable $start, \DateTimeImmutable $end): void
    {
        $this->startsAt = $start; $this->endsAt = $end;
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
