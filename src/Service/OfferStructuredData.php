<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Offer;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class OfferStructuredData
{
    public function __construct(private UrlGeneratorInterface $urls)
    {
    }

    /** Only persisted facts: no inferred availability, currency or discount prices. */
    public function forOffer(Offer $offer): array
    {
        $data = [
            '@context' => 'https://schema.org', '@type' => 'Offer', 'name' => $offer->getTitle(),
            'url' => $this->urls->generate('offer_show', ['slug' => $offer->getSlug()], UrlGeneratorInterface::ABSOLUTE_URL),
            'description' => $offer->getShortDescription(),
            'seller' => ['@type' => 'LocalBusiness', 'name' => $offer->getCompany()->getName(), 'url' => $this->urls->generate('company_show', ['slug' => $offer->getCompany()->getSlug()], UrlGeneratorInterface::ABSOLUTE_URL)],
        ];
        $price = $offer->getOfferPrice() ?? $offer->getRegularPrice();
        if ($price !== null) {
            $data['price'] = $price;
            $data['priceCurrency'] = 'EUR';
        }
        if ($offer->getStartsAt() !== null) {
            $data['validFrom'] = $offer->getStartsAt()->format(DATE_ATOM);
        }
        if ($offer->getEndsAt() !== null) {
            $data['validThrough'] = $offer->getEndsAt()->format(DATE_ATOM);
        }
        if ($offer->getImagePath() !== null) {
            $data['image'] = $this->urls->generate('offer_image', ['slug' => $offer->getSlug(), 'fileName' => $offer->getImagePath()], UrlGeneratorInterface::ABSOLUTE_URL);
        }

        return array_filter($data, static fn ($value): bool => $value !== null && $value !== '');
    }
}
