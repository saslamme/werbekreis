<?php

declare(strict_types=1);
namespace App\Moderation;
use App\Enum\ModerationStatus;
interface ModeratedContent
{
    public function getId(): ?int;
    public function getModerationStatus(): ModerationStatus;
    public function isModerationApproved(): bool;
}
