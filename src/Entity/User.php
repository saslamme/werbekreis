<?php
declare(strict_types=1);
namespace App\Entity;
use App\Repository\UserRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Validator\Constraints as Assert;
#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'portal_user')]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['email'], message: 'Diese E-Mail-Adresse wird bereits verwendet.')]
final class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;
    #[ORM\Column(length: 180, unique: true)]
    #[Assert\NotBlank, Assert\Email, Assert\Length(max: 180)]
    private string $email = '';
    #[ORM\Column]
    private string $password = '';
    /** @var list<string> */
    #[ORM\Column(type: 'json')]
    #[Assert\Count(min: 1, minMessage: 'Bitte mindestens eine Rolle auswählen.')]
    #[Assert\Choice(choices: ['ROLE_ADMIN', 'ROLE_EDITOR', 'ROLE_MEMBER', 'ROLE_VOUCHER_REDEEMER'], multiple: true)]
    private array $roles = [];
    #[ORM\Column(length: 100)]
    #[Assert\NotBlank, Assert\Length(max: 100)]
    private string $firstName = '';
    #[ORM\Column(length: 100)]
    #[Assert\NotBlank, Assert\Length(max: 100)]
    private string $lastName = '';
    #[ORM\Column]
    private bool $active = true;
    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;
    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Company $company = null;
    /** Member ownership is deliberately separate from the single voucher redemption company. */
    #[ORM\ManyToMany(targetEntity: Company::class)]
    #[ORM\JoinTable(name: 'user_company')]
    private \Doctrine\Common\Collections\Collection $companies;
    public function getCompanies(): \Doctrine\Common\Collections\Collection { return $this->companies; }
    public function addCompany(Company $company): self { if (!$this->companies->contains($company)) { $this->companies->add($company); } return $this; }
    public function removeCompany(Company $company): self { $this->companies->removeElement($company); return $this; }
    public function canManageCompany(Company $company): bool
    {
        return $this->active && $this->companies->exists(static fn ($key, Company $owned): bool => $owned === $company || ($company->getId() !== null && $owned->getId() === $company->getId()));
    }
    public function getCompany(): ?Company { return $this->company; }
    public function setCompany(?Company $company): self { $this->company = $company; return $this; }
    public function __construct() { $this->companies = new \Doctrine\Common\Collections\ArrayCollection(); $this->createdAt = $this->updatedAt = \Symfony\Component\Clock\Clock::get()->now(); }
    public function getId(): ?int { return $this->id; }
    public function getEmail(): string { return $this->email; }
    public function setEmail(string $email): self { $this->email = strtolower(trim($email)); return $this; }
    public function getUserIdentifier(): string { return $this->email; }
    public function getPassword(): string { return $this->password; }
    public function setPassword(string $password): self { $this->password = $password; return $this; }
    public function getRoles(): array { return $this->roles; }
    public function setRoles(array $roles): self { $this->roles = array_values(array_unique($roles)); return $this; }
    public function getFirstName(): string { return $this->firstName; }
    public function setFirstName(string $name): self { $this->firstName = trim($name); return $this; }
    public function getLastName(): string { return $this->lastName; }
    public function setLastName(string $name): self { $this->lastName = trim($name); return $this; }
    public function isActive(): bool { return $this->active; }
    public function setActive(bool $active): self { $this->active = $active; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }
    #[ORM\PreUpdate]
    public function touch(): void { $this->updatedAt = \Symfony\Component\Clock\Clock::get()->now(); }
    public function eraseCredentials(): void {}
}
