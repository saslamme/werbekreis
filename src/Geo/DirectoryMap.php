<?php

declare(strict_types=1);

namespace App\Geo;

use App\Entity\Company;
use App\Entity\Event;
use App\Entity\JobPosting;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class DirectoryMap
{
    public function __construct(private UrlGeneratorInterface $urls, #[Autowire('%env(MAP_TILE_URL)%')] private string $tileUrl)
    {
    }

    /** @param iterable<Company|Event|JobPosting> $companies */
    public function data(iterable $companies): array
    {
        $markers = [];
        foreach ($companies as $company) {
            if (!$company->hasCoordinates()) {
                continue;
            }
            $markers[] = [
                'latitude' => $company->getLatitude(), 'longitude' => $company->getLongitude(),
                'slug' => $company->getSlug(), 'name' => $company instanceof Event || $company instanceof JobPosting ? $company->getTitle() : $company->getName(),
                'categories' => $company instanceof JobPosting ? [$company->getEmploymentType()->label()] : array_map(static fn ($category): string => $category->getName(), $company->getPublicCategories()),
                'description' => $company instanceof Event || $company instanceof JobPosting ? mb_substr($company->getShortDescription() ?? '', 0, 140) : $company->getTeaser(140),
                'url' => $this->urls->generate($company instanceof JobPosting ? 'job_show' : ($company instanceof Event ? 'event_show' : 'company_show'), ['slug' => $company->getSlug()]),
            ];
        }

        return ['markers' => $markers, 'tileUrl' => $this->tileUrl];
    }

    /** @param iterable<Company> $companies @return array<string, string> */
    public function distances(iterable $companies, ?GeoPoint $point): array
    {
        $distances = [];
        if ($point !== null) {
            foreach ($companies as $company) {
                if ($company->hasCoordinates()) {
                    $km = $point->distanceTo(new GeoPoint($company->getLatitude(), $company->getLongitude()));
                    $distances[$company->getSlug()] = number_format($km, $km < 10 ? 1 : 0, ',', '.').' km';
                }
            }
        }

        return $distances;
    }
}
