<?php

declare(strict_types=1);
namespace App\Enum;

enum ModerationStatus: string
{
    case Draft = 'draft';
    case PendingReview = 'pending_review';
    case ChangesRequested = 'changes_requested';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) { self::Draft => 'Entwurf', self::PendingReview => 'In Prüfung', self::ChangesRequested => 'Änderungen erforderlich', self::Approved => 'Freigegeben', self::Rejected => 'Abgelehnt' };
    }
    public function isEditable(): bool { return in_array($this, [self::Draft, self::ChangesRequested, self::Rejected], true); }
    public function canTransitionTo(self $next): bool
    {
        return $next === self::PendingReview ? $this->isEditable() : ($this === self::PendingReview && in_array($next, [self::Approved, self::ChangesRequested, self::Rejected], true));
    }
}
