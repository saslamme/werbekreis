<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\TimestampedTrait;
use App\Enum\OfferType;
use App\Repository\OfferRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: OfferRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Index(name: 'offer_schedule', columns: ['active', 'startsAt', 'endsAt'])]
#[UniqueEntity(fields: ['slug'], service: \App\Validator\DraftAwareUniqueEntityValidator::class, message: 'Dieser Slug ist bereits vergeben.')]
final class Offer implements \App\Moderation\ModeratedContent
{
    use TimestampedTrait;
    use \App\Entity\Traits\ModerationTrait;

    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'offers')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    private ?Company $company = null;

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

    #[ORM\Column(length: 20, enumType: OfferType::class)]
    private OfferType $type = OfferType::Offer;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    #[Assert\Regex(pattern: '/^[0-9]{1,8}(?:\.[0-9]{1,2})?$/D', message: 'Bitte einen nicht negativen Preis mit höchstens zwei Nachkommastellen eingeben.')]
    private ?string $regularPrice = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    #[Assert\Regex(pattern: '/^[0-9]{1,8}(?:\.[0-9]{1,2})?$/D', message: 'Bitte einen nicht negativen Preis mit höchstens zwei Nachkommastellen eingeben.')]
    private ?string $offerPrice = null;

    #[ORM\Column(length: 120, nullable: true)]
    #[Assert\Length(max: 120)]
    private ?string $discountText = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $startsAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $endsAt = null;

    #[ORM\Column]
    private bool $active = true;

    #[ORM\Column]
    private bool $featured = false;

    #[ORM\Column(length: 100, nullable: true)]
    #[Assert\Regex('/^[a-f0-9]{32}\.(?:jpg|png|webp)$/D')]
    private ?string $imagePath = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $imageAlt = null;

    #[ORM\Column(length: 2048, nullable: true)]
    #[Assert\Length(max: 2048), Assert\Url(protocols: ['http', 'https'])]
    private ?string $externalUrl = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $terms = null;

    public function __construct()
    {
        $this->initializeTimestamps();
    }

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
        if ($this->company === $company) {
            return $this;
        }
        $previous = $this->company;
        $this->company = $company;
        $previous?->removeOffer($this);
        $company?->addOffer($this);

        return $this;
    }

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
        $this->shortDescription = $shortDescription !== null && trim($shortDescription) !== '' ? trim($shortDescription) : null;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description !== null && trim($description) !== '' ? trim($description) : null;

        return $this;
    }

    public function getType(): OfferType
    {
        return $this->type;
    }

    public function setType(OfferType $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getRegularPrice(): ?string
    {
        return $this->regularPrice;
    }

    public function setRegularPrice(?string $regularPrice): self
    {
        $this->regularPrice = self::normalizePrice($regularPrice);

        return $this;
    }

    public function getOfferPrice(): ?string
    {
        return $this->offerPrice;
    }

    public function setOfferPrice(?string $offerPrice): self
    {
        $this->offerPrice = self::normalizePrice($offerPrice);

        return $this;
    }

    public function getDiscountText(): ?string
    {
        return $this->discountText;
    }

    public function setDiscountText(?string $discountText): self
    {
        $this->discountText = $discountText !== null && trim($discountText) !== '' ? trim($discountText) : null;

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

    public function getImagePath(): ?string
    {
        return $this->imagePath;
    }

    public function setImagePath(?string $imagePath): self
    {
        $this->imagePath = $imagePath !== null && trim($imagePath) !== '' ? trim($imagePath) : null;

        return $this;
    }

    public function getImageAlt(): ?string
    {
        return $this->imageAlt;
    }

    public function setImageAlt(?string $imageAlt): self
    {
        $this->imageAlt = $imageAlt !== null && trim($imageAlt) !== '' ? trim($imageAlt) : null;

        return $this;
    }

    public function getExternalUrl(): ?string
    {
        return $this->externalUrl;
    }

    public function setExternalUrl(?string $externalUrl): self
    {
        $this->externalUrl = $externalUrl !== null && trim($externalUrl) !== '' ? trim($externalUrl) : null;

        return $this;
    }

    public function getTerms(): ?string
    {
        return $this->terms;
    }

    public function setTerms(?string $terms): self
    {
        $this->terms = $terms !== null && trim($terms) !== '' ? trim($terms) : null;

        return $this;
    }

    public function getTypeLabel(): string
    {
        return $this->type->label();
    }

    /** Shared scheduling semantics: inclusive boundaries, with inactive companies hidden too. */
    public function isCurrentlyActive(\DateTimeImmutable $now): bool
    {
        return $this->isModerationApproved() && $this->company?->isModerationApproved() === true && $this->active && $this->company?->isActive() === true
            && ($this->startsAt === null || $this->startsAt <= $now)
            && ($this->endsAt === null || $this->endsAt >= $now);
    }

    public function statusAt(\DateTimeImmutable $now): string
    {
        if (!$this->active) {
            return 'Deaktiviert';
        }
        if ($this->company?->isActive() !== true) {
            return 'Unternehmen inaktiv';
        }
        if ($this->startsAt !== null && $this->startsAt > $now) {
            return 'Geplant';
        }
        if ($this->endsAt !== null && $this->endsAt < $now) {
            return 'Abgelaufen';
        }

        return 'Aktuell';
    }

    #[Assert\Callback]
    public function validate(ExecutionContextInterface $context): void
    {
        if ($this->startsAt !== null && $this->endsAt !== null && $this->startsAt > $this->endsAt) {
            $context->buildViolation('Das Ende darf nicht vor dem Start liegen.')->atPath('endsAt')->addViolation();
        }
        $regular = self::minorUnits($this->regularPrice);
        $offer = self::minorUnits($this->offerPrice);
        if ($regular !== null && $offer !== null && $offer > $regular) {
            $context->buildViolation('Der Angebotspreis darf den regulären Preis nicht überschreiten.')->atPath('offerPrice')->addViolation();
        }
    }

    public function getFormattedRegularPrice(): ?string
    {
        return self::formatPrice($this->regularPrice);
    }

    public function getFormattedOfferPrice(): ?string
    {
        return self::formatPrice($this->offerPrice);
    }

    /** Adapter to the shared company/offer image storage; never accepts an arbitrary filesystem path. */
    public function getFileName(): string
    {
        return $this->imagePath ?? '';
    }

    public function setFileName(string $name): self
    {
        return $this->setImagePath($name);
    }

    private static function normalizePrice(?string $price): ?string
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

    private static function formatPrice(?string $price): ?string
    {
        if (self::minorUnits($price) === null) {
            return null;
        }
        [$euros, $cents] = explode('.', self::normalizePrice($price));

        return preg_replace('/\B(?=(\d{3})+(?!\d))/', '.', $euros).','.$cents.' €';
    }
}
