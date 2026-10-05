<?php

declare(strict_types=1);
namespace App\Service;

use App\Entity\EventOccurrence;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class EventStructuredData
{
    public function __construct(private UrlGeneratorInterface $urls, #[Autowire('%portal.timezone%')] private string $timezone) {}
    public function forOccurrence(EventOccurrence $occurrence): array
    {
        $event = $occurrence->getEvent(); $zone = new \DateTimeZone($this->timezone);
        $dateFormat = $event->isAllDay() ? 'Y-m-d' : \DateTimeInterface::ATOM;
        $data = ['@context' => 'https://schema.org', '@type' => 'Event', 'name' => $event->getTitle(),
            'url' => $this->urls->generate('event_occurrence', ['slug' => $event->getSlug(), 'id' => $occurrence->getId()], UrlGeneratorInterface::ABSOLUTE_URL),
            'startDate' => $occurrence->getStartsAt()->setTimezone($zone)->format($dateFormat), 'endDate' => $occurrence->getEndsAt()->setTimezone($zone)->format($dateFormat),
            'eventStatus' => 'https://schema.org/'.($event->isCancelled() ? 'EventCancelled' : 'EventScheduled')];
        if ($event->getShortDescription()) { $data['description'] = $event->getShortDescription(); }
        if ($event->getImagePath()) { $data['image'] = $this->urls->generate('event_image', ['slug' => $event->getSlug(), 'fileName' => $event->getImagePath()], UrlGeneratorInterface::ABSOLUTE_URL); }
        if ($event->getOrganizerLabel()) {
            $data['organizer'] = ['@type' => 'Organization', 'name' => $event->getOrganizerLabel()];
            if ($event->getOrganizerWebsite()) { $data['organizer']['url'] = $event->getOrganizerWebsite(); }
        }
        if ($event->getLocationName() || $event->getStreet() || $event->getCity()) {
            $location = ['@type' => 'Place'];
            if ($event->getLocationName()) { $location['name'] = $event->getLocationName(); }
            $address = array_filter(['@type' => 'PostalAddress', 'streetAddress' => trim(($event->getStreet() ?? '').' '.($event->getHouseNumber() ?? '')), 'postalCode' => $event->getPostalCode(), 'addressLocality' => $event->getCity()]);
            if (count($address) > 1) { $location['address'] = $address; }
            if ($event->hasCoordinates()) { $location['geo'] = ['@type' => 'GeoCoordinates', 'latitude' => $event->getLatitude(), 'longitude' => $event->getLongitude()]; }
            $data['location'] = $location;
        }
        if ($event->isFreeAdmission()) { $data['isAccessibleForFree'] = true; }
        return $data;
    }
}
