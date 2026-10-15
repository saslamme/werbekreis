<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Repository\PortalSettingsRepository;
use App\Service\PortalAssetStorage;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class LogoDimensionsTest extends DirectoryDatabaseTestCase
{
    private ?string $upload = null;

    protected function setUp(): void { parent::setUp(); $this->resetSettings(); }
    protected function tearDown(): void
    {
        $this->resetSettings();
        if ($this->upload !== null) { (new Filesystem())->remove($this->upload); }
        parent::tearDown();
    }

    private function resetSettings(): void
    {
        $settings = static::getContainer()->get(PortalSettingsRepository::class)->getSettings();
        $settings->setLogoMaxWidth(null)->setLogoMaxHeight(null)->setFacebookUrl(null)->setInstagramUrl(null);
        static::getContainer()->get(PortalAssetStorage::class)->save($settings, null, true);
    }

    private function createLogo(): string
    {
        $this->upload = tempnam(sys_get_temp_dir(), 'logo-dimensions-');
        file_put_contents($this->upload, '<svg xmlns="http://www.w3.org/2000/svg" width="1200" height="300"><rect width="1200" height="300" fill="#214d3b"/></svg>');
        $settings = static::getContainer()->get(PortalSettingsRepository::class)->getSettings();
        static::getContainer()->get(PortalAssetStorage::class)->save($settings, new UploadedFile($this->upload, 'original.svg', test: true));
        return $settings->getLogoFileName();
    }

    public function testAdminSavesUpdatesAndClearsDimensionsWithoutChangingLogoOrSocialLinks(): void
    {
        $name = $this->createLogo();
        $storage = static::getContainer()->get(PortalAssetStorage::class);
        $path = $storage->path($name);
        $hash = hash_file('sha256', $path);
        $this->login('admin');
        $this->client->request('GET', '/admin/settings');
        self::assertSelectorExists('#portal_settings_logoMaxWidth[min="40"][max="1000"][step="1"]');
        self::assertSelectorExists('#portal_settings_logoMaxHeight[min="20"][max="400"][step="1"]');
        foreach ([[320, 90], [280, 75], [null, null]] as [$width, $height]) {
            $this->client->submitForm('Speichern', [
                'portal_settings[logoMaxWidth]' => $width === null ? '' : (string) $width,
                'portal_settings[logoMaxHeight]' => $height === null ? '' : (string) $height,
                'portal_settings[facebookUrl]' => 'https://facebook.com/test',
                'portal_settings[instagramUrl]' => 'https://instagram.com/test',
            ]);
            self::assertResponseRedirects('/admin/settings', 303);
            $this->client->followRedirect();
            $em = static::getContainer()->get(EntityManagerInterface::class);
            $em->clear();
            $settings = static::getContainer()->get(PortalSettingsRepository::class)->getSettings();
            self::assertSame($width, $settings->getLogoMaxWidth());
            self::assertSame($height, $settings->getLogoMaxHeight());
            self::assertSame($name, $settings->getLogoFileName());
            self::assertSame('original.svg', $settings->getLogoOriginalName());
            self::assertSame($hash, hash_file('sha256', $path));
            self::assertSame('https://facebook.com/test', $settings->getFacebookUrl());
            self::assertSame('https://instagram.com/test', $settings->getInstagramUrl());
            $preview = $this->client->getCrawler()->filter('.admin-logo-preview')->attr('style');
            self::assertStringContainsString('--portal-logo-max-width: '.($width ?? 240).'px;', $preview);
            self::assertStringContainsString('--portal-logo-max-height: '.($height ?? 60).'px;', $preview);
        }
    }

    public static function invalidDimensions(): iterable
    {
        foreach (['logoMaxWidth' => ['0', '20', '39', '1001', '-1'], 'logoMaxHeight' => ['0', '5', '19', '401', '-1']] as $field => $values) {
            foreach ($values as $value) { yield [$field, $value]; }
        }
        foreach (['logoMaxWidth', 'logoMaxHeight'] as $field) {
            foreach (['240.5', '240,5', '240.0', '2e2', '2e-1', 'text', '100px;color:red', 'calc(100vw)', '100%;position:fixed', 'url(...)', '10rem', 'auto'] as $value) {
                yield [$field, $value];
            }
        }
    }

    #[DataProvider('invalidDimensions')]
    public function testInvalidDimensionsReturnFormErrorsAndDoNotPersist(string $field, string $value): void
    {
        $settings = static::getContainer()->get(PortalSettingsRepository::class)->getSettings();
        $settings->setLogoMaxWidth(320)->setLogoMaxHeight(90);
        static::getContainer()->get(EntityManagerInterface::class)->flush();
        $this->login('admin');
        $this->client->request('GET', '/admin/settings');
        $this->client->submitForm('Speichern', ['portal_settings['.$field.']' => $value]);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorExists('#portal_settings_'.$field.'.is-invalid');
        self::assertSelectorExists('.invalid-feedback');
        static::getContainer()->get(EntityManagerInterface::class)->clear();
        $settings = static::getContainer()->get(PortalSettingsRepository::class)->getSettings();
        self::assertSame(320, $settings->getLogoMaxWidth());
        self::assertSame(90, $settings->getLogoMaxHeight());
    }

    public static function dimensions(): iterable
    {
        yield [320, 90]; yield [null, null]; yield [320, null]; yield [null, 90];
    }

    #[DataProvider('dimensions')]
    public function testPublicLogoDimensionsAndIndependentDefaults(?int $width, ?int $height): void
    {
        $this->createLogo();
        $settings = static::getContainer()->get(PortalSettingsRepository::class)->getSettings();
        $settings->setLogoMaxWidth($width)->setLogoMaxHeight($height);
        static::getContainer()->get(EntityManagerInterface::class)->flush();
        foreach (['/', '/unternehmen', '/werbekreis/ueber-uns'] as $url) {
            $crawler = $this->client->request('GET', $url);
            self::assertResponseIsSuccessful();
            foreach (['header' => [240, 60], '.site-footer' => [220, 70]] as $selector => [$defaultWidth, $defaultHeight]) {
                $style = $crawler->filter($selector.' .brand img')->attr('style');
                self::assertSame($selector === 'header'
                    ? '--portal-logo-max-width: '.($width ?? $defaultWidth).'px; --portal-logo-max-height: '.($height ?? $defaultHeight).'px;'
                    : '--portal-footer-logo-max-width: 220px; --portal-footer-logo-max-height: 70px;', $style);
            }
        }
    }
}
