<?php

declare(strict_types=1);
namespace App\Moderation;
use App\Enum\{ContentType, ModerationStatus};
/** Immutable post-commit notification foundation; intentionally no mail sender or payload/secrets. */
final readonly class ContentModerationEvent
{
    public function __construct(public int $revisionId, public ContentType $type, public ModerationStatus $status, public ?int $actorId, public \DateTimeImmutable $occurredAt) {}
}
