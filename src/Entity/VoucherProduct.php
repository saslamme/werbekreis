<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\TimestampedTrait;
use App\Repository\VoucherProductRepository;
use App\Service\{DecimalAmount, VoucherException};
use Doctrine\Common\Collections\{ArrayCollection, Collection};
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: VoucherProductRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['slug'], message: 'Dieser Slug ist bereits vergeben.')]
final class VoucherProduct
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

    #[ORM\Column(type: 'text')]
    #[Assert\NotBlank]
    private string $description = '';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $terms = null;

    #[ORM\Column()]
    private bool $active = true;

    #[ORM\Column()]
    private bool $featured = false;

    #[ORM\Column()]
    #[Assert\PositiveOrZero]
    private int $position = 0;

    #[ORM\Column(nullable: true)]
    #[Assert\Range(min: 1, max: 1200)]
    private ?int $validityMonths = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    #[Assert\Regex(DecimalAmount::PATTERN)]
    private ?string $minimumAmount = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    #[Assert\Regex(DecimalAmount::PATTERN)]
    private ?string $maximumAmount = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    #[Assert\Regex(DecimalAmount::PATTERN)]
    private ?string $fixedAmount = null;

    /** @var Collection<int, Company> */
    #[ORM\ManyToMany(targetEntity: Company::class, inversedBy: 'acceptedVoucherProducts')]
    private Collection $acceptingCompanies;

    /** @var Collection<int, Company> */
    #[ORM\ManyToMany(targetEntity: Company::class)]
    #[ORM\JoinTable(name: 'voucher_product_selling_company')]
    private Collection $sellingCompanies;

    public function __construct()
    {
        $this->createdAt = $this->updatedAt = Clock::get()->now();
        $this->acceptingCompanies = new ArrayCollection(); $this->sellingCompanies = new ArrayCollection();
    }
    #[ORM\PreUpdate]
    public function touch(): void { $this->updatedAt = Clock::get()->now(); }
    public function getId(): ?int { return $this->id; }
    public function getName(): string { return $this->name; }
    public function setName(string $value): self { $this->name = trim($value); return $this; }
    public function getSlug(): string { return $this->slug; }
    public function setSlug(string $value): self { $this->slug = trim($value); return $this; }
    public function getDescription(): string { return $this->description; }
    public function setDescription(string $value): self { $this->description = $value; return $this; }
    public function getTerms(): ?string { return $this->terms; }
    public function setTerms(?string $value): self { $this->terms = $value !== null && trim($value) !== "" ? trim($value) : null; return $this; }
    public function isActive(): bool { return $this->active; }
    public function setActive(bool $value): self { $this->active = $value; return $this; }
    public function isFeatured(): bool { return $this->featured; }
    public function setFeatured(bool $value): self { $this->featured = $value; return $this; }
    public function getPosition(): int { return $this->position; }
    public function setPosition(int $value): self { $this->position = $value; return $this; }
    public function getValidityMonths(): ?int { return $this->validityMonths; }
    public function setValidityMonths(?int $value): self { $this->validityMonths = $value; return $this; }
    public function getMinimumAmount(): ?string { return $this->minimumAmount; }
    public function setMinimumAmount(?string $value): self { $this->minimumAmount = DecimalAmount::normalize($value); return $this; }
    public function getMaximumAmount(): ?string { return $this->maximumAmount; }
    public function setMaximumAmount(?string $value): self { $this->maximumAmount = DecimalAmount::normalize($value); return $this; }
    public function getFixedAmount(): ?string { return $this->fixedAmount; }
    public function setFixedAmount(?string $value): self { $this->fixedAmount = DecimalAmount::normalize($value); return $this; }

    /** @return Collection<int, Company> */
    public function getAcceptingCompanies(): Collection { return $this->acceptingCompanies; }
    public function addAcceptingCompany(Company $company): self
    {
        if (!$this->acceptingCompanies->contains($company)) { $this->acceptingCompanies->add($company); $company->addAcceptedVoucherProduct($this); }
        return $this;
    }
    public function removeAcceptingCompany(Company $company): self
    {
        if ($this->acceptingCompanies->removeElement($company)) { $company->removeAcceptedVoucherProduct($this); }
        return $this;
    }
    /** @return Collection<int, Company> */
    public function getSellingCompanies(): Collection { return $this->sellingCompanies; }
    public function addSellingCompany(Company $company): self { if (!$this->sellingCompanies->contains($company)) { $this->sellingCompanies->add($company); } return $this; }
    public function removeSellingCompany(Company $company): self { $this->sellingCompanies->removeElement($company); return $this; }
    public function amountForIssue(?string $value): string
    {
        if (!$this->active) { throw new VoucherException('Dieses Gutscheinprodukt ist nicht aktiv.'); }
        $amount = $this->fixedAmount ?? DecimalAmount::normalize($value);
        if ($this->fixedAmount !== null && $value !== null && trim($value) !== '' && DecimalAmount::toMinor($value) !== DecimalAmount::toMinor($this->fixedAmount)) { throw new VoucherException('Der feste Gutscheinwert darf nicht verändert werden.'); }
        if ($amount === null || DecimalAmount::toMinor($amount) <= 0) { throw new VoucherException('Bitte einen Gutscheinwert größer als null eingeben.'); }
        $minor = DecimalAmount::toMinor($amount);
        if (($this->minimumAmount !== null && $minor < DecimalAmount::toMinor($this->minimumAmount)) || ($this->maximumAmount !== null && $minor > DecimalAmount::toMinor($this->maximumAmount))) { throw new VoucherException('Der Gutscheinwert liegt außerhalb des erlaubten Bereichs.'); }
        return DecimalAmount::fromMinor($minor);
    }
    #[Assert\Callback]
    public function validate(ExecutionContextInterface $context): void
    {
        if ($this->fixedAmount !== null && ($this->minimumAmount !== null || $this->maximumAmount !== null)) {
            $context->buildViolation('Fester Wert und variabler Wertbereich dürfen nicht kombiniert werden.')->atPath('fixedAmount')->addViolation();
        }
        if ($this->fixedAmount === null && ($this->minimumAmount === null || $this->maximumAmount === null)) {
            $context->buildViolation('Bitte einen festen Wert oder beide Grenzen des variablen Wertbereichs angeben.')->atPath('minimumAmount')->addViolation();
        }
        foreach (['minimumAmount', 'maximumAmount', 'fixedAmount'] as $field) {
            $value = $this->$field;
            if ($value !== null && preg_match(DecimalAmount::PATTERN, $value) && DecimalAmount::toMinor($value) === 0) { $context->buildViolation('Der Betrag muss größer als null sein.')->atPath($field)->addViolation(); }
        }
        if ($this->minimumAmount !== null && $this->maximumAmount !== null && preg_match(DecimalAmount::PATTERN, $this->minimumAmount) && preg_match(DecimalAmount::PATTERN, $this->maximumAmount) && DecimalAmount::toMinor($this->minimumAmount) > DecimalAmount::toMinor($this->maximumAmount)) {
            $context->buildViolation('Der Höchstwert darf nicht unter dem Mindestwert liegen.')->atPath('maximumAmount')->addViolation();
        }
    }
}
