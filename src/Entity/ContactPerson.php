<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity]
final class ContactPerson
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'contactPersons')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    private ?Company $company = null;

    #[ORM\Column(length: 100)]
    #[Assert\Length(max: 100), Assert\NotBlank]
    private string $firstName = '';

    #[ORM\Column(length: 100)]
    #[Assert\Length(max: 100), Assert\NotBlank]
    private string $lastName = '';

    #[ORM\Column(length: 180, nullable: true)]
    #[Assert\Length(max: 180)]
    private ?string $position = null;

    #[ORM\Column(length: 180, nullable: true)]
    #[Assert\Length(max: 180), Assert\Email]
    private ?string $email = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Assert\Length(max: 50)]
    private ?string $phone = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Assert\Length(max: 50)]
    private ?string $mobile = null;

    #[ORM\Column]
    private bool $primaryContact = false;

    #[ORM\Column]
    #[Assert\PositiveOrZero]
    private int $sortOrder = 0;

    #[ORM\Column]
    private bool $active = true;

    #[ORM\ManyToOne(targetEntity: CompanyImage::class)]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?CompanyImage $image = null;

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
            $previous?->removeContactPerson($this);
            $company?->addContactPerson($this);
        }

        return $this;
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): self
    {
        $this->firstName = $firstName;

        return $this;
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): self
    {
        $this->lastName = $lastName;

        return $this;
    }

    public function getPosition(): ?string
    {
        return $this->position;
    }

    public function setPosition(?string $position): self
    {
        $this->position = $position;

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

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): self
    {
        $this->phone = $phone;

        return $this;
    }

    public function getMobile(): ?string
    {
        return $this->mobile;
    }

    public function setMobile(?string $mobile): self
    {
        $this->mobile = $mobile;

        return $this;
    }

    public function isPrimaryContact(): bool
    {
        return $this->primaryContact;
    }

    public function setPrimaryContact(bool $primaryContact): self
    {
        $this->primaryContact = $primaryContact;

        return $this;
    }

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    public function setSortOrder(int $sortOrder): self
    {
        $this->sortOrder = $sortOrder;

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

    public function getImage(): ?CompanyImage
    {
        return $this->image;
    }

    public function setImage(?CompanyImage $image): self
    {
        $this->image = $image;

        return $this;
    }

    #[Assert\Callback]
    public function validateImage(ExecutionContextInterface $context): void
    {
        if ($this->image !== null && ($this->image->getCompany() !== $this->company && ($this->company?->getId() === null || $this->image->getCompany()?->getId() !== $this->company->getId()))) {
            $context->buildViolation('Das Bild muss zu diesem Unternehmen gehören.')->atPath('image')->addViolation();
        }
    }
}
