<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class CookieNoticeTest extends WebTestCase
{
    public function testPublicPagesIncludeOneInformationalNoticeAndPrivacyLink(): void
    {
        $client = static::createClient();
        foreach (['/datenschutz', '/kontakt', '/werbekreis/ueber-uns', '/login'] as $url) {
            $crawler = $client->request('GET', $url);
            self::assertResponseIsSuccessful();
            self::assertSelectorCount(1, 'section.cookie-notice[aria-label="Hinweis zu Cookies"]');
            self::assertSelectorTextContains('.cookie-notice', 'Wir verwenden ausschließlich technisch notwendige Cookies');
            self::assertSelectorTextSame('.cookie-notice button[type="button"]', 'Verstanden');
            self::assertSelectorExists('.cookie-notice a[href="'.static::getContainer()->get(UrlGeneratorInterface::class)->generate('privacy').'"]');
            self::assertSelectorTextSame('.cookie-notice a', 'Datenschutz');
            self::assertSelectorNotExists('.cookie-notice[role="alert"], .cookie-notice[aria-modal], .cookie-notice input');
            foreach (['Alle akzeptieren', 'Alle ablehnen', 'Auswahl speichern', 'Marketing', 'Statistik', 'Tracking'] as $text) {
                self::assertStringNotContainsString($text, $crawler->filter('.cookie-notice')->text());
            }
            self::assertNull($client->getCookieJar()->get('wk_cookie_notice'));
        }
    }

    public function testPrivacyDocumentsThePreferenceAndSeparateMapAction(): void
    {
        $client = static::createClient();
        $client->request('GET', '/datenschutz');
        foreach (['wk_cookie_notice', '180 Tage', 'Keine Weitergabe an Dritte.', 'Im aktuellen Stand sind keine Marketing- oder Tracking-Cookies eingebaut.', 'keine Zustimmung zum Laden der Karte', 'separate bewusste Nutzeraktion'] as $text) {
            self::assertSelectorTextContains('main', $text);
        }
        self::assertSelectorTextSame('main dd:nth-of-type(3) code', '1');
    }
}
