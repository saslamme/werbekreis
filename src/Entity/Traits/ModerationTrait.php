<?php

declare(strict_types=1);
namespace App\Entity\Traits;
use App\Entity\User;
use App\Enum\ModerationStatus;
use Doctrine\ORM\Mapping as ORM;
trait ModerationTrait
{
    #[ORM\Column(type: 'integer'), ORM\Version]
    private int $moderationVersion = 1;
    public function getModerationVersion(): int { return $this->moderationVersion; }
    /** Transient form/validation copy: never publish inverse association changes into managed entities. */
    private bool $revisionShadow = false;
    public function markRevisionShadow(): void { $this->revisionShadow = true; }
    public function isRevisionShadow(): bool { return $this->revisionShadow; }
    // Trusted admin content keeps previous behavior; member creation explicitly starts as Draft.
    #[ORM\Column(length: 24, enumType: ModerationStatus::class, options: ['default' => 'approved'])]
    private ModerationStatus $moderationStatus = ModerationStatus::Approved;
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $submittedAt = null;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $submittedBy = null;
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $reviewedAt = null;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $reviewedBy = null;
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $reviewNote = null;
    public function getModerationStatus(): ModerationStatus { return $this->moderationStatus; }
    public function isModerationApproved(): bool { return $this->moderationStatus === ModerationStatus::Approved; }
    public function getSubmittedAt(): ?\DateTimeImmutable { return $this->submittedAt; }
    public function getSubmittedBy(): ?User { return $this->submittedBy; }
    public function getReviewedAt(): ?\DateTimeImmutable { return $this->reviewedAt; }
    public function getReviewedBy(): ?User { return $this->reviewedBy; }
    public function getReviewNote(): ?string { return $this->reviewNote; }
    public function beginMemberDraft(): void { $this->moderationStatus = ModerationStatus::Draft; }
    public function recordApproval(?User $submitter, \DateTimeImmutable $submittedAt, User $reviewer, \DateTimeImmutable $now, ?string $note): void
    {
        $this->moderationStatus = ModerationStatus::Approved; $this->submittedBy = $submitter; $this->submittedAt = $submittedAt;
        $this->reviewedBy = $reviewer; $this->reviewedAt = $now; $this->reviewNote = $note;
    }
}
