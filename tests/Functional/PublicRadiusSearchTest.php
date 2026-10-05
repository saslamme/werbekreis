<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Company;
use App\Geo\GeocodingResult;
use App\Geo\GeocodingServiceInterface;
use App\Geo\GeoPoint;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;

final class PublicRadiusSearchTest extends PublicDirectoryTestCase
{
    public function testRadiusFiltersInDatabaseAndOrdersByDistanceBeforeFeatured(): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $near = $this->company('Demo-Werkstatt Stadtblick')->setLatitude(52.675)->setLongitude(7.484);
        $em->flush();
        $this->client->request('GET', '/unternehmen?lat=52.674&lng=7.484&radius=10&ansicht=karte');
        self::assertResponseIsSuccessful();
        self::assertSame(['Musterladen Hasebogen', 'Demo-Werkstatt Stadtblick', 'Beispielcafé Uferpause'], $this->cardNames());
        self::assertSelectorTextNotContains('[data-company-results]', 'Musterpraxis Wohlgefühl');
        self::assertSelectorTextContains('.company-distance', '0,0 km');
        self::assertSelectorExists('[data-company-map]');
        self::assertSelectorExists('meta[name="robots"][content="noindex, follow"]');
        self::assertSelectorExists('link[rel="canonical"][href="http://localhost/unternehmen"]');
        $this->client->request('GET', '/unternehmen?lat=52.674&lng=7.484&radius=1');
        self::assertSame(['Musterladen Hasebogen', 'Demo-Werkstatt Stadtblick'], $this->cardNames());
    }

    public static function combinations(): iterable
    {
        yield ['q=KUCHEN', ['Beispielcafé Uferpause']];
        yield ['kategorie=handel', ['Musterladen Hasebogen']];
        yield ['q=Hasebogen&kategorie=handel', ['Musterladen Hasebogen']];
        yield ['q=Hasebogen&kategorie=gastronomie', []];
    }

    #[DataProvider('combinations')]
    public function testExistingFiltersCombineWithRadius(string $filters, array $expected): void
    {
        $this->client->request('GET', '/unternehmen?lat=52.674&lng=7.484&radius=10&'.$filters);
        self::assertResponseIsSuccessful();
        self::assertSame($expected, $this->cardNames());
    }

    public function testFiveAndTwentyFiveKmExcludeMissingCoordinates(): void
    {
        $this->client->request('GET', '/unternehmen?lat=52.674&lng=7.484&radius=5');
        self::assertSame(['Musterladen Hasebogen'], $this->cardNames());
        $this->client->request('GET', '/unternehmen?lat=52.674&lng=7.484&radius=25');
        self::assertSame(['Musterladen Hasebogen', 'Beispielcafé Uferpause', 'Demo-Werkstatt Stadtblick'], $this->cardNames());
    }

    public function testMapDataIsSafeAndListKeepsCompaniesWithoutCoordinates(): void
    {
        $company = $this->company('Musterladen Hasebogen')->setName('<script>alert("x")</script>');
        static::getContainer()->get(EntityManagerInterface::class)->flush();
        $this->client->request('GET', '/unternehmen?ansicht=karte');
        self::assertResponseIsSuccessful();
        self::assertCount(4, $this->cardNames());
        $data = json_decode($this->client->getCrawler()->filter('[data-company-map]')->attr('data-map'), true, flags: JSON_THROW_ON_ERROR);
        self::assertCount(3, $data['markers']);
        self::assertNotContains('Musterpraxis Wohlgefühl', array_column($data['markers'], 'name'));
        self::assertStringNotContainsString('<script>alert("x")</script>', $this->client->getResponse()->getContent());
        $this->client->request('GET', '/unternehmen/musterladen-hasebogen');
        self::assertSelectorExists('[data-company-map]');
        $this->client->request('GET', '/unternehmen/musterpraxis-wohlgefuehl');
        self::assertSelectorNotExists('[data-company-map]');
    }

    public function testInvalidLocationParametersFallBackWithoutServerError(): void
    {
        foreach (['lat=91&lng=7', 'lat=0', 'lat[]=0&lng=0', 'lat=NaN&lng=0', 'radius[]=10', 'radius=-5', 'radius=999999'] as $query) {
            $this->client->request('GET', '/unternehmen?'.$query);
            self::assertResponseIsSuccessful();
            self::assertCount(4, $this->cardNames());
            self::assertSelectorExists('[role="status"]');
        }
    }

    public function testGeocodingSuccessUsesMockAndFilters(): void
    {
        static::getContainer()->set(GeocodingServiceInterface::class, new class implements GeocodingServiceInterface {
            public function search(string $location): GeocodingResult
            {
                return new GeocodingResult(new GeoPoint(52.674, 7.484, 'Haselünne'));
            }
        });
        $this->client->request('GET', '/unternehmen?ort=49740&radius=5');
        self::assertResponseIsSuccessful();
        self::assertSame(['Musterladen Hasebogen'], $this->cardNames());
        self::assertInputValueSame('ort', '49740');
    }

    public function testGeocodingFailureKeepsOtherFiltersUsable(): void
    {
        foreach ([false, true] as $unavailable) {
            // Reboot between requests so each mock replaces an unused service.
            if ($unavailable) {
                static::ensureKernelShutdown();
                $this->client = static::createClient();
            }
            static::getContainer()->set(GeocodingServiceInterface::class, new class($unavailable) implements GeocodingServiceInterface {
                public function __construct(private bool $unavailable) {}
                public function search(string $location): GeocodingResult { return new GeocodingResult(unavailable: $this->unavailable); }
            });
            $this->client->request('GET', '/unternehmen?ort=Unbekannt&q=KUCHEN');
            self::assertResponseIsSuccessful();
            self::assertSame(['Beispielcafé Uferpause'], $this->cardNames());
            self::assertSelectorTextContains('[role="status"]', $unavailable ? 'derzeit nicht verfügbar' : 'nicht gefunden');
        }
    }

    public function testRadiusPaginationRetainsCoordinatesAndView(): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $category = $this->company('Musterladen Hasebogen')->getCategories()->first();
        for ($i = 0; $i < 14; ++$i) {
            $em->persist((new Company())->setName(sprintf('Geo Test %02d', $i))->setSlug('geo-test-'.$i)->addCategory($category)->setLatitude(52.674 + $i * .0001)->setLongitude(7.484));
        }
        $em->flush();
        $this->client->request('GET', '/unternehmen?q=Geo+Test&kategorie=handel&lat=52.674&lng=7.484&radius=5&ansicht=karte');
        self::assertResponseIsSuccessful();
        self::assertCount(12, $this->cardNames());
        $url = $this->client->getCrawler()->selectLink('Weiter')->link()->getUri();
        parse_str(parse_url($url, PHP_URL_QUERY), $parameters);
        self::assertSame('52.674', $parameters['lat']);
        self::assertSame('7.484', $parameters['lng']);
        self::assertSame('5', $parameters['radius']);
        self::assertSame('karte', $parameters['ansicht']);
        self::assertSame('handel', $parameters['kategorie']);
        $this->client->request('GET', $url);
        self::assertResponseIsSuccessful();
        self::assertSame(['Geo Test 12', 'Geo Test 13'], $this->cardNames());
    }
}
