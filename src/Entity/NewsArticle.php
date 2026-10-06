<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\TimestampedTrait;
use App\Enum\NewsStatus;
use Symfony\Component\Clock\Clock;
use App\Repository\NewsArticleRepository;
use Doctrine\Common\Collections\{ArrayCollection, Collection};
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: NewsArticleRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Index(name: 'news_publication', columns: ['status', 'publishedAt'])]
#[UniqueEntity(fields: ['slug'], service: \App\Validator\DraftAwareUniqueEntityValidator::class, message: 'Dieser Slug ist bereits vergeben.')]
final class NewsArticle implements \App\Moderation\ModeratedContent
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

    #[ORM\Column(length: 500)]
    #[Assert\Length(max: 500)]
    private string $teaser = '';

    #[ORM\Column(type: 'text')]
    #[Assert\NotBlank]
    private string $content = '';

    #[ORM\Column(length: 20, enumType: NewsStatus::class)]
    private NewsStatus $status = NewsStatus::Draft;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $publishedAt = null;

    #[ORM\Column]
    private bool $featured = false;

    #[ORM\ManyToOne(inversedBy: 'newsArticles')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Company $company = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Assert\Regex('/^[a-f0-9]{32}\.(?:jpg|png|webp)$/D')]
    private ?string $imagePath = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $imageAltText = null;

    #[ORM\Column(length: 180, nullable: true)]
    #[Assert\Length(max: 180)]
    private ?string $authorName = null;

    #[ORM\Column(length: 2048, nullable: true)]
    #[Assert\Length(max: 2048), Assert\Url(protocols: ['http', 'https'])]
    private ?string $externalUrl = null;

    /** @var Collection<int, NewsCategory> */
    #[ORM\ManyToMany(targetEntity: NewsCategory::class, inversedBy: 'articles')]
    private Collection $categories;

    public function __construct(?\DateTimeImmutable $createdAt = null)
    {
        $this->initializeTimestamps();
        $this->createdAt = $this->updatedAt = $createdAt ?? Clock::get()->now();
        $this->categories = new ArrayCollection();
    }

    #[ORM\PreUpdate]
    public function touch(): void
    {
        $this->updatedAt = Clock::get()->now();
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

    public function getTeaser(): string
    {
        return $this->teaser;
    }

    public function setTeaser(string $teaser): self
    {
        $this->teaser = $teaser;

        return $this;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): self
    {
        $this->content = $content;

        return $this;
    }

    public function getStatus(): NewsStatus
    {
        return $this->status;
    }

    public function setStatus(NewsStatus $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getPublishedAt(): ?\DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function setPublishedAt(?\DateTimeImmutable $publishedAt): self
    {
        $this->publishedAt = $publishedAt;

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
        $old?->removeNewsArticle($this);
        $company?->addNewsArticle($this);

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

    public function getImageAltText(): ?string
    {
        return $this->imageAltText;
    }

    public function setImageAltText(?string $imageAltText): self
    {
        $this->imageAltText = $imageAltText;

        return $this;
    }

    public function getAuthorName(): ?string
    {
        return $this->authorName;
    }

    public function setAuthorName(?string $authorName): self
    {
        $this->authorName = $authorName;

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

    /** @return Collection<int, NewsCategory> */
    public function getCategories(): Collection { return $this->categories; }
    /** @return list<NewsCategory> */
    public function getPublicCategories(): array
    {
        return $this->categories->filter(static fn (NewsCategory $category): bool => $category->isActive())->getValues();
    }
    public function addCategory(NewsCategory $category): self
    {
        if (!$this->categories->contains($category)) { $this->categories->add($category); if (!$this->isRevisionShadow()) { $category->addArticle($this); } }
        return $this;
    }
    public function removeCategory(NewsCategory $category): self
    {
        if ($this->categories->removeElement($category)) { if (!$this->isRevisionShadow()) { $category->removeArticle($this); } }
        return $this;
    }
    public function getFileName(): string { return $this->imagePath ?? ''; }
    public function setFileName(string $name): self { return $this->setImagePath($name === '' ? null : $name); }
    public function getPublicationDate(): \DateTimeImmutable { return $this->publishedAt ?? $this->createdAt; }

    public function isPubliclyVisible(\DateTimeImmutable $now): bool
    {
        return $this->isModerationApproved() && ($this->company === null || $this->company->isModerationApproved()) && in_array($this->status->value, NewsStatus::publishableValues(), true)
            && ($this->status !== NewsStatus::Scheduled || $this->publishedAt !== null)
            && ($this->publishedAt === null || $this->publishedAt <= $now);
    }
    public function statusAt(\DateTimeImmutable $now): string
    {
        return match (true) {
            $this->isPubliclyVisible($now) => 'Veröffentlicht',
            $this->status === NewsStatus::Draft => 'Entwurf',
            default => 'Geplant',
        };
    }
    #[Assert\Callback]
    public function validatePublication(ExecutionContextInterface $context): void
    {
        if ($this->status === NewsStatus::Scheduled && $this->publishedAt === null) {
            $context->buildViolation('Geplante Beiträge benötigen einen Veröffentlichungszeitpunkt.')->atPath('publishedAt')->addViolation();
        }
    }
}
