<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Company;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/** Builds schema.org LocalBusiness JSON-LD from stored company data only; empty fields are left out. */
final class CompanyStructuredData
{
    private const DAYS = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'];

    public function __construct(private readonly UrlGeneratorInterface $urls)
    {
    }

    /** @return array<string, mixed> */
    public function forCompany(Company $company): array
    {
        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'LocalBusiness',
            'name' => $company->getName(),
            'url' => $this->urls->generate('company_show', ['slug' => $company->getSlug()], UrlGeneratorInterface::ABSOLUTE_URL),
            'description' => $company->getTeaser(300),
            'telephone' => $company->getPhone(),
            'email' => $company->getEmail(),
        ];
        $street = trim(($company->getStreet() ?? '').' '.($company->getHouseNumber() ?? ''));
        $address = array_filter(['@type' => 'PostalAddress', 'streetAddress' => $street, 'postalCode' => $company->getPostalCode(), 'addressLocality' => $company->getCity()]);
        if (count($address) > 1) {
            $data['address'] = $address + ['addressCountry' => 'DE'];
        }
        $images = array_filter([$company->getCoverImage(), $company->getLogoImage()]);
        if ($images !== []) {
            $data['image'] = array_values(array_map(fn ($image): string => $this->urls->generate('company_image', ['slug' => $company->getSlug(), 'fileName' => $image->getFileName()], UrlGeneratorInterface::ABSOLUTE_URL), $images));
        }
        $data['sameAs'] = array_values(array_filter([$company->getWebsite(), $company->getFacebookUrl(), $company->getInstagramUrl()]));
        $hours = [];
        foreach ($company->getOpeningHours() as $entry) {
            // Closed days carry no time window; schema.org expresses them by omission.
            if (!$entry->isClosed() && $entry->getOpensAt() !== null && $entry->getClosesAt() !== null) {
                $hours[] = ['@type' => 'OpeningHoursSpecification', 'dayOfWeek' => 'https://schema.org/'.self::DAYS[$entry->getDayOfWeek()], 'opens' => $entry->getOpensAt()->format('H:i'), 'closes' => $entry->getClosesAt()->format('H:i')];
            }
        }
        $data['openingHoursSpecification'] = $hours;

        return array_filter($data, static fn (mixed $value): bool => $value !== null && $value !== '' && $value !== []);
    }
}
