<?php

declare(strict_types=1);
namespace App\Tests\Functional;

use App\Entity\{Event, EventCategory};
use App\Enum\EventRecurrence;
use App\Repository\{EventRepository, EventCategoryRepository};
use App\Service\{EventSchedule, EventDateRangeResolver};
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;

final class PublicEventTest extends EventDatabaseTestCase
{
    public function testUpcomingOverviewChronologicalWithSafeImagesAndLabels(): void
    {
        $this->client->request('GET', '/veranstaltungen'); self::assertResponseIsSuccessful();
        $titles = $this->eventTitles(); self::assertContains('Beispielabend heute', $titles); self::assertContains('Familientag morgen', $titles);
        self::assertNotContains('Vergangener Beispielabend', $titles); self::assertNotContains('Inaktive Beispielveranstaltung', $titles);
        $dates = $this->client->getCrawler()->filter('.event-card .event-time time:first-child')->each(static fn ($node) => strtotime($node->attr('datetime')));
        $sorted = $dates; sort($sorted); self::assertSame($sorted, $dates);
        self::assertSelectorTextContains('title', 'Veranstaltungen in Haselünne'); self::assertSelectorExists('link[rel="canonical"][href="http://localhost/veranstaltungen"]');
        self::assertSelectorExists('.event-card-fallback'); self::assertSelectorTextContains('body', 'Abgesagt');
        $this->client->request('GET', '/veranstaltungen/beispielabend-heute'); self::assertResponseIsSuccessful();
        $src = $this->client->getCrawler()->filter('img.offer-detail-image')->attr('src'); $this->client->request('GET', $src);
        self::assertResponseIsSuccessful(); self::assertResponseHeaderSame('X-Content-Type-Options', 'nosniff'); self::assertResponseHeaderSame('Cache-Control', 'no-store, private');
    }
    public static function details(): iterable
    {
        yield ['beispielabend-heute', 200]; yield ['familientag-morgen', 200]; yield ['vergangener-beispielabend', 200];
        yield ['inaktive-beispielveranstaltung', 404]; yield ['unbekannt', 404]; yield ['abgesagtes-beispielkonzert', 200];
    }
    #[DataProvider('details')]
    public function testDetailVisibility(string $slug, int $code): void
    {
        $this->client->request('GET', '/veranstaltungen/'.$slug); self::assertResponseStatusCodeSame($code);
    }
    public function testStructuredDataOrganizerCompanyAndOptInMap(): void
    {
        $this->client->request('GET', '/veranstaltungen/beispielabend-heute'); self::assertResponseIsSuccessful();
        $data = json_decode($this->client->getCrawler()->filter('script[type="application/ld+json"]')->text(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('Event', $data['@type']); self::assertSame('https://schema.org/EventScheduled', $data['eventStatus']);
        self::assertSame('Beispielcafé Uferpause', $data['organizer']['name']); self::assertArrayHasKey('geo', $data['location']); self::assertArrayNotHasKey('offers', $data);
        self::assertSelectorExists('[data-company-map] [data-map-load]'); self::assertSelectorExists('a[href="/unternehmen/beispielcafe-uferpause"]');
        self::assertSelectorExists('a[href*="openstreetmap.org/directions"]');
        $this->client->request('GET', '/veranstaltungen/familientag-morgen');
        $data = json_decode($this->client->getCrawler()->filter('script[type="application/ld+json"]')->text(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('2030-05-02', $data['startDate']); self::assertSame('2030-05-02', $data['endDate']); self::assertTrue($data['isAccessibleForFree']);
        self::assertSame('Fiktive Initiative Stadtleben', $data['organizer']['name']); self::assertSelectorNotExists('[data-company-map]'); self::assertSelectorTextContains('body', 'Ganztägig');
        $this->client->request('GET', '/veranstaltungen/abgesagtes-beispielkonzert'); self::assertSelectorTextContains('.alert-warning', 'abgesagt');
        $data = json_decode($this->client->getCrawler()->filter('script[type="application/ld+json"]')->text(), true); self::assertSame('https://schema.org/EventCancelled', $data['eventStatus']);
    }
    public static function filters(): iterable
    {
        yield ['today', 'Beispielabend heute']; yield ['tomorrow', 'Familientag morgen']; yield ['weekend', 'Fiktiver Wochenendmarkt'];
        yield ['week', 'Beispielabend heute']; yield ['month', 'Freier Beispieltalk'];
    }
    #[DataProvider('filters')]
    public function testDateFilters(string $period, string $title): void
    {
        $this->client->request('GET', '/veranstaltungen?zeitraum='.$period); self::assertResponseIsSuccessful(); self::assertContains($title, $this->eventTitles());
        self::assertSelectorExists('select[name="zeitraum"] option[value="'.$period.'"][selected]'); self::assertSelectorExists('meta[name="robots"][content="noindex,follow"]');
        if ($period === 'today') { self::assertNotContains('Familientag morgen', $this->eventTitles()); }
    }
    public function testCombinedCategoryCompanyAndPeriodFilters(): void
    {
        $this->client->request('GET', '/veranstaltungen?kategorie=musik&unternehmen=beispielcafe-uferpause&zeitraum=today');
        self::assertResponseIsSuccessful(); self::assertSame(['Beispielabend heute'], $this->eventTitles());
        self::assertSelectorExists('select[name="kategorie"] option[value="musik"][selected]');
        $this->client->request('GET', '/veranstaltungen?kategorie=fiktive-inaktive-kategorie'); self::assertResponseStatusCodeSame(404);
        $this->client->request('GET', '/veranstaltungen?unternehmen=beispielhotel-lindenhof'); self::assertResponseStatusCodeSame(404);
        $this->client->request('GET', '/veranstaltungen?zeitraum[]=bad&page[]=bad'); self::assertResponseIsSuccessful();
    }
    public function testCalendarCurrentPreviousNextMultidayAndSeriesWithoutDuplicates(): void
    {
        $this->client->request('GET', '/veranstaltungen/kalender'); self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h2', '05.2030'); self::assertSelectorExists('a[href="/veranstaltungen/kalender?month=2030-04"]'); self::assertSelectorExists('a[href="/veranstaltungen/kalender?month=2030-06"]');
        self::assertSelectorTextContains('[data-date="2030-05-01"]', 'Beispielabend heute');
        foreach (['2030-05-03', '2030-05-04', '2030-05-05'] as $date) { self::assertSelectorTextContains('[data-date="'.$date.'"]', 'Mehrtagige Beispielausstellung'); }
        foreach (['2030-05-01', '2030-05-08', '2030-05-15', '2030-05-22'] as $date) { self::assertCount(1, $this->client->getCrawler()->filter('[data-date="'.$date.'"] a')->reduce(static fn ($node) => $node->text() === 'Wöchentliche Ideenrunde')); }
        self::assertSelectorNotExists('a[href*="inaktive-beispielveranstaltung"]');
        $this->client->request('GET', '/veranstaltungen/kalender?month=2030-04'); self::assertResponseIsSuccessful(); self::assertSelectorTextContains('[data-date="2030-04-29"]', 'Vergangener Beispielabend');
        $this->client->request('GET', '/veranstaltungen/kalender?month=2030-06'); self::assertSelectorTextContains('[data-date="2030-06-01"]', 'Kultur im nächsten Monat');
        $this->client->request('GET', '/veranstaltungen/kalender?month=2031-01'); self::assertSelectorTextContains('body', 'keine Veranstaltungen');
        foreach (['bad', '2030-13', '2030-00', '2030-05-junk', '%3Cscript%3E'] as $month) { $this->client->request('GET', '/veranstaltungen/kalender?month='.$month); self::assertResponseIsSuccessful(); self::assertSelectorTextContains('h2', '05.2030'); }
        $this->client->request('GET', '/veranstaltungen/kalender?month[]=bad'); self::assertResponseIsSuccessful();
    }
    public function testCalendarCategoryFilterAndOccurrenceLinkBelongsToSeries(): void
    {
        $event = $this->event('woechentliche-ideenrunde'); $id = $event->getOccurrences()->last()->getId();
        $this->client->request('GET', '/veranstaltungen/'.$event->getSlug().'/termine/'.$id); self::assertResponseIsSuccessful(); self::assertSelectorTextContains('.event-time', '22.05.2030');
        self::assertSelectorExists('link[rel="canonical"][href="http://localhost/veranstaltungen/'.$event->getSlug().'/termine/'.$id.'"]');
        $this->client->request('GET', '/veranstaltungen/familientag-morgen/termine/'.$id); self::assertResponseStatusCodeSame(404);
        $this->client->request('GET', '/veranstaltungen/kalender?month=2030-05&kategorie=musik'); self::assertResponseIsSuccessful(); self::assertSelectorNotExists('a[href*="familientag-morgen/termine"]');
        self::assertSelectorExists('a[href="/veranstaltungen/kalender?kategorie=musik&month=2030-06"]');
    }
    public function testHomepageAndCompanyUseLimitedCurrentEvents(): void
    {
        $this->client->request('GET', '/'); self::assertResponseIsSuccessful(); self::assertCount(3, $this->client->getCrawler()->filter('#veranstaltungen .event-card'));
        self::assertSame(['Beispielabend heute', 'Familientag morgen', 'Kultur im nächsten Monat'], $this->eventTitles());
        self::assertSelectorNotExists('a[href="#veranstaltungen"]'); self::assertSelectorNotExists('a[href="/#veranstaltungen"]'); self::assertSelectorExists('header a[href="/veranstaltungen"]'); self::assertSelectorExists('footer a[href="/veranstaltungen"]');
        $this->client->request('GET', '/unternehmen/musterladen-hasebogen'); self::assertResponseIsSuccessful(); self::assertCount(3, $this->client->getCrawler()->filter('.event-card'));
        self::assertSelectorExists('a[href="/veranstaltungen?unternehmen=musterladen-hasebogen"]');
        $this->client->request('GET', '/unternehmen/demo-werkstatt-stadtblick'); self::assertSelectorNotExists('#events-heading');
    }
    public function testRepositoryDateRangeMonthFeaturedAndNoCompanyGraph(): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class); $em->clear();
        $repository = static::getContainer()->get(EventRepository::class); $dates = static::getContainer()->get(EventDateRangeResolver::class); [$start, $end] = $dates->resolve('today');
        $items = $repository->findForDateRange($start, $end); self::assertGreaterThanOrEqual(3, count($items));
        self::assertContains('Beispielabend heute', array_map(static fn ($o) => $o->getEvent()->getTitle(), $items));
        foreach ($items as $occurrence) { self::assertTrue($occurrence->getEvent()->getCategories()->isInitialized()); if ($company = $occurrence->getEvent()->getCompany()) { self::assertFalse($company->getImages()->isInitialized()); self::assertFalse($company->getOffers()->isInitialized()); } }
        self::assertNotEmpty($repository->findForMonth($dates->month('2030-05'))); self::assertCount(3, $repository->findUpcomingFeatured());
        $featured = $em->getRepository(Event::class)->findBy(['featured' => true]); foreach ($featured as $event) { $event->setFeatured(false); } $em->flush(); self::assertCount(3, $repository->findUpcomingFeatured());
        self::assertCount(3, $repository->findUpcoming());
    }
    public function testClockAdvanceRemovesEndedEventsButRetainsActiveDetail(): void
    {
        $this->clock->modify('+2 days'); $this->client->request('GET', '/veranstaltungen?zeitraum=today'); self::assertResponseIsSuccessful(); self::assertNotContains('Beispielabend heute', $this->eventTitles());
        $this->client->request('GET', '/veranstaltungen/beispielabend-heute'); self::assertResponseIsSuccessful(); self::assertSelectorTextContains('body', 'Vergangen');
    }
    public function testEmptyStateAndJsonLdCannotEscapeScript(): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class); $event = $this->event(); $event->setShortDescription('</script><script>alert(1)</script>'); $em->flush();
        $this->client->request('GET', '/veranstaltungen/beispielabend-heute'); self::assertResponseIsSuccessful();
        self::assertCount(1, $this->client->getCrawler()->filter('script[type="application/ld+json"]')); self::assertStringNotContainsString('<script>alert(1)</script>', $this->client->getResponse()->getContent());
        $em = static::getContainer()->get(EntityManagerInterface::class); $em->createQuery('UPDATE App\Entity\Event event SET event.active = false')->execute();
        $this->client->request('GET', '/veranstaltungen'); self::assertSelectorTextContains('body', 'keine Veranstaltungen'); $this->client->request('GET', '/'); self::assertSelectorTextContains('#veranstaltungen', 'keine Veranstaltungen');
    }
    public function testPaginationWithCollectionFetchJoinDoesNotDuplicateOrLoseEvents(): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class); $schedule = static::getContainer()->get(EventSchedule::class);
        for ($i = 0; $i < 14; ++$i) {
            $event = (new Event())->setTitle('Zusatz '.$i)->setSlug('zusatz-'.$i)->setStartsAt(new \DateTimeImmutable('2030-05-02 12:00Z'))->setEndsAt(new \DateTimeImmutable('2030-05-02 14:00Z'));
            foreach ($em->getRepository(EventCategory::class)->findBy([], limit: 2) as $category) { $event->addCategory($category); }
            $schedule->synchronize($event); $em->persist($event);
        }
        $em->flush(); $this->client->request('GET', '/veranstaltungen?zeitraum=tomorrow'); self::assertCount(12, $this->client->getCrawler()->filter('.event-card'));
        $first = $this->eventTitles(); $this->client->request('GET', '/veranstaltungen?zeitraum=tomorrow&page=2'); self::assertResponseIsSuccessful(); self::assertCount(4, $this->client->getCrawler()->filter('.event-card')); self::assertSame([], array_intersect($first, $this->eventTitles()));
        self::assertSelectorExists('a[href="/veranstaltungen?zeitraum=tomorrow&page=1"]'); $this->client->request('GET', '/veranstaltungen?page=999'); self::assertResponseStatusCodeSame(404);
    }
}
