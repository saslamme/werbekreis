<?php
declare(strict_types=1);
namespace App\Entity;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
#[ORM\Entity]
final class ContentMedia
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;
    #[ORM\ManyToOne(inversedBy: 'media')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?ContentElement $element = null;
    #[ORM\Column(length: 40)]
    #[Assert\Regex('/^[a-f0-9]{32}\.(jpg|png|webp)$/D')]
    private string $fileName = '';
    #[ORM\Column(length: 255)]
    private string $originalName = '';
    #[ORM\Column(length: 30)]
    #[Assert\Choice(['image/jpeg', 'image/png', 'image/webp'])]
    private string $mimeType = '';
    #[ORM\Column(length: 255)]
    #[Assert\Length(max: 255)]
    private string $altText = '';
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $caption = null;
    #[ORM\Column]
    #[Assert\PositiveOrZero]
    private int $position = 0;
    #[ORM\Column]
    private bool $decorative = false;
    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;
    public function __construct() { $this->createdAt = new \DateTimeImmutable(); }
    public function getId(): ?int { return $this->id; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    #[Assert\Callback]
    public function validateAlt(ExecutionContextInterface $context): void {
        if (!$this->decorative && trim($this->altText) === '') { $context->buildViolation('Alt-Text angeben oder als dekorativ markieren.')->atPath('altText')->addViolation(); }
    }
    public function getElement(): ?ContentElement { return $this->element; }
    public function setElement(?ContentElement $value): self { $this->element = $value; return $this; }
    public function getFileName(): string { return $this->fileName; }
    public function setFileName(string $value): self { $this->fileName = $value; return $this; }
    public function getOriginalName(): string { return $this->originalName; }
    public function setOriginalName(string $value): self { $this->originalName = $value; return $this; }
    public function getMimeType(): string { return $this->mimeType; }
    public function setMimeType(string $value): self { $this->mimeType = $value; return $this; }
    public function getAltText(): string { return $this->altText; }
    public function setAltText(string $value): self { $this->altText = $value; return $this; }
    public function getCaption(): ?string { return $this->caption; }
    public function setCaption(?string $value): self { $this->caption = $value; return $this; }
    public function getPosition(): int { return $this->position; }
    public function setPosition(int $value): self { $this->position = $value; return $this; }
    public function isDecorative(): bool { return $this->decorative; }
    public function setDecorative(bool $value): self { $this->decorative = $value; return $this; }
}
