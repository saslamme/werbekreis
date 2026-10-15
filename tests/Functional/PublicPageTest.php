<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class PublicPageTest extends WebTestCase
{
    public static function pages(): iterable
    {
        yield 'about' => ['/werbekreis/ueber-uns', 'Über den Werbekreis Haselünne', 'Über uns'];
        yield 'board' => ['/werbekreis/vorstand', 'Unser Vorstand', 'Vorstand'];
        yield 'membership' => ['/mitglied-werden', 'Mitglied werden', 'Mitglied werden'];
        yield 'contact' => ['/kontakt', 'Kontakt', 'Kontakt'];
        yield 'imprint' => ['/impressum', 'Impressum', 'Impressum'];
        yield 'privacy' => ['/datenschutz', 'Datenschutzerklärung', 'Datenschutz'];
    }

    #[DataProvider('pages')]
    public function testPublicPage(string $url, string $heading, string $label): void
    {
        $client = static::createClient();
        $client->request('GET', $url);
        self::assertResponseStatusCodeSame(200);
        self::assertSelectorExists('body.public-frontend');
        self::assertSelectorCount(1, 'h1');
        self::assertSelectorTextSame('h1', $heading);
        self::assertSelectorExists('.directory-hero nav[aria-label="Brotkrumen"] a[href="/"]');
        self::assertSelectorTextSame('.breadcrumb [aria-current="page"]', $label);
        self::assertSelectorTextSame('title', $label.' | Werbekreis Haselünne');
        self::assertSelectorExists('meta[name="description"][content]:not([content=""])');
        self::assertSelectorExists('link[rel="canonical"][href="http://localhost'.$url.'"]');
        self::assertSelectorNotExists('meta[name="robots"][content*="noindex"]');
        foreach (self::pages() as [$path, , $footerLabel]) {
            self::assertSelectorTextSame('footer a[href="'.$path.'"]', $footerLabel);
        }
        self::assertSelectorExists('.utility-bar a[href="/mitglied-werden"]');
        self::assertSelectorTextSame('header .nav-link[href="/werbekreis/ueber-uns"]', 'Werbekreis');
        self::assertSelectorNotExists('a[href$="#mitglied"]');
        self::assertSelectorTextNotContains('body', '– folgt');
        self::assertSelectorTextNotContains('body', 'info@example.local');
        self::assertStringNotContainsString('info@example.local', (string) $client->getResponse()->getContent());
        self::assertSelectorNotExists('main form');
    }

    public function testBoardListsOnlySuppliedPeopleAndRoles(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/werbekreis/vorstand');
        self::assertSame([
            'Andreas Walburg', 'Norbert Schnakenberg', 'Daniel Pesch', 'Cornelia Sturm',
            'Christopher Jansen', 'Philipp Langen', 'Robert Münster', 'Ingo Hoffmeister', 'Tanja Kramer',
        ], $crawler->filter('.page-card h2')->each(static fn ($node): string => $node->text()));
        self::assertSame([
            '1. Vorsitzender', '2. Vorsitzender', 'Schriftführer', 'Kassiererin',
            'Vorstand', 'Vorstand', 'Vorstand', 'Vorstand', 'Werbung',
        ], $crawler->filter('.page-card p')->each(static fn ($node): string => $node->text()));
        self::assertSelectorExists('.breadcrumb a[href="/werbekreis/ueber-uns"]');
        self::assertSelectorNotExists('main img');
    }

    public function testContactDetailsAndMembershipLinks(): void
    {
        $client = static::createClient();
        foreach (['/kontakt', '/impressum', '/mitglied-werden'] as $url) {
            $client->request('GET', $url);
            self::assertSelectorTextContains('main', '05961 95 72 21 3');
            self::assertSelectorExists('main a[href="tel:059619572213"]');
            self::assertSelectorExists('main a[href="mailto:info@werbekreis-haseluenne.de"]');
        }
        self::assertSelectorExists('main a[href="/kontakt"]');
        $client->request('GET', '/werbekreis/ueber-uns');
        self::assertSelectorExists('main a[href="/mitglied-werden"]');
        self::assertSelectorExists('main a[href="/werbekreis/vorstand"]');
        $client->request('GET', '/kontakt');
        foreach (['Werbekreis Haselünne e.V.', 'Andreas Walburg', 'Vorsitzender', 'Steintorstraße 5', '49740 Haselünne'] as $text) {
            self::assertSelectorTextContains('main address', $text);
        }
    }

    public function testLegalPagesRetainReviewNotesAndActualMapBehavior(): void
    {
        $client = static::createClient();
        $client->request('GET', '/impressum');
        self::assertSelectorTextContains('main', 'Registerangaben werden vor dem produktiven Start ergänzt.');
        $client->request('GET', '/datenschutz');
        self::assertSelectorCount(8, 'main h2');
        self::assertSelectorTextContains('main', 'vor dem endgültigen Produktivstart rechtlich zu prüfen.');
        self::assertSelectorTextContains('main', 'erst geladen, wenn Sie auf „Karte laden“ klicken');
        self::assertSelectorNotExists('[data-company-map]');
    }
}
