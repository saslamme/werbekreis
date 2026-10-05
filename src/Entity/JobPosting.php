<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\TimestampedTrait;
use App\Enum\{EmploymentType, WorkModel, SalaryPeriod, NewsStatus};
use App\Repository\JobPostingRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: JobPostingRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Index(name: 'job_publication', columns: ['status', 'publishedAt', 'validThrough'])]
#[ORM\Index(name: 'job_employment', columns: ['employmentType', 'workModel'])]
#[UniqueEntity(fields: ['slug'], message: 'Dieser Slug ist bereits vergeben.')]
final class JobPosting
{
    use TimestampedTrait;

    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'jobPostings')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    private ?Company $company = null;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank, Assert\Length(max: 180)]
    private string $title = '';

    #[ORM\Column(length: 180, unique: true)]
    #[Assert\NotBlank, Assert\Length(max: 180), Assert\Regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')]
    private string $slug = '';

    #[ORM\Column(length: 500)]
    #[Assert\NotBlank, Assert\Length(max: 500)]
    private string $shortDescription = '';

    #[ORM\Column(length: 180)]
    #[Assert\Length(max: 180)]
    private string $city = '';

    #[ORM\Column(type: 'text')]
    #[Assert\NotBlank]
    private string $description = '';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $requirements = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $benefits = null;

    #[ORM\Column(length: 20, enumType: NewsStatus::class)]
    private NewsStatus $status = NewsStatus::Draft;

    #[ORM\Column(length: 20, enumType: EmploymentType::class)]
    private EmploymentType $employmentType = EmploymentType::FullTime;

    #[ORM\Column(length: 20, enumType: WorkModel::class)]
    private WorkModel $workModel = WorkModel::Onsite;

    #[ORM\Column(length: 20, enumType: SalaryPeriod::class, nullable: true)]
    private ?SalaryPeriod $salaryPeriod = null;

    #[ORM\Column()]
    private bool $featured = false;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $publishedAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $validThrough = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $startsAt = null;

    #[ORM\Column(length: 2, nullable: true)]
    #[Assert\Country, Assert\Length(exactly: 2)]
    private ?string $addressCountry = null;

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
    #[Assert\Length(max: 180), Assert\Email]
    private ?string $applicationEmail = null;

    #[ORM\Column(length: 2048, nullable: true)]
    #[Assert\Length(max: 2048), Assert\Url(protocols: ['http', 'https'])]
    private ?string $applicationUrl = null;

    #[ORM\Column(length: 180, nullable: true)]
    #[Assert\Length(max: 180)]
    private ?string $contactName = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Assert\Length(max: 50)]
    private ?string $contactPhone = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Assert\Length(max: 100)]
    private ?string $referenceNumber = null;

    #[ORM\Column(nullable: true)]
    #[Assert\Range(min: -90, max: 90)]
    private ?float $latitude = null;

    #[ORM\Column(nullable: true)]
    #[Assert\Range(min: -180, max: 180)]
    private ?float $longitude = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    #[Assert\Regex(pattern: '/^[0-9]{1,8}(?:\.[0-9]{1,2})?$/D', message: 'Bitte einen nicht negativen Betrag mit höchstens zwei Nachkommastellen eingeben.')]
    private ?string $salaryMin = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    #[Assert\Regex(pattern: '/^[0-9]{1,8}(?:\.[0-9]{1,2})?$/D', message: 'Bitte einen nicht negativen Betrag mit höchstens zwei Nachkommastellen eingeben.')]
    private ?string $salaryMax = null;

    public function __construct(?\DateTimeImmutable $createdAt = null)
    {
        $this->createdAt = $this->updatedAt = $createdAt ?? Clock::get()->now();
    }

    #[ORM\PreUpdate]
    public function touch(): void { $this->updatedAt = Clock::get()->now(); }
    public function getId(): ?int { return $this->id; }
    public function getCompany(): ?Company { return $this->company; }
    public function setCompany(?Company $company): self
    {
        if ($this->company === $company) { return $this; }
        $old = $this->company; $this->company = $company;
        $old?->removeJobPosting($this); $company?->addJobPosting($this);
        return $this;
    }

    public function getTitle(): string { return $this->title; }
    public function setTitle(string $value): self { $this->title = trim($value); return $this; }

    public function getSlug(): string { return $this->slug; }
    public function setSlug(string $value): self { $this->slug = trim($value); return $this; }

    public function getShortDescription(): string { return $this->shortDescription; }
    public function setShortDescription(string $value): self { $this->shortDescription = $value; return $this; }

    public function getCity(): string { return $this->city; }
    public function setCity(string $value): self { $this->city = trim($value); return $this; }

    public function getDescription(): string { return $this->description; }
    public function setDescription(string $value): self { $this->description = $value; return $this; }

    public function getRequirements(): ?string { return $this->requirements; }
    public function setRequirements(?string $value): self { $this->requirements = $value !== null && trim($value) !== "" ? trim($value) : null; return $this; }

    public function getBenefits(): ?string { return $this->benefits; }
    public function setBenefits(?string $value): self { $this->benefits = $value !== null && trim($value) !== "" ? trim($value) : null; return $this; }

    public function getStatus(): NewsStatus { return $this->status; }
    public function setStatus(NewsStatus $value): self { $this->status = $value; return $this; }

    public function getEmploymentType(): EmploymentType { return $this->employmentType; }
    public function setEmploymentType(EmploymentType $value): self { $this->employmentType = $value; return $this; }

    public function getWorkModel(): WorkModel { return $this->workModel; }
    public function setWorkModel(WorkModel $value): self { $this->workModel = $value; return $this; }

    public function getSalaryPeriod(): ?SalaryPeriod { return $this->salaryPeriod; }
    public function setSalaryPeriod(?SalaryPeriod $value): self { $this->salaryPeriod = $value; return $this; }

    public function isFeatured(): bool { return $this->featured; }
    public function setFeatured(bool $value): self { $this->featured = $value; return $this; }

    public function getPublishedAt(): ?\DateTimeImmutable { return $this->publishedAt; }
    public function setPublishedAt(?\DateTimeImmutable $value): self { $this->publishedAt = $value; return $this; }

    public function getValidThrough(): ?\DateTimeImmutable { return $this->validThrough; }
    public function setValidThrough(?\DateTimeImmutable $value): self { $this->validThrough = $value; return $this; }

    public function getStartsAt(): ?\DateTimeImmutable { return $this->startsAt; }
    public function setStartsAt(?\DateTimeImmutable $value): self { $this->startsAt = $value; return $this; }

    public function getLocationName(): ?string { return $this->locationName; }
    public function setLocationName(?string $value): self { $this->locationName = $value !== null && trim($value) !== "" ? trim($value) : null; return $this; }

    public function getStreet(): ?string { return $this->street; }
    public function setStreet(?string $value): self { $this->street = $value !== null && trim($value) !== "" ? trim($value) : null; return $this; }

    public function getHouseNumber(): ?string { return $this->houseNumber; }
    public function setHouseNumber(?string $value): self { $this->houseNumber = $value !== null && trim($value) !== "" ? trim($value) : null; return $this; }

    public function getPostalCode(): ?string { return $this->postalCode; }
    public function setPostalCode(?string $value): self { $this->postalCode = $value !== null && trim($value) !== "" ? trim($value) : null; return $this; }

    public function getApplicationEmail(): ?string { return $this->applicationEmail; }
    public function setApplicationEmail(?string $value): self { $this->applicationEmail = $value !== null && trim($value) !== "" ? trim($value) : null; return $this; }

    public function getApplicationUrl(): ?string { return $this->applicationUrl; }
    public function setApplicationUrl(?string $value): self { $this->applicationUrl = $value !== null && trim($value) !== "" ? trim($value) : null; return $this; }

    public function getContactName(): ?string { return $this->contactName; }
    public function setContactName(?string $value): self { $this->contactName = $value !== null && trim($value) !== "" ? trim($value) : null; return $this; }

    public function getContactPhone(): ?string { return $this->contactPhone; }
    public function setContactPhone(?string $value): self { $this->contactPhone = $value !== null && trim($value) !== "" ? trim($value) : null; return $this; }

    public function getReferenceNumber(): ?string { return $this->referenceNumber; }
    public function setReferenceNumber(?string $value): self { $this->referenceNumber = $value !== null && trim($value) !== "" ? trim($value) : null; return $this; }

    public function getLatitude(): ?float { return $this->latitude; }
    public function setLatitude(?float $value): self { $this->latitude = $value; return $this; }

    public function getLongitude(): ?float { return $this->longitude; }
    public function setLongitude(?float $value): self { $this->longitude = $value; return $this; }

    public function getSalaryMin(): ?string { return $this->salaryMin; }
    public function setSalaryMin(?string $value): self { $this->salaryMin = self::normalizeSalary($value); return $this; }

    public function getSalaryMax(): ?string { return $this->salaryMax; }
    public function setSalaryMax(?string $value): self { $this->salaryMax = self::normalizeSalary($value); return $this; }

    public function getAddressCountry(): ?string { return $this->addressCountry; }
    public function setAddressCountry(?string $value): self { $this->addressCountry = $value !== null && trim($value) !== '' ? strtoupper(trim($value)) : null; return $this; }

    public function getPublicationDate(): \DateTimeImmutable { return $this->publishedAt ?? $this->createdAt; }
    public function getEffectiveApplicationEmail(): ?string { return $this->applicationEmail ?? $this->company?->getEmail(); }
    public function hasCoordinates(): bool { return $this->latitude !== null && $this->longitude !== null; }
    public function isExpired(\DateTimeImmutable $now): bool { return $this->validThrough !== null && $this->validThrough < $now; }
    public function isScheduled(\DateTimeImmutable $now): bool
    {
        return $this->status !== NewsStatus::Draft && $this->publishedAt !== null && $this->publishedAt > $now;
    }
    public function isPubliclyVisible(\DateTimeImmutable $now): bool
    {
        return $this->company?->isActive() === true
            && in_array($this->status->value, NewsStatus::publishableValues(), true)
            && ($this->status !== NewsStatus::Scheduled || $this->publishedAt !== null)
            && ($this->publishedAt === null || $this->publishedAt <= $now)
            && !$this->isExpired($now);
    }
    public function statusAt(\DateTimeImmutable $now): string
    {
        return match (true) {
            $this->status === NewsStatus::Draft => 'Entwurf',
            $this->company?->isActive() !== true => 'Unternehmen inaktiv',
            $this->isExpired($now) => 'Abgelaufen',
            $this->isPubliclyVisible($now) => 'Veröffentlicht',
            default => 'Geplant',
        };
    }
    public function copyLocationFromCompany(Company $company): self
    {
        $this->street = $company->getStreet(); $this->houseNumber = $company->getHouseNumber();
        $this->postalCode = $company->getPostalCode(); $this->city = $company->getCity() ?? '';
        $this->latitude = $company->getLatitude(); $this->longitude = $company->getLongitude();
        return $this;
    }
    #[Assert\Callback]
    public function validate(ExecutionContextInterface $context): void
    {
        if ($this->status === NewsStatus::Scheduled && $this->publishedAt === null) {
            $context->buildViolation('Geplante Stellen benötigen einen Veröffentlichungszeitpunkt.')->atPath('publishedAt')->addViolation();
        }
        if ($this->validThrough !== null && $this->publishedAt !== null && $this->validThrough < $this->publishedAt) {
            $context->buildViolation('Die Bewerbungsfrist darf nicht vor der Veröffentlichung liegen.')->atPath('validThrough')->addViolation();
        }
        if ($this->workModel !== WorkModel::Remote && trim($this->city) === '') {
            $context->buildViolation('Bitte den Arbeitsort angeben.')->atPath('city')->addViolation();
        }
        if (($this->latitude === null) !== ($this->longitude === null) || ($this->latitude !== null && (!is_finite($this->latitude) || !is_finite($this->longitude)))) {
            $context->buildViolation('Bitte beide gültigen Koordinaten angeben oder beide leer lassen.')->atPath('latitude')->addViolation();
        }
        if ($this->applicationUrl === null && $this->getEffectiveApplicationEmail() === null) {
            $context->buildViolation('Bitte eine Bewerbungs-URL oder E-Mail angeben; alternativ die E-Mail des Unternehmens ergänzen.')->atPath('applicationEmail')->addViolation();
        }
        $min = self::minorUnits($this->salaryMin); $max = self::minorUnits($this->salaryMax);
        if ($min !== null && $max !== null && $min > $max) {
            $context->buildViolation('Das Höchstgehalt darf nicht unter dem Mindestgehalt liegen.')->atPath('salaryMax')->addViolation();
        }
        if (($this->salaryMin !== null || $this->salaryMax !== null) && $this->salaryPeriod === null) {
            $context->buildViolation('Bitte den Gehaltszeitraum wählen.')->atPath('salaryPeriod')->addViolation();
        }
    }
    private static function normalizeSalary(?string $price): ?string
    {
        $price = $price !== null ? trim($price) : '';
        if ($price === '') {
            return null;
        }
        if (preg_match('/^[0-9]{1,8}(?:\.[0-9]{1,2})?$/D', $price)) {
            [$euros, $cents] = array_pad(explode('.', $price), 2, '');

            return (string) (int) $euros.'.'.str_pad($cents, 2, '0');
        }

        return $price; // Retain invalid input for Symfony validation; never silently round it.
    }

    private static function minorUnits(?string $price): ?int
    {
        if ($price === null || !preg_match('/^[0-9]{1,8}(?:\.[0-9]{1,2})?$/D', $price)) {
            return null;
        }
        [$euros, $cents] = array_pad(explode('.', $price), 2, '');

        return (int) $euros * 100 + (int) str_pad($cents, 2, '0');
    }

    private static function formatSalary(?string $price): ?string
    {
        if (self::minorUnits($price) === null) {
            return null;
        }
        [$euros, $cents] = explode('.', self::normalizeSalary($price));

        return preg_replace('/\B(?=(\d{3})+(?!\d))/', '.', $euros).','.$cents.' €';
    }
    public function getFormattedSalaryMin(): ?string { return self::formatSalary($this->salaryMin); }
    public function getFormattedSalaryMax(): ?string { return self::formatSalary($this->salaryMax); }
}
