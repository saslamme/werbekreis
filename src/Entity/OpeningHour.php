<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity]
final class OpeningHour
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'openingHours')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    private ?Company $company = null;

    #[ORM\Column(type: 'smallint')]
    #[Assert\Range(min: 1, max: 7)]
    private int $dayOfWeek = 1;

    #[ORM\Column(type: 'time_immutable', nullable: true)]
    private ?\DateTimeImmutable $opensAt = null;

    #[ORM\Column(type: 'time_immutable', nullable: true)]
    private ?\DateTimeImmutable $closesAt = null;

    #[ORM\Column]
    private bool $closed = false;

    #[ORM\Column]
    #[Assert\PositiveOrZero]
    private int $position = 0;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCompany(): ?Company
    {
        return $this->company;
    }

    public function setCompany(?Company $company): self
    {
        if ($this->company !== $company) {
            $previous = $this->company;
            $this->company = $company;
            $previous?->removeOpeningHour($this);
            $company?->addOpeningHour($this);
        }

        return $this;
    }

    public function getDayOfWeek(): int
    {
        return $this->dayOfWeek;
    }

    public function setDayOfWeek(int $dayOfWeek): self
    {
        $this->dayOfWeek = $dayOfWeek;

        return $this;
    }

    public function getOpensAt(): ?\DateTimeImmutable
    {
        return $this->opensAt;
    }

    public function setOpensAt(?\DateTimeImmutable $opensAt): self
    {
        $this->opensAt = $opensAt;

        return $this;
    }

    public function getClosesAt(): ?\DateTimeImmutable
    {
        return $this->closesAt;
    }

    public function setClosesAt(?\DateTimeImmutable $closesAt): self
    {
        $this->closesAt = $closesAt;

        return $this;
    }

    public function isClosed(): bool
    {
        return $this->closed;
    }

    public function setClosed(bool $closed): self
    {
        $this->closed = $closed;

        return $this;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): self
    {
        $this->position = $position;

        return $this;
    }

    #[Assert\Callback]
    public function validateTimes(ExecutionContextInterface $context): void
    {
        if ($this->closed) {
            if ($this->opensAt !== null || $this->closesAt !== null) {
                $context->buildViolation('Geschlossene Tage dürfen keine Uhrzeiten enthalten.')->atPath('closed')->addViolation();
            }

            return;
        }
        if ($this->opensAt === null || $this->closesAt === null) {
            $context->buildViolation('Für offene Zeitfenster sind beide Uhrzeiten erforderlich.')->atPath('opensAt')->addViolation();
        } elseif ($this->opensAt->format('H:i') >= $this->closesAt->format('H:i')) {
            $context->buildViolation('Die Schlusszeit muss nach der Öffnungszeit liegen.')->atPath('closesAt')->addViolation();
        }
    }
}
