<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use App\Entity\Traits\TimestampedTrait;
use App\Repository\CompanyRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: CompanyRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['slug'], message: 'Dieser Slug ist bereits vergeben.')]
final class Company
{
    use TimestampedTrait;

    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank, Assert\Length(max: 180)]
    private string $name = '';

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

    #[ORM\Column(length: 50, nullable: true)]
    #[Assert\Length(max: 50)]
    private ?string $phone = null;

    #[ORM\Column(length: 180, nullable: true)]
    #[Assert\Length(max: 180), Assert\Email]
    private ?string $email = null;

    #[ORM\Column(length: 2048, nullable: true)]
    #[Assert\Length(max: 2048), Assert\Url(protocols: ['http', 'https'])]
    private ?string $website = null;

    #[ORM\Column(length: 2048, nullable: true)]
    #[Assert\Length(max: 2048), Assert\Url(protocols: ['http', 'https'])]
    private ?string $facebookUrl = null;

    #[ORM\Column(length: 2048, nullable: true)]
    #[Assert\Length(max: 2048), Assert\Url(protocols: ['http', 'https'])]
    private ?string $instagramUrl = null;

    #[ORM\Column(nullable: true)]
    #[Assert\Range(min: -90, max: 90)]
    private ?float $latitude = null;

    #[ORM\Column(nullable: true)]
    #[Assert\Range(min: -180, max: 180)]
    private ?float $longitude = null;

    /** @var Collection<int, Category> */
    #[ORM\ManyToMany(targetEntity: Category::class, inversedBy: 'companies')]
    #[ORM\JoinTable(name: 'company_category')]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(onDelete: 'RESTRICT')]
    private Collection $categories;

    /** @var Collection<int, CompanyImage> */
    #[ORM\OneToMany(targetEntity: CompanyImage::class, mappedBy: 'company', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC', 'id' => 'ASC'])]
    #[Assert\Valid]
    private Collection $images;

    /** @var Collection<int, OpeningHour> */
    #[ORM\OneToMany(targetEntity: OpeningHour::class, mappedBy: 'company', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['dayOfWeek' => 'ASC', 'id' => 'ASC'])]
    #[Assert\Valid, Assert\Count(max: 50)]
    private Collection $openingHours;

    /** @var Collection<int, ContactPerson> */
    #[ORM\OneToMany(targetEntity: ContactPerson::class, mappedBy: 'company', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['sortOrder' => 'ASC', 'id' => 'ASC'])]
    #[Assert\Valid, Assert\Count(max: 50)]
    private Collection $contactPersons;

    public function __construct()
    {
        $this->initializeTimestamps();
        $this->categories = new ArrayCollection();
        $this->images = new ArrayCollection();
        $this->openingHours = new ArrayCollection();
        $this->contactPersons = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = trim($name);

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

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): self
    {
        $this->phone = $phone;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): self
    {
        $this->email = $email;

        return $this;
    }

    public function getWebsite(): ?string
    {
        return $this->website;
    }

    public function setWebsite(?string $website): self
    {
        $this->website = $website;

        return $this;
    }

    public function getFacebookUrl(): ?string
    {
        return $this->facebookUrl;
    }

    public function setFacebookUrl(?string $facebookUrl): self
    {
        $this->facebookUrl = $facebookUrl;

        return $this;
    }

    public function getInstagramUrl(): ?string
    {
        return $this->instagramUrl;
    }

    public function setInstagramUrl(?string $instagramUrl): self
    {
        $this->instagramUrl = $instagramUrl;

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

    /** @return Collection<int, Category> */
    public function getCategories(): Collection
    {
        return $this->categories;
    }

    public function addCategory(Category $category): self
    {
        if (!$this->categories->contains($category)) {
            $this->categories->add($category);
            $category->addCompany($this);
        }

        return $this;
    }

    public function removeCategory(Category $category): self
    {
        if ($this->categories->removeElement($category)) {
            $category->removeCompany($this);
        }

        return $this;
    }

    /** @return Collection<int, CompanyImage> */
    public function getImages(): Collection
    {
        return $this->images;
    }

    public function addImage(CompanyImage $entry): self
    {
        if (!$this->images->contains($entry)) {
            $this->images->add($entry);
            $entry->setCompany($this);
        }

        return $this;
    }

    public function removeImage(CompanyImage $entry): self
    {
        if ($this->images->removeElement($entry) && $entry->getCompany() === $this) {
            $entry->setCompany(null);
        }

        return $this;
    }

    /** @return Collection<int, OpeningHour> */
    public function getOpeningHours(): Collection
    {
        return $this->openingHours;
    }

    public function addOpeningHour(OpeningHour $entry): self
    {
        if (!$this->openingHours->contains($entry)) {
            $this->openingHours->add($entry);
            $entry->setCompany($this);
        }

        return $this;
    }

    public function removeOpeningHour(OpeningHour $entry): self
    {
        if ($this->openingHours->removeElement($entry) && $entry->getCompany() === $this) {
            $entry->setCompany(null);
        }

        return $this;
    }

    /** @return Collection<int, ContactPerson> */
    public function getContactPersons(): Collection
    {
        return $this->contactPersons;
    }

    public function addContactPerson(ContactPerson $entry): self
    {
        if (!$this->contactPersons->contains($entry)) {
            $this->contactPersons->add($entry);
            $entry->setCompany($this);
        }

        return $this;
    }

    public function removeContactPerson(ContactPerson $entry): self
    {
        if ($this->contactPersons->removeElement($entry) && $entry->getCompany() === $this) {
            $entry->setCompany(null);
        }

        return $this;
    }

    #[Assert\Callback]
    public function validateDirectoryData(ExecutionContextInterface $context): void
    {
        if ($this->active && $this->categories->isEmpty()) {
            $context->buildViolation('Aktive Unternehmen benötigen mindestens eine Kategorie.')->atPath('categories')->addViolation();
        }
        $days = [];
        foreach ($this->openingHours as $entry) {
            $days[$entry->getDayOfWeek()][] = $entry;
        }
        foreach ($days as $entries) {
            $closed = array_filter($entries, static fn (OpeningHour $entry): bool => $entry->isClosed());
            if ($closed !== [] && count($entries) > 1) {
                $context->buildViolation('Ein geschlossener Tag darf keine weiteren Zeitfenster enthalten.')->atPath('openingHours')->addViolation();
            }
            $windows = array_filter($entries, static fn (OpeningHour $entry): bool => !$entry->isClosed() && $entry->getOpensAt() !== null && $entry->getClosesAt() !== null);
            usort($windows, static fn (OpeningHour $a, OpeningHour $b): int => $a->getOpensAt()->format('H:i') <=> $b->getOpensAt()->format('H:i'));
            $end = null;
            foreach ($windows as $window) {
                if ($end !== null && $window->getOpensAt()->format('H:i') < $end) {
                    $context->buildViolation('Öffnungszeiten am selben Tag dürfen sich nicht überschneiden.')->atPath('openingHours')->addViolation();
                    break;
                }
                $end = $window->getClosesAt()->format('H:i');
            }
        }
        if (count(array_filter($this->contactPersons->toArray(), static fn (ContactPerson $contact): bool => $contact->isPrimaryContact())) > 1) {
            $context->buildViolation('Bitte höchstens einen primären Ansprechpartner auswählen.')->atPath('contactPersons')->addViolation();
        }
    }
}
