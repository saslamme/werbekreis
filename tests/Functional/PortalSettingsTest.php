<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Repository\PortalSettingsRepository;
use App\Twig\PortalSettingsExtension;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;

final class PortalSettingsTest extends DirectoryDatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->setLinks(null, null);
    }

    protected function tearDown(): void
    {
        $this->setLinks(null, null);
        parent::tearDown();
    }

    private function setLinks(?string $facebook, ?string $instagram): void
    {
        $settings = static::getContainer()->get(PortalSettingsRepository::class)->getSettings();
        $settings->setFacebookUrl($facebook)->setInstagramUrl($instagram);
        static::getContainer()->get(EntityManagerInterface::class)->flush();
    }

    public function testProviderMemoizesPerRequestAndReadsFreshOnNextRequest(): void
    {
        $container = static::getContainer();
        $requests = $container->get('request_stack');
        $provider = $container->get(PortalSettingsExtension::class);
        $em = $container->get(EntityManagerInterface::class);
        $requests->push(new \Symfony\Component\HttpFoundation\Request());
        try {
            $first = $provider->getSettings();
            $em->clear();
            // Clearing the identity map would require a new query without request memoization.
            self::assertSame($first, $provider->getSettings());
            $requests->push(new \Symfony\Component\HttpFoundation\Request());
            try { self::assertSame($first, $provider->getSettings()); }
            finally { $requests->pop(); }
        } finally { $requests->pop(); }
        $requests->push(new \Symfony\Component\HttpFoundation\Request());
        try { self::assertNotSame($first, $provider->getSettings()); }
        finally { $requests->pop(); }
    }

    public function testDatabaseRejectsAdditionalSettingsRows(): void
    {
        $connection = static::getContainer()->get(EntityManagerInterface::class)->getConnection();
        $this->expectException(\Doctrine\DBAL\Exception\DriverException::class);
        $connection->executeStatement('INSERT INTO PortalSettings (id, createdAt, updatedAt) VALUES (2, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)');
    }

    public function testAdminCanSaveAndClearLinks(): void
    {
        $this->login('admin');
        $this->client->request('GET', '/admin/settings');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.admin-sidebar a.is-active[href="/admin/settings"][aria-current="page"]');
        self::assertSelectorExists('input[name="portal_settings[_token]"]');
        $this->client->submitForm('Speichern', ['portal_settings[facebookUrl]' => 'https://www.facebook.com/example', 'portal_settings[instagramUrl]' => 'https://www.instagram.com/example']);
        self::assertResponseRedirects('/admin/settings', 303);
        $this->client->followRedirect();
        self::assertSelectorTextContains('[role="status"]', 'Einstellungen gespeichert.');
        self::assertSelectorExists('input[value="https://www.facebook.com/example"]');
        $this->client->request('GET', '/');
        self::assertSelectorCount(2, '.site-footer .social-link');
        $this->client->request('GET', '/admin/settings');
        $this->client->submitForm('Speichern', ['portal_settings[facebookUrl]' => '', 'portal_settings[instagramUrl]' => '']);
        self::assertResponseRedirects('/admin/settings', 303);
        $this->client->request('GET', '/');
        self::assertSelectorNotExists('.site-footer .social-links');
        self::assertSame(1, static::getContainer()->get(PortalSettingsRepository::class)->count([]));
    }

    public function testAccessAndNavigation(): void
    {
        foreach (['GET', 'POST'] as $method) {
            $this->client->request($method, '/admin/settings');
            self::assertResponseRedirects('/login');
        }
        $this->login('editor');
        $this->client->request('GET', '/admin');
        self::assertSelectorNotExists('.admin-sidebar a[href="/admin/settings"]');
        foreach (['GET', 'POST'] as $method) {
            $this->client->request($method, '/admin/settings');
            self::assertResponseStatusCodeSame(403);
        }
    }

    public static function invalidUrls(): iterable
    {
        foreach (['facebook', 'javascript:alert(1)', 'ftp://example.com', '//example.com', 'https://example.com/'.str_repeat('x', 2048)] as $url) {
            foreach (['facebookUrl', 'instagramUrl'] as $field) { yield [$field, $url]; }
        }
    }

    #[DataProvider('invalidUrls')]
    public function testInvalidUrlsAreNotSaved(string $field, string $url): void
    {
        $this->login('admin');
        $this->client->request('GET', '/admin/settings');
        $this->client->submitForm('Speichern', ['portal_settings['.$field.']' => $url]);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorExists('.invalid-feedback');
        static::getContainer()->get(EntityManagerInterface::class)->clear();
        $settings = static::getContainer()->get(PortalSettingsRepository::class)->getSettings();
        self::assertNull($settings->getFacebookUrl());
        self::assertNull($settings->getInstagramUrl());
    }

    public function testInvalidCsrfDoesNotSave(): void
    {
        $this->login('admin');
        $this->client->request('POST', '/admin/settings', ['portal_settings' => ['facebookUrl' => 'https://example.com', '_token' => 'invalid']]);
        self::assertResponseStatusCodeSame(422);
        static::getContainer()->get(EntityManagerInterface::class)->clear();
        self::assertNull(static::getContainer()->get(PortalSettingsRepository::class)->getSettings()->getFacebookUrl());
    }

    public static function links(): iterable
    {
        yield [null, null];
        yield ['https://www.facebook.com/example', null];
        yield [null, 'https://www.instagram.com/example'];
        yield ['http://www.facebook.com/example', 'https://www.instagram.com/example'];
    }

    #[DataProvider('links')]
    public function testFooterOptionalLinks(?string $facebook, ?string $instagram): void
    {
        $this->setLinks($facebook, $instagram);
        foreach (['/', '/unternehmen', '/werbekreis/ueber-uns', '/impressum'] as $path) {
            $crawler = $this->client->request('GET', $path);
            self::assertResponseIsSuccessful();
            self::assertSelectorCount((int) ($facebook !== null) + (int) ($instagram !== null), '.site-footer .social-link');
            foreach (['Facebook' => $facebook, 'Instagram' => $instagram] as $name => $url) {
                $link = $crawler->filter('.site-footer a[aria-label="Werbekreis Haselünne auf '.$name.'"]');
                self::assertCount($url === null ? 0 : 1, $link);
                if ($url === null) { continue; }
                self::assertSame($url, $link->attr('href'));
                self::assertSame('_blank', $link->attr('target'));
                self::assertSame('noopener noreferrer', $link->attr('rel'));
                self::assertSame('true', $link->filter('i.fa-brands')->attr('aria-hidden'));
            }
            self::assertSame($facebook !== null || $instagram !== null, $crawler->filter('.site-footer .social-links')->count() === 1);
        }
    }
}
