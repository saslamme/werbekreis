<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Offer;
use App\Enum\OfferType;
use App\Repository\OfferRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;

final class PublicOfferTest extends OfferDatabaseTestCase
{
    public function testPublicDirectoryShowsOnlyCurrentOffersInStableOrder(): void
    {
        $this->client->request('GET', '/angebote');
        self::assertResponseIsSuccessful();
        self::assertSame(['Genuss-Auszeit', 'Lieblingsstücke zum Aktionspreis', 'Wohlfühlwoche', '20 Prozent auf Beispieljacken', 'Neue Geschenkideen', 'Werkstatt-Neuheit'], $this->offerTitles());
        self::assertSelectorTextContains('h1', 'Angebote in Haselünne');
        self::assertSelectorTextContains('.offer-price', '2 für 1');
        self::assertSelectorExists('a[href="/angebote/lieblingsstuecke-zum-aktionspreis"]');
    }

    public static function hiddenSlugs(): iterable
    {
        foreach (['saison-ausblick', 'sommer-rueckblick', 'pausierte-aktion', 'fiktives-hotelangebot', 'unknown'] as $slug) {
            yield [$slug];
        }
    }

    #[DataProvider('hiddenSlugs')]
    public function testNonPublicDetailsReturn404(string $slug): void
    {
        $this->client->request('GET', '/angebote/'.$slug);
        self::assertResponseStatusCodeSame(404);
    }

    public function testDetailPriceSeoAndStructuredData(): void
    {
        $this->client->request('GET', '/angebote/lieblingsstuecke-zum-aktionspreis');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('title', 'Lieblingsstücke zum Aktionspreis | Musterladen Hasebogen');
        self::assertSelectorTextContains('.offer-price', '59,90 €');
        self::assertSelectorTextContains('.offer-regular', '79,90 €');
        self::assertSelectorExists('link[rel="canonical"][href="http://localhost/angebote/lieblingsstuecke-zum-aktionspreis"]');
        self::assertSelectorExists('meta[property="og:image"]');
        $data = json_decode($this->client->getCrawler()->filter('script[type="application/ld+json"]')->text(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('Offer', $data['@type']);
        self::assertSame('59.90', $data['price']);
        self::assertSame('EUR', $data['priceCurrency']);
        self::assertSame('Musterladen Hasebogen', $data['seller']['name']);
        self::assertArrayNotHasKey('availability', $data);
        $this->client->request('GET', '/angebote/werkstatt-neuheit');
        self::assertSelectorNotExists('.offer-price');
        $data = json_decode($this->client->getCrawler()->filter('script[type="application/ld+json"]')->text(), true, flags: JSON_THROW_ON_ERROR);
        self::assertArrayNotHasKey('price', $data);
        self::assertArrayNotHasKey('priceCurrency', $data);
        self::assertSelectorNotExists('.offer-detail-image');
    }

    public function testTypeAndCompanyFiltersCombineAndKeepState(): void
    {
        $this->client->request('GET', '/angebote?typ=new_product&unternehmen=musterladen-hasebogen');
        self::assertResponseIsSuccessful();
        self::assertSame(['Neue Geschenkideen'], $this->offerTitles());
        self::assertSelectorExists('option[value="new_product"][selected]');
        self::assertSelectorExists('input[name="unternehmen"][value="musterladen-hasebogen"]');
        self::assertSelectorExists('meta[name="robots"][content="noindex, follow"]');
        self::assertSelectorExists('link[rel="canonical"][href="http://localhost/angebote"]');
        $this->client->request('GET', '/angebote?typ=invalid');
        self::assertResponseIsSuccessful();
        self::assertCount(6, $this->offerTitles());
        self::assertSelectorTextContains('[role="status"]', 'Unbekannter Angebotstyp');
        $this->client->request('GET', '/angebote?unternehmen=beispielhotel-lindenhof');
        self::assertResponseStatusCodeSame(404);
    }

    public function testPaginationPreservesFilters(): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $company = $this->firstCompany();
        for ($i = 0; $i < 14; ++$i) {
            $em->persist((new Offer())->setCompany($company)->setTitle(sprintf('Pagination %02d', $i))->setSlug('pagination-'.$i)->setType(OfferType::Discount));
        }
        $em->flush();
        $this->client->request('GET', '/angebote?typ=discount&unternehmen=musterladen-hasebogen');
        self::assertCount(12, $this->offerTitles());
        $url = $this->client->getCrawler()->selectLink('Weiter')->link()->getUri();
        self::assertStringContainsString('typ=discount&unternehmen=musterladen-hasebogen&page=2', $url);
        $this->client->request('GET', $url);
        self::assertResponseIsSuccessful();
        self::assertCount(3, $this->offerTitles());
        $this->client->request('GET', '/angebote?typ=discount&unternehmen=musterladen-hasebogen&page=3');
        self::assertResponseStatusCodeSame(404);
    }

    public function testHomepageUsesFeaturedThenCurrentFallbackAndUpdatesNavigation(): void
    {
        $this->client->request('GET', '/');
        self::assertResponseIsSuccessful();
        self::assertSame(['Genuss-Auszeit', 'Lieblingsstücke zum Aktionspreis', 'Wohlfühlwoche'], $this->offerTitles());
        self::assertSelectorExists('header a[href="/angebote"]');
        self::assertSelectorExists('footer a[href="/angebote"]');
        self::assertSelectorTextNotContains('#angebote', 'Beispielinhalte');
        self::assertSelectorExists('#angebote a[href="/angebote"]');
    }

    public function testCompanyShowsOnlyItsCurrentOffersWithMoreLink(): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->persist((new Offer())->setCompany($this->firstCompany())->setTitle('Zusätzlich')->setSlug('zusaetzlich'));
        $em->flush();
        $this->client->request('GET', '/unternehmen/musterladen-hasebogen');
        self::assertResponseIsSuccessful();
        self::assertCount(3, $this->offerTitles());
        self::assertNotContains('Saison-Ausblick', $this->offerTitles());
        self::assertSelectorExists('a[href="/angebote?unternehmen=musterladen-hasebogen"]');
        $this->client->request('GET', '/unternehmen/beispielcafe-uferpause');
        self::assertSame(['Genuss-Auszeit'], $this->offerTitles());
    }

