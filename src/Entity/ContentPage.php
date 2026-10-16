<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\TimestampedTrait;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

use App\Repository\ContentPageRepository;
use Doctrine\Common\Collections\{ArrayCollection, Collection, Criteria};
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity(repositoryClass: ContentPageRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['code'])]
final class ContentPage
{
    public const SYSTEM_PAGES = [
        'home' => ['label' => 'Startseite', 'url' => '/', 'route' => 'app_home'],
        'about' => ['label' => 'Über uns', 'url' => '/werbekreis/ueber-uns', 'route' => 'about'],
        'board' => ['label' => 'Vorstand', 'url' => '/werbekreis/vorstand', 'route' => 'board'],
        'membership' => ['label' => 'Mitglied werden', 'url' => '/mitglied-werden', 'route' => 'membership'],
        'contact' => ['label' => 'Kontakt', 'url' => '/kontakt', 'route' => 'contact'],
        'imprint' => ['label' => 'Impressum', 'url' => '/impressum', 'route' => 'imprint'],
        'privacy' => ['label' => 'Datenschutz', 'url' => '/datenschutz', 'route' => 'privacy'],
    ];

    use TimestampedTrait;

    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20, unique: true)]
    #[Assert\Choice(choices: ['home', 'about', 'board', 'membership', 'contact', 'imprint', 'privacy'])]
    private string $code;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank, Assert\Length(max: 180)]
    private string $title = '';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $lead = null;

    #[ORM\Column(length: 180, nullable: true)]
    #[Assert\Length(max: 180)]
    private ?string $metaTitle = null;

    #[ORM\Column(length: 300, nullable: true)]
    #[Assert\Length(max: 300)]
    private ?string $metaDescription = null;

    #[ORM\Column(options: ['default' => true])]
    private bool $showDefaultHeader = true;

    /** @var Collection<int, ContentElement> */
    #[ORM\OneToMany(targetEntity: ContentElement::class, mappedBy: 'page', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC', 'id' => 'ASC'])]
    #[Assert\Valid]
    private Collection $elements;

    public function getRoute(): string { return self::SYSTEM_PAGES[$this->code]['route']; }
    public function isShowDefaultHeader(): bool { return $this->showDefaultHeader; }
    public function setShowDefaultHeader(bool $value): self { $this->showDefaultHeader = $value; return $this; }
    public function getElements(): Collection { return $this->elements->matching(Criteria::create()->orderBy(['position' => 'ASC', 'id' => 'ASC'])); }
    public function addElement(ContentElement $element): self {
        if (!$this->elements->contains($element)) { $this->elements->add($element); $element->setPage($this); }
        return $this;
    }
    public function removeElement(ContentElement $element): self {
        if ($this->elements->removeElement($element) && $element->getPage() === $this) { $element->setPage(null); }
        return $this;
    }

    /** @var Collection<int, ContentPageSection> */
    #[ORM\OneToMany(targetEntity: ContentPageSection::class, mappedBy: 'page', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC', 'id' => 'ASC'])]
    #[Assert\Valid]
    private Collection $sections;

    public function __construct(string $code)
    {
        if (!isset(self::SYSTEM_PAGES[$code])) {
            throw new \InvalidArgumentException('Unknown system page code.');
        }
        $this->code = $code;
        $this->sections = new ArrayCollection();
        $this->elements = new ArrayCollection();
        $this->showDefaultHeader = $code !== 'home';
        $this->initializeTimestamps();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): string { return $this->code; }
    public function getLabel(): string { return self::SYSTEM_PAGES[$this->code]['label']; }
    public function getPublicUrl(): string { return self::SYSTEM_PAGES[$this->code]['url']; }
    public function isLegal(): bool { return in_array($this->code, ['imprint', 'privacy'], true); }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = trim($title);

        return $this;
    }

    public function getLead(): ?string
    {
        return $this->lead;
    }

    public function setLead(?string $lead): self
    {
        $this->lead = $lead;

        return $this;
    }

    public function getMetaTitle(): ?string
    {
        return $this->metaTitle;
    }

    public function setMetaTitle(?string $metaTitle): self
    {
        $this->metaTitle = $metaTitle;

        return $this;
    }

    public function getMetaDescription(): ?string
    {
        return $this->metaDescription;
    }

    public function setMetaDescription(?string $metaDescription): self
    {
        $this->metaDescription = $metaDescription;

        return $this;
    }

    /** @return Collection<int, ContentPageSection> */
    public function getSections(): Collection
    {
        return $this->sections->matching(Criteria::create()->orderBy(['position' => 'ASC', 'id' => 'ASC']));
    }

    public function addSection(ContentPageSection $section): self
    {
        if (!$this->sections->contains($section)) {
            $this->sections->add($section);
            $section->setPage($this);
        }

        return $this;
    }

    public function removeSection(ContentPageSection $section): self
    {
        if ($this->sections->removeElement($section) && $section->getPage() === $this) {
            $section->setPage(null);
        }

        return $this;
    }
}
