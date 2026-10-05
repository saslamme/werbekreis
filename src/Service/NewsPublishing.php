<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\NewsArticle;
use App\Enum\NewsStatus;
use Symfony\Component\Clock\ClockInterface;

final readonly class NewsPublishing
{
    public function __construct(private ClockInterface $clock) {}

    public function now(): \DateTimeImmutable { return $this->clock->now(); }

    public function prepareForSave(NewsArticle $article): void
    {
        if ($article->getStatus() === NewsStatus::Published && $article->getPublishedAt() === null) {
            $article->setPublishedAt($this->clock->now());
        }
    }

    /** New/changed schedules must be in the future; an elapsed stored schedule remains editable. */
    public function invalidScheduleChange(NewsArticle $article, NewsStatus $originalStatus, ?\DateTimeImmutable $originalDate): bool
    {
        return $article->getStatus() === NewsStatus::Scheduled && $article->getPublishedAt() !== null
            && ($article->getId() === null || $originalStatus !== NewsStatus::Scheduled || $originalDate != $article->getPublishedAt())
            && $article->getPublishedAt() <= $this->clock->now();
    }
}
