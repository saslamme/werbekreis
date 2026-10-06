<?php

declare(strict_types=1);
namespace App\Entity;

use App\Entity\Traits\TimestampedTrait;
use App\Enum\EventRecurrence;
use App\Repository\EventRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: EventRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[\App\Validator\EventDates]
#[UniqueEntity(fields: ['slug'], service: \App\Validator\DraftAwareUniqueEntityValidator::class, message: 'Dieser Slug ist bereits vergeben.')]
final class Event implements \App\Moderation\ModeratedContent
{
    use TimestampedTrait;
    use \App\Entity\Traits\ModerationTrait;
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank, Assert\Length(max: 180)]
    private string $title = '';

    #[ORM\Column(length: 180, unique: true)]
    #[Assert\NotBlank, Assert\Length(max: 180), Assert\Regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')]
    private string $slug = '';

    #[ORM\Column(length: 500, nullable: true)]
    #[Assert\Length(max: 500)]
    private ?string $shortDescription = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column]
    private bool $active = true;

    #[ORM\Column]
    private bool $featured = false;

    #[ORM\ManyToOne(inversedBy: 'events')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Company $company = null;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Assert\NotNull]
    private ?\DateTimeImmutable $startsAt = null;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Assert\NotNull]
    private ?\DateTimeImmutable $endsAt = null;

    #[ORM\Column]
    private bool $allDay = false;

    #[ORM\Column(length: 20, enumType: EventRecurrence::class)]
    private EventRecurrence $recurrenceType = EventRecurrence::None;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $recurrenceUntil = null;

    #[ORM\Column]
    private bool $cancelled = false;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $cancellationNotice = null;

    #[ORM\Column]
    private bool $freeAdmission = false;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $admissionText = null;

    #[ORM\Column(length: 180, nullable: true)]
    #[Assert\Length(max: 180)]
    private ?string $organizerName = null;

    #[ORM\Column(length: 180, nullable: true)]
    #[Assert\Length(max: 180)]
    #[Assert\Email]
    private ?string $organizerEmail = null;

    #[ORM\Column(length: 80, nullable: true)]
    #[Assert\Length(max: 80)]
    private ?string $organizerPhone = null;

    #[ORM\Column(length: 2048, nullable: true)]
    #[Assert\Length(max: 2048)]
    #[Assert\Url(protocols: ['http', 'https'])]
    private ?string $organizerWebsite = null;

    #[ORM\Column(length: 180, nullable: true)]
    #[Assert\Length(max: 180)]
    private ?string $locationName = null;

    #[ORM\Column(length: 180, nullable: true)]
    #[Assert\Length(max: 180)]
    private ?string $street = null;

    #[ORM\Column(length: 30, nullable: true)]
    #[Assert\Length(max: 30)]
    private ?string $houseNumber = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Assert\Length(max: 20)]
    private ?string $postalCode = null;

    #[ORM\Column(length: 180, nullable: true)]
    #[Assert\Length(max: 180)]
    private ?string $city = null;

    #[ORM\Column(length: 2048, nullable: true)]
    #[Assert\Length(max: 2048)]
    #[Assert\Url(protocols: ['http', 'https'])]
    private ?string $externalUrl = null;

    #[ORM\Column(length: 2048, nullable: true)]
    #[Assert\Length(max: 2048)]
    #[Assert\Url(protocols: ['http', 'https'])]
    private ?string $ticketUrl = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Assert\Length(max: 100)]
    #[Assert\Regex('/^[a-f0-9]{32}\.(?:jpg|png|webp)$/D')]
    private ?string $imagePath = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $imageAlt = null;

    #[ORM\Column(nullable: true)]
    #[Assert\Range(min: -90, max: 90)]
    private ?float $latitude = null;

    #[ORM\Column(nullable: true)]
    #[Assert\Range(min: -180, max: 180)]
    private ?float $longitude = null;

    /** @var Collection<int, EventCategory> */
    #[ORM\ManyToMany(targetEntity: EventCategory::class, inversedBy: 'events')]
    private Collection $categories;

    /** @var Collection<int, EventOccurrence> */
    #[ORM\OneToMany(targetEntity: EventOccurrence::class, mappedBy: 'event', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['startsAt' => 'ASC'])]
    private Collection $occurrences;

    public function __construct()
    {
        $this->initializeTimestamps();
        $this->categories = new ArrayCollection();
        $this->occurrences = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = trim($title);

        return $this;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): self
    {
        $this->slug = trim($slug);

        return $this;
    }

    public function getShortDescription(): ?string
    {
        return $this->shortDescription;
    }

    public function setShortDescription(?string $shortDescription): self
    {
        $this->shortDescription = $shortDescription;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): self
    {
        $this->active = $active;

        return $this;
    }

    public function isFeatured(): bool
    {
        return $this->featured;
    }

    public function setFeatured(bool $featured): self
    {
        $this->featured = $featured;

        return $this;
    }

    public function getCompany(): ?Company
    {
        return $this->company;
    }

    public function setCompany(?Company $company): self
    {
        if ($this->company === $company) { return $this; }
        $old = $this->company;
        $this->company = $company;
        $old?->removeEvent($this);
        $company?->addEvent($this);

        return $this;
    }

    public function getStartsAt(): ?\DateTimeImmutable
    {
        return $this->startsAt;
    }

    public function setStartsAt(?\DateTimeImmutable $startsAt): self
    {
        $this->startsAt = $startsAt;

        return $this;
    }

    public function getEndsAt(): ?\DateTimeImmutable
    {
        return $this->endsAt;
    }

    public function setEndsAt(?\DateTimeImmutable $endsAt): self
    {
        $this->endsAt = $endsAt;

        return $this;
    }

    public function isAllDay(): bool
    {
        return $this->allDay;
    }

    public function setAllDay(bool $allDay): self
    {
        $this->allDay = $allDay;

        return $this;
    }

    public function getRecurrenceType(): EventRecurrence
    {
        return $this->recurrenceType;
    }

    public function setRecurrenceType(EventRecurrence $recurrenceType): self
    {
        $this->recurrenceType = $recurrenceType;

        return $this;
    }

    public function getRecurrenceUntil(): ?\DateTimeImmutable
    {
        return $this->recurrenceUntil;
    }

    public function setRecurrenceUntil(?\DateTimeImmutable $recurrenceUntil): self
    {
        $this->recurrenceUntil = $recurrenceUntil === null ? null : new \DateTimeImmutable($recurrenceUntil->format('Y-m-d'), new \DateTimeZone('UTC'));

        return $this;
    }

    public function isCancelled(): bool
    {
        return $this->cancelled;
    }

    public function setCancelled(bool $cancelled): self
    {
        $this->cancelled = $cancelled;

        return $this;
    }

    public function getCancellationNotice(): ?string
    {
        return $this->cancellationNotice;
    }

    public function setCancellationNotice(?string $cancellationNotice): self
    {
        $this->cancellationNotice = $cancellationNotice;

        return $this;
    }

    public function isFreeAdmission(): bool
    {
        return $this->freeAdmission;
    }

    public function setFreeAdmission(bool $freeAdmission): self
    {
        $this->freeAdmission = $freeAdmission;

        return $this;
    }

    public function getAdmissionText(): ?string
    {
        return $this->admissionText;
    }

    public function setAdmissionText(?string $admissionText): self
    {
        $this->admissionText = $admissionText;

        return $this;
    }

    public function getOrganizerName(): ?string
    {
        return $this->organizerName;
    }

    public function setOrganizerName(?string $organizerName): self
    {
        $this->organizerName = $organizerName;

        return $this;
    }

    public function getOrganizerEmail(): ?string
    {
        return $this->organizerEmail;
    }

    public function setOrganizerEmail(?string $organizerEmail): self
    {
        $this->organizerEmail = $organizerEmail;

        return $this;
    }

    public function getOrganizerPhone(): ?string
    {
        return $this->organizerPhone;
    }

    public function setOrganizerPhone(?string $organizerPhone): self
    {
        $this->organizerPhone = $organizerPhone;

        return $this;
    }

    public function getOrganizerWebsite(): ?string
    {
        return $this->organizerWebsite;
    }

    public function setOrganizerWebsite(?string $organizerWebsite): self
    {
        $this->organizerWebsite = $organizerWebsite;

        return $this;
    }

    public function getLocationName(): ?string
    {
        return $this->locationName;
    }

    public function setLocationName(?string $locationName): self
    {
        $this->locationName = $locationName;

        return $this;
    }

    public function getStreet(): ?string
    {
        return $this->street;
    }

    public function setStreet(?string $street): self
    {
        $this->street = $street;

        return $this;
    }

    public function getHouseNumber(): ?string
    {
        return $this->houseNumber;
    }

    public function setHouseNumber(?string $houseNumber): self
    {
        $this->houseNumber = $houseNumber;

        return $this;
    }

    public function getPostalCode(): ?string
    {
        return $this->postalCode;
    }

    public function setPostalCode(?string $postalCode): self
    {
        $this->postalCode = $postalCode;

        return $this;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function setCity(?string $city): self
    {
        $this->city = $city;

        return $this;
    }

    public function getExternalUrl(): ?string
    {
        return $this->externalUrl;
    }

    public function setExternalUrl(?string $externalUrl): self
    {
        $this->externalUrl = $externalUrl;

        return $this;
    }

    public function getTicketUrl(): ?string
    {
        return $this->ticketUrl;
    }

    public function setTicketUrl(?string $ticketUrl): self
    {
        $this->ticketUrl = $ticketUrl;

        return $this;
    }

    public function getImagePath(): ?string
    {
        return $this->imagePath;
    }

    public function setImagePath(?string $imagePath): self
    {
        $this->imagePath = $imagePath;

        return $this;
    }

    public function getImageAlt(): ?string
    {
        return $this->imageAlt;
    }

    public function setImageAlt(?string $imageAlt): self
    {
        $this->imageAlt = $imageAlt;

        return $this;
    }

    public function getLatitude(): ?float
    {
        return $this->latitude;
    }

    public function setLatitude(?float $latitude): self
    {
        $this->latitude = $latitude;

        return $this;
    }

    public function getLongitude(): ?float
    {
        return $this->longitude;
    }

    public function setLongitude(?float $longitude): self
    {
        $this->longitude = $longitude;

        return $this;
    }

    /** @return Collection<int, EventCategory> */
    public function getCategories(): Collection { return $this->categories; }
    /** @return list<EventCategory> */
    public function getPublicCategories(): array
    {
        return $this->categories->filter(static fn (EventCategory $category): bool => $category->isActive())->getValues();
    }
    public function addCategory(EventCategory $category): self
    {
        if (!$this->categories->contains($category)) { $this->categories->add($category); if (!$this->isRevisionShadow()) { $category->addEvent($this); } }
        return $this;
    }
    public function removeCategory(EventCategory $category): self
    {
        if ($this->categories->removeElement($category)) { if (!$this->isRevisionShadow()) { $category->removeEvent($this); } }
        return $this;
    }
    /** @return Collection<int, EventOccurrence> */
    public function getOccurrences(): Collection { return $this->occurrences; }
    public function addOccurrence(EventOccurrence $occurrence): self
    {
        if (!$this->occurrences->contains($occurrence)) { $this->occurrences->add($occurrence); }
        return $this;
    }
    public function getFileName(): string { return $this->imagePath ?? ''; }
    public function setFileName(string $name): self { return $this->setImagePath($name === '' ? null : $name); }
    public function hasCoordinates(): bool { return $this->latitude !== null && $this->longitude !== null; }
    public function getOrganizerLabel(): string { return $this->organizerName ?: ($this->company?->getName() ?? ''); }

    public function representativeOccurrence(\DateTimeImmutable $now): ?EventOccurrence
    {
        foreach ($this->occurrences as $occurrence) {
            if ($occurrence->getEndsAt() >= $now) { return $occurrence; }
        }
        return $this->occurrences->last() ?: null;
    }

    #[Assert\Callback]
    public function validate(ExecutionContextInterface $context): void
    {
        if (!$this->allDay && $this->startsAt !== null && $this->endsAt !== null && $this->endsAt < $this->startsAt) {
            $context->buildViolation('Das Ende darf nicht vor dem Beginn liegen.')->atPath('endsAt')->addViolation();
        }
        if (($this->latitude === null) !== ($this->longitude === null)) {
            $context->buildViolation('Bitte beide Koordinaten eingeben oder beide leer lassen.')->atPath('latitude')->addViolation();
        }
        if ($this->recurrenceType !== EventRecurrence::None) {
            if ($this->recurrenceUntil === null) {
                $context->buildViolation('Wiederholungen benötigen ein Enddatum.')->atPath('recurrenceUntil')->addViolation();
            }
        }
    }
}