    public function testClockAdvancementHidesExpiredAndRevealsScheduledOffers(): void
    {
        $this->clock->sleep(4 * 86400);
        $this->client->request('GET', '/angebote');
        self::assertNotContains('Genuss-Auszeit', $this->offerTitles());
        self::assertNotContains('Wohlfühlwoche', $this->offerTitles());
        self::assertContains('Saison-Ausblick', $this->offerTitles());
        $this->client->request('GET', '/angebote/genuss-auszeit');
        self::assertResponseStatusCodeSame(404);
    }

    public function testEmptyStateAndImagesRespectScheduling(): void
    {
        $offer = $this->offer();
        $fileName = $offer->getImagePath();
        $this->client->request('GET', '/angebote/'.$offer->getSlug().'/bilder/'.$fileName);
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'image/png');
        self::assertResponseHeaderSame('X-Content-Type-Options', 'nosniff');
        self::assertStringContainsString('no-store', $this->client->getResponse()->headers->get('Cache-Control'));
        $this->clock->sleep(20 * 86400);
        $this->client->request('GET', '/angebote/'.$offer->getSlug().'/bilder/'.$fileName);
        self::assertResponseStatusCodeSame(404);
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->createQuery('UPDATE App\Entity\Offer offer SET offer.active = false')->execute();
        $this->client->request('GET', '/angebote');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('[role="status"]', 'Aktuell sind keine Angebote verfügbar.');
    }

    public function testListingFetchJoinsCompanyWithoutLoadingObjectGraph(): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->clear();
        foreach (static::getContainer()->get(OfferRepository::class)->findCurrentFeatured() as $offer) {
            self::assertFalse($em->getUnitOfWork()->isUninitializedObject($offer->getCompany()));
            self::assertFalse($offer->getCompany()->getImages()->isInitialized());
            self::assertFalse($offer->getCompany()->getCategories()->isInitialized());
            self::assertFalse($offer->getCompany()->getOffers()->isInitialized());
        }
    }
}
