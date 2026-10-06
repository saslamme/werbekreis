<?php

declare(strict_types=1);
namespace App\Enum;
use App\Entity\{Company, Offer, Event, NewsArticle, JobPosting};
use App\Form\{CompanyType, OfferType, EventType, NewsArticleType, JobPostingType};
enum ContentType: string
{
    case Company = 'companies'; case Offer = 'offers'; case Event = 'events'; case News = 'news'; case Job = 'jobs';
    public function label(): string { return match ($this) { self::Company => 'Unternehmen', self::Offer => 'Angebote', self::Event => 'Veranstaltungen', self::News => 'News', self::Job => 'Jobs' }; }
    public function entityClass(): string { return match ($this) { self::Company => Company::class, self::Offer => Offer::class, self::Event => Event::class, self::News => NewsArticle::class, self::Job => JobPosting::class }; }
    public function association(): string { return match ($this) { self::Company => 'company', self::Offer => 'offer', self::Event => 'event', self::News => 'newsArticle', self::Job => 'jobPosting' }; }
    public function formClass(): string { return match ($this) { self::Company => \App\Form\MemberCompanyType::class, self::Offer => \App\Form\MemberOfferType::class, self::Event => \App\Form\MemberEventType::class, self::News => \App\Form\MemberNewsType::class, self::Job => \App\Form\MemberJobType::class }; }
    public static function forEntity(object $entity): self { foreach (self::cases() as $type) { if ($entity instanceof ($type->entityClass())) { return $type; } } throw new \InvalidArgumentException('Unsupported moderated content'); }
}
