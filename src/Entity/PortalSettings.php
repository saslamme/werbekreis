<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\TimestampedTrait;
use App\Repository\PortalSettingsRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: PortalSettingsRepository::class)]
#[ORM\HasLifecycleCallbacks]
final class PortalSettings
{
    use TimestampedTrait;

    public const ID = 1;

    #[ORM\Id, ORM\Column]
    private int $id = self::ID;

    #[ORM\Column(length: 2048, nullable: true)]
    #[Assert\Length(max: 2048)]
    #[Assert\Url(protocols: ['http', 'https'], requireTld: true)]
    private ?string $facebookUrl = null;

    #[ORM\Column(length: 2048, nullable: true)]
    #[Assert\Length(max: 2048)]
    #[Assert\Url(protocols: ['http', 'https'], requireTld: true)]
    private ?string $instagramUrl = null;

    public const LOGO_NAME_PATTERN = '/^[a-f0-9]{32}\.(?:svg|png|jpg)$/D';

    #[ORM\Column(length: 36, nullable: true)]
    #[Assert\Regex(pattern: self::LOGO_NAME_PATTERN)]
    private ?string $logoFileName = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $logoOriginalName = null;

    #[ORM\Column(nullable: true)]
    #[Assert\Range(min: 40, max: 1000)]
    private ?int $logoMaxWidth = null;

    #[ORM\Column(nullable: true)]
    #[Assert\Range(min: 20, max: 400)]
    private ?int $logoMaxHeight = null;

    public function getLogoMaxWidth(): ?int { return $this->logoMaxWidth; }
    public function getLogoMaxHeight(): ?int { return $this->logoMaxHeight; }
    public function setLogoMaxWidth(?int $width): self { $this->logoMaxWidth = $width; return $this; }
    public function setLogoMaxHeight(?int $height): self { $this->logoMaxHeight = $height; return $this; }

    public function getLogoFileName(): ?string { return $this->logoFileName; }
    public function getLogoOriginalName(): ?string { return $this->logoOriginalName; }

    public function setLogoFileName(?string $name): self
    {
        if ($name !== null && !preg_match(self::LOGO_NAME_PATTERN, $name)) {
            throw new \InvalidArgumentException('Invalid stored portal logo name.');
        }
        $this->logoFileName = $name;
        return $this;
    }

    public function setLogoOriginalName(?string $name): self
    {
        // Display metadata only; never used to resolve a filesystem path.
        if ($name !== null && (mb_strlen($name) > 255 || strpbrk($name, "/\\\0") !== false)) {
            throw new \InvalidArgumentException('Invalid original portal logo name.');
        }
        $this->logoOriginalName = $name;
        return $this;
    }

    public function __construct()
    {
        $this->initializeTimestamps();
    }

    public function getId(): int { return $this->id; }
    public function getFacebookUrl(): ?string { return $this->facebookUrl; }
    public function getInstagramUrl(): ?string { return $this->instagramUrl; }

    public function setFacebookUrl(?string $url): self
    {
        $this->facebookUrl = self::normalizeUrl($url);
        return $this;
    }

    public function setInstagramUrl(?string $url): self
    {
        $this->instagramUrl = self::normalizeUrl($url);
        return $this;
    }

    private static function normalizeUrl(?string $url): ?string
    {
        return $url === null || trim($url) === '' ? null : trim($url);
    }
}
