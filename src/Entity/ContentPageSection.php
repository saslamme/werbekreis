<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\TimestampedTrait;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\HasLifecycleCallbacks]
#[ORM\Index(columns: ['page_id', 'position', 'id'], name: 'content_section_order')]
final class ContentPageSection
{
    public const STYLES = ['default', 'feature', 'notice'];

    use TimestampedTrait;

    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'sections')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    private ?ContentPage $page = null;

    #[ORM\Column(length: 180, nullable: true)]
    #[Assert\Length(max: 180)]
    private ?string $heading = null;

    #[ORM\Column(type: 'text')]
    #[Assert\NotBlank]
    private string $body = '';

    #[ORM\Column]
    #[Assert\PositiveOrZero]
    private int $position = 0;

    #[ORM\Column(length: 20)]
    #[Assert\Choice(choices: self::STYLES)]
    private string $style = 'default';

    public function __construct() { $this->initializeTimestamps(); }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPage(): ?ContentPage { return $this->page; }
    public function setPage(?ContentPage $page): self
    {
        if ($this->page === $page) { return $this; }
        $previous = $this->page;
        $this->page = $page;
        $previous?->removeSection($this);
        $page?->addSection($this);

        return $this;
    }

    public function getHeading(): ?string
    {
        return $this->heading;
    }

    public function setHeading(?string $heading): self
    {
        $this->heading = $heading;

        return $this;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function setBody(string $body): self
    {
        $this->body = trim($body);

        return $this;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): self
    {
        $this->position = $position;

        return $this;
    }

    public function getStyle(): string
    {
        return $this->style;
    }

    public function setStyle(string $style): self
    {
        $this->style = trim($style);

        return $this;
    }
}
