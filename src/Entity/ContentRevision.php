<?php

declare(strict_types=1);
namespace App\Entity;
use App\Enum\{ContentType, ModerationStatus};
use App\Entity\Traits\{TimestampedTrait, ModerationTrait};
use App\Repository\ContentRevisionRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ContentRevisionRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Index(name: 'revision_queue', columns: ['moderationStatus', 'submittedAt'])]
final class ContentRevision
{
    use TimestampedTrait;
    use ModerationTrait;
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;
    #[ORM\Column(length: 16, enumType: ContentType::class)]
    private ContentType $type;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Company $ownerCompany;
    #[ORM\OneToOne, ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?Company $company = null;
    #[ORM\OneToOne, ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?Offer $offer = null;
    #[ORM\OneToOne, ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?Event $event = null;
    #[ORM\OneToOne, ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?NewsArticle $newsArticle = null;
    #[ORM\OneToOne, ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?JobPosting $jobPosting = null;
    #[ORM\Column(type: 'json')]
    private array $payload;
    #[ORM\Column(type: 'json')]
    private array $imageNames = [];
    #[ORM\Column(length: 64)]
    private string $baseFingerprint;
    public function __construct(object $target, array $payload, array $imageNames, string $fingerprint, \DateTimeImmutable $now)
    {
        $this->type = ContentType::forEntity($target); $this->{$this->type->association()} = $target;
        $this->ownerCompany = $target instanceof Company ? $target : $target->getCompany();
        $this->payload = $payload; $this->imageNames = $imageNames; $this->baseFingerprint = $fingerprint;
        $this->createdAt = $this->updatedAt = $now; $this->beginMemberDraft();
    }
    public function getId(): ?int { return $this->id; }
    public function getType(): ContentType { return $this->type; }
    public function getOwnerCompany(): Company { return $this->ownerCompany; }
    public function getTarget(): object { return $this->{$this->type->association()}; }
    public function getPayload(): array { return $this->payload; }
    public function getImageNames(): array { return $this->imageNames; }
    public function getBaseFingerprint(): string { return $this->baseFingerprint; }
    public function getVersion(): int { return $this->moderationVersion; }
    public function getTitle(): string { return $this->payload['title'] ?? $this->payload['name'] ?? $this->type->label(); }
    public function saveDraft(array $payload, array $images, \DateTimeImmutable $now): void
    {
        if (!$this->moderationStatus->isEditable() && $this->moderationStatus !== ModerationStatus::Approved) { throw new \DomainException('Ein eingereichter Entwurf kann während der Prüfung nicht bearbeitet werden.'); }
        $this->payload = $payload; $this->imageNames = $images; $this->updatedAt = $now;
    }
    public function submit(User $actor, \DateTimeImmutable $now): void
    {
        $this->transition(ModerationStatus::PendingReview); $this->submittedBy = $actor; $this->submittedAt = $this->updatedAt = $now;
    }
    public function review(ModerationStatus $result, User $actor, \DateTimeImmutable $now, ?string $note): void
    {
        $note = trim($note ?? '');
        if (mb_strlen($note) > 2000 || ($result !== ModerationStatus::Approved && $note === '')) { throw new \DomainException('Bitte einen verständlichen Kommentar mit höchstens 2000 Zeichen angeben.'); }
        $this->transition($result); $this->reviewedBy = $actor; $this->reviewedAt = $this->updatedAt = $now; $this->reviewNote = $note === '' ? null : $note;
    }
    private function transition(ModerationStatus $next): void
    {
        if (!$this->moderationStatus->canTransitionTo($next)) { throw new \DomainException('Dieser Statusübergang ist nicht möglich. Bitte die Seite neu laden.'); }
        $this->moderationStatus = $next;
    }
}
