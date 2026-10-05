<?php

declare(strict_types=1);
namespace App\Tests\Functional;
use App\DataFixtures\VoucherFixtures;

final class VoucherPublicTest extends VoucherDatabaseTestCase
{
    public function testLandingAndAcceptanceNetworkUseActiveProductsAndDistinctCompanies(): void
    {
        $this->client->request('GET', '/gutschein'); self::assertResponseIsSuccessful(); self::assertSelectorTextContains('h1', 'Gutschein');
        self::assertStringContainsString('TEST Werbekreis-Gutschein', $this->client->getResponse()->getContent()); self::assertStringNotContainsString('TEST Inaktives Produkt', $this->client->getResponse()->getContent());
        $this->client->request('GET', '/gutschein/akzeptanzstellen'); self::assertResponseIsSuccessful(); self::assertCount(3, $this->cardNames()); self::assertSame(3, count(array_unique($this->cardNames())));
        $slug = $this->voucher(1)->getProduct()->getSlug();
        $this->client->request('GET', '/gutschein/akzeptanzstellen', ['produkt' => $slug]); self::assertResponseIsSuccessful(); self::assertCount(2, $this->cardNames());
        $this->client->request('GET', '/gutschein/akzeptanzstellen', ['produkt' => $slug, 'q' => 'Uferpause', 'kategorie' => 'gastronomie']); self::assertResponseIsSuccessful(); self::assertSame(['Beispielcafé Uferpause'], $this->cardNames());
        self::assertStringContainsString('Fiktive Beispielstraße', $this->client->getResponse()->getContent());
        $this->client->request('GET', '/gutschein/akzeptanzstellen?produkt=missing'); self::assertResponseStatusCodeSame(404);
    }
    public function testPublicChecksStatusesWithoutExposingCodeOrPrivateHistory(): void
    {
        foreach ([0 => false, 1 => true, 2 => true, 3 => false, 4 => false, 5 => false, 6 => false] as $index => $usable) {
            $crawler = $this->client->request('GET', '/gutschein/pruefen'); $form = $crawler->selectButton('Gutschein prüfen')->form(['voucher_lookup[code]' => strtolower(VoucherFixtures::code($index))]);
            $this->client->submit($form); self::assertResponseIsSuccessful(); self::assertSelectorTextContains('#check-result', $usable ? 'Gutschein gültig' : 'Gutschein derzeit nicht gültig');
            $html = $this->client->getResponse()->getContent();
            foreach ([VoucherFixtures::code($index), strtolower(VoucherFixtures::code($index)), 'TEST interne Notiz', 'TEST-REF', 'TEST Auditnotiz', 'admin@example.local', 'redeemer@example.local'] as $private) { self::assertStringNotContainsString($private, $html); }
            self::assertResponseHeaderSame('X-Robots-Tag', 'noindex, nofollow'); self::assertStringContainsString('no-store', $this->client->getResponse()->headers->get('Cache-Control')); self::assertResponseHeaderSame('Referrer-Policy', 'no-referrer');
            self::assertSame('/gutschein/pruefen', parse_url($this->client->getRequest()->getUri(), PHP_URL_PATH)); self::assertNull(parse_url($this->client->getRequest()->getUri(), PHP_URL_QUERY));
        }
    }
    public function testGetQueryDoesNotCheckCodeAndPostRequiresCsrf(): void
    {
        $this->client->request('GET', '/gutschein/pruefen', ['code' => VoucherFixtures::code(1)]); self::assertResponseIsSuccessful(); self::assertSelectorNotExists('#check-result');
        $this->client->request('POST', '/gutschein/pruefen', ['voucher_lookup' => ['code' => VoucherFixtures::code(1)]]); self::assertResponseStatusCodeSame(422); self::assertSelectorNotExists('#check-result');
    }
    public function testUnknownCodesReceiveGenericResultAndRateLimit(): void
    {
        for ($i = 0; $i < 20; ++$i) {
            $crawler = $this->client->request('GET', '/gutschein/pruefen'); $this->client->submit($crawler->selectButton('Gutschein prüfen')->form(['voucher_lookup[code]' => 'WK-ZZZZ-YYYY-XXXX-WWWW']));
            self::assertResponseIsSuccessful(); self::assertSelectorTextContains('[role="status"]', 'Der Gutschein konnte nicht geprüft werden.');
        }
        $crawler = $this->client->request('GET', '/gutschein/pruefen'); $this->client->submit($crawler->selectButton('Gutschein prüfen')->form(['voucher_lookup[code]' => 'WK-ZZZZ-YYYY-XXXX-WWWW']));
        self::assertResponseStatusCodeSame(429); self::assertResponseHeaderSame('Retry-After', '900');
    }
}
