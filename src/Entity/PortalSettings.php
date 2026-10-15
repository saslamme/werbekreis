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
