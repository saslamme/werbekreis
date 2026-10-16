<?php
declare(strict_types=1);
namespace App\Entity;

use App\Entity\Traits\TimestampedTrait;
use App\Content\ElementTypes;
use Doctrine\Common\Collections\{ArrayCollection, Collection};
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity]
#[ORM\HasLifecycleCallbacks]
#[ORM\Index(columns: ['page_id', 'position', 'id'], name: 'content_element_order')]
#[ORM\UniqueConstraint(name: 'content_element_anchor', columns: ['page_id', 'anchor'])]
#[UniqueEntity(fields: ['page', 'anchor'], ignoreNull: true, message: 'Dieser Anker wird auf dieser Seite bereits verwendet.')]
final class ContentElement
{
    use TimestampedTrait;
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;
    #[ORM\ManyToOne(inversedBy: 'elements')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    private ?ContentPage $page = null;
    #[ORM\Column(length: 30)]
    #[Assert\Choice(callback: [ElementTypes::class, 'types'])]
    private string $type;
    #[ORM\Column]
    #[Assert\PositiveOrZero]
    private int $position = 0;
    #[ORM\Column]
    private bool $active = true;
    #[ORM\Column(length: 100, nullable: true)]
    #[Assert\Length(max: 100)]
    #[Assert\Regex('/^[a-z][a-z0-9-]*$/D')]
    private ?string $anchor = null;
    #[ORM\Column(type: 'json')]
    private array $data = [];
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Event $linkedEvent = null;
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Company $linkedCompany = null;
    #[ORM\OneToMany(targetEntity: ContentMedia::class, mappedBy: 'element', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC', 'id' => 'ASC'])]
    #[Assert\Valid]
    private Collection $media;
    public function __construct(string $type = 'text') {
        if (!in_array($type, ElementTypes::types(), true)) { throw new \InvalidArgumentException('Unknown element type.'); }
        $this->type = $type; $this->data = ElementTypes::defaults($type); $this->media = new ArrayCollection(); $this->initializeTimestamps();
    }
    public function getId(): ?int { return $this->id; }
    public function getType(): string { return $this->type; }
    public function getLabel(): string { return ElementTypes::ALL[$this->type][0]; }
    public function getIcon(): string { return ElementTypes::ALL[$this->type][2]; }
    public function getPage(): ?ContentPage { return $this->page; }
    public function setPage(?ContentPage $page): self {
        if ($this->page === $page) { return $this; }
        $old = $this->page; $this->page = $page; $old?->removeElement($this); $page?->addElement($this); return $this;
    }
    public function getPosition(): int { return $this->position; }
    public function setPosition(int $value): self { $this->position = $value; return $this; }
    public function isActive(): bool { return $this->active; }
    public function setActive(bool $value): self { $this->active = $value; return $this; }
    public function getAnchor(): ?string { return $this->anchor; }
    public function setAnchor(?string $value): self { $this->anchor = $value === '' ? null : $value; return $this; }
    public function getData(): array { return $this->data; }
    public function setData(array $value): self { $this->data = $value; return $this; }
    public function getLinkedEvent(): ?Event { return $this->linkedEvent; }
    public function setLinkedEvent(?Event $value): self { $this->linkedEvent = $value; return $this; }
    public function getLinkedCompany(): ?Company { return $this->linkedCompany; }
    public function setLinkedCompany(?Company $value): self { $this->linkedCompany = $value; return $this; }
    public function getMedia(): Collection { return $this->media; }
    public function addMedia(ContentMedia $value): self { if (!$this->media->contains($value)) { $this->media->add($value); $value->setElement($this); } return $this; }
    public function removeMedia(ContentMedia $value): self { if ($this->media->removeElement($value)) { $value->setElement(null); } return $this; }
    // Symfony PropertyAccess singularizes "media" as "medium" for collection forms.
    public function addMedium(ContentMedia $value): self { return $this->addMedia($value); }
    public function removeMedium(ContentMedia $value): self { return $this->removeMedia($value); }
    #[Assert\Callback]
    public function validateData(ExecutionContextInterface $context): void {
        foreach (ElementTypes::errors($this->type, $this->data) as $field => $message) { $context->buildViolation($message)->atPath('data.'.$field)->addViolation(); }
        if (in_array($this->type, ['image', 'hero', 'cta', 'contact_person', 'video'], true) && $this->media->count() > 1) { $context->buildViolation('Nur ein Bild erlaubt.')->atPath('media')->addViolation(); }
        foreach ($this->page?->getElements() ?? [] as $other) {
            if ($other !== $this && $this->anchor !== null && $other->getAnchor() === $this->anchor) { $context->buildViolation('Dieser Anker wird bereits verwendet.')->atPath('anchor')->addViolation(); break; }
        }
    }
}
