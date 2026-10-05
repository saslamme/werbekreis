<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Category;
use App\Entity\Company;
use App\Entity\Offer;
use App\Entity\Event;
use App\Entity\EventCategory;
use App\Repository\CategoryRepository;
use App\Repository\CompanyRepository;
use App\Repository\OfferRepository;
use App\Repository\EventRepository;
use App\Repository\EventCategoryRepository;
use Symfony\Component\String\Slugger\SluggerInterface;

final class DirectorySlugger
{
    public function __construct(private readonly SluggerInterface $slugger, private readonly CompanyRepository $companies, private readonly CategoryRepository $categories, private readonly OfferRepository $offers, private readonly EventRepository $events, private readonly EventCategoryRepository $eventCategories)
    {
    }

    public function assign(Category|Company|Offer|Event|EventCategory $entity): void
    {
        if ($entity->getSlug() !== '') {
            return;
        }
        $name = strtr(($entity instanceof Offer || $entity instanceof Event ? $entity->getTitle() : $entity->getName()), ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'Ä' => 'Ae', 'Ö' => 'Oe', 'Ü' => 'Ue', 'ß' => 'ss']);
        $base = rtrim(substr($this->slugger->slug($name)->lower()->toString(), 0, 170), '-') ?: 'eintrag';
        $repository = match (true) {
            $entity instanceof Company => $this->companies,
            $entity instanceof Offer => $this->offers,
            $entity instanceof Event => $this->events,
            $entity instanceof EventCategory => $this->eventCategories,
            default => $this->categories,
        };
        $used = $repository->existingSlugs($base, $entity->getId());
        $slug = $base;
        for ($suffix = 2; in_array($slug, $used, true); ++$suffix) {
            $slug = $base.'-'.$suffix;
        }
        $entity->setSlug($slug);
    }
}
