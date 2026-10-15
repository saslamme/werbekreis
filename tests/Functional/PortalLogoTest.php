<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\DataFixtures\CmsPreservingPurgerFactory;
use App\Repository\PortalSettingsRepository;
use App\Service\PortalAssetStorage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class PortalLogoTest extends DirectoryDatabaseTestCase
{
    private array $temporary = [];
    protected function setUp(): void { parent::setUp(); $this->resetLogo(); }
    protected function tearDown(): void
    {
        $this->resetLogo();
        (new Filesystem())->remove($this->temporary);
        parent::tearDown();
    }
    private function resetLogo(): void
    {
        $settings = static::getContainer()->get(PortalSettingsRepository::class)->getSettings();
        static::getContainer()->get(PortalAssetStorage::class)->save($settings, null, true);
    }
    private function upload(string $format = 'png'): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'portal-functional-');
        $this->temporary[] = $path;
        if ($format === 'svg') { file_put_contents($path, '<svg xmlns="http://www.w3.org/2000/svg" width="240" height="60"><rect width="240" height="60" fill="#214d3b"/></svg>'); }
        else { $image = imagecreatetruecolor(240,60); imagepng($image, $path); }
        return new UploadedFile($path, 'Website.'.$format, test: true);
    }
    private function submit(?UploadedFile $file = null, bool $remove = false): void
    {
        $this->client->request('GET', '/admin/settings');
        $values = $this->client->getCrawler()->selectButton('Speichern')->form()->getPhpValues();
        if ($remove) { $values['portal_settings']['removeLogo'] = '1'; }
        $this->client->request('POST', '/admin/settings', $values, $file ? ['portal_settings' => ['logoFile' => $file]] : []);
    }
    public function testAdminUploadReplaceRemoveAndPublicBranding(): void
    {
        $this->login('admin');
        $this->client->request('GET', '/admin/settings');
        self::assertSelectorTextContains('.admin-panel', 'Branding');
        self::assertSelectorNotExists('input[name="portal_settings[removeLogo]"]');
        $settings = static::getContainer()->get(PortalSettingsRepository::class)->getSettings();
        $settings->setFacebookUrl('https://facebook.com/test');
        static::getContainer()->get(EntityManagerInterface::class)->flush();
        $this->submit($this->upload());
        self::assertResponseRedirects('/admin/settings', 303);
        $first = static::getContainer()->get(PortalSettingsRepository::class)->getSettings()->getLogoFileName();
        $this->client->followRedirect();
        self::assertSelectorExists('.admin-logo-preview');
        self::assertSelectorTextContains('.admin-panel', 'Website.png');
        $this->assertPublicLogo($first);
        $this->client->request('GET', '/media/portal/logo');
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'image/png');
        self::assertResponseHeaderSame('X-Content-Type-Options', 'nosniff');
        $etag = $this->client->getResponse()->headers->get('ETag');
        $this->client->request('GET', '/media/portal/logo', server: ['HTTP_IF_NONE_MATCH' => $etag]);
        self::assertResponseStatusCodeSame(304);
        // Upload wins over removal; old cache keys must still serve only the current logo.
        $this->submit($this->upload('svg'), true);
        self::assertResponseRedirects('/admin/settings', 303);
        $second = static::getContainer()->get(PortalSettingsRepository::class)->getSettings()->getLogoFileName();
        self::assertNotSame($first, $second);
        $storage = static::getContainer()->get(PortalAssetStorage::class);
        self::assertFileDoesNotExist($storage->path($first));
        self::assertFileExists($storage->path($second));
        $this->client->request('GET', '/media/portal/logo?v='.$first, server: ['HTTP_IF_NONE_MATCH' => $etag]);
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'image/svg+xml');
        $this->assertPublicLogo($second);
        $this->submit(null, true);
        self::assertResponseRedirects('/admin/settings', 303);
        self::assertFileDoesNotExist($storage->path($second));
        $settings = static::getContainer()->get(PortalSettingsRepository::class)->getSettings();
        self::assertNull($settings->getLogoFileName());
        self::assertNull($settings->getLogoOriginalName());
        self::assertSame('https://facebook.com/test', $settings->getFacebookUrl());
        $this->assertFallback();
        $this->client->request('GET', '/media/portal/logo');
        self::assertResponseStatusCodeSame(404);
    }
    private function assertPublicLogo(string $name): void
    {
        foreach (['/', '/unternehmen', '/werbekreis/ueber-uns'] as $path) {
            $this->client->request('GET', $path);
            self::assertResponseIsSuccessful();
            foreach (['header', '.site-footer'] as $selector) {
                self::assertSelectorExists($selector.' .brand img[src="/media/portal/logo?v='.$name.'"][alt="Werbekreis Haselünne"]');
                self::assertSelectorTextSame($selector.' .brand', '');
                self::assertSelectorExists($selector.' .brand[href="/"]');
            }
            self::assertSelectorExists('.site-footer .social-link[href="https://facebook.com/test"]');
        }
    }
    private function assertFallback(): void
    {
        $this->client->request('GET', '/');
        foreach (['header', '.site-footer'] as $selector) {
            self::assertSelectorNotExists($selector.' .brand img');
            self::assertSelectorTextContains($selector.' .brand', 'Werbekreis');
            self::assertSelectorTextContains($selector.' .brand span', 'Haselünne');
        }
    }
    public function testMissingFileAndTraversalReturn404(): void
    {
        $this->assertFallback();
        $settings = static::getContainer()->get(PortalSettingsRepository::class)->getSettings();
        $settings->setLogoFileName(str_repeat('f',32).'.png');
        static::getContainer()->get(EntityManagerInterface::class)->flush();
        foreach (['/media/portal/logo', '/media/portal/example.png', '/media/portal/%2e%2e/.env'] as $path) {
            $this->client->request('GET', $path);
            self::assertResponseStatusCodeSame(404);
        }
        // Even legacy/corrupt database metadata cannot escape the storage directory.
        static::getContainer()->get(EntityManagerInterface::class)->getConnection()->executeStatement('UPDATE PortalSettings SET logoFileName = ? WHERE id = 1', ['../../.env']);
        $this->client->request('GET', '/media/portal/logo');
        self::assertResponseStatusCodeSame(404);
        static::getContainer()->get(EntityManagerInterface::class)->getConnection()->executeStatement('UPDATE PortalSettings SET logoFileName = NULL WHERE id = 1');
        static::getContainer()->get(EntityManagerInterface::class)->clear();
    }
    public function testInvalidUploadPreservesExistingLogoAndSocialSettings(): void
    {
        $this->login('admin');
        $this->submit($this->upload());
        $old = static::getContainer()->get(PortalSettingsRepository::class)->getSettings()->getLogoFileName();
        $invalid = $this->upload('svg');
        file_put_contents($invalid->getPathname(), '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"/>');
        $this->submit($invalid, true);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.invalid-feedback', PortalAssetStorage::UPLOAD_ERROR);
        static::getContainer()->get(EntityManagerInterface::class)->clear();
        $settings = static::getContainer()->get(PortalSettingsRepository::class)->getSettings();
        self::assertSame($old, $settings->getLogoFileName());
        self::assertFileExists(static::getContainer()->get(PortalAssetStorage::class)->path($old));
    }
    public function testPhpUploadLimitUsesTheRequiredErrorMessage(): void
    {
        $this->login('admin');
        $file = $this->upload();
        $this->submit(new UploadedFile($file->getPathname(), 'large.png', error: UPLOAD_ERR_INI_SIZE, test: true));
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.invalid-feedback', PortalAssetStorage::UPLOAD_ERROR);
        self::assertNull(static::getContainer()->get(PortalSettingsRepository::class)->getSettings()->getLogoFileName());
    }
    public function testFixturePurgerRetainsLogoMetadataAndFile(): void
    {
        $settings = static::getContainer()->get(PortalSettingsRepository::class)->getSettings();
        static::getContainer()->get(PortalAssetStorage::class)->save($settings, $this->upload());
        $name = $settings->getLogoFileName();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        (new CmsPreservingPurgerFactory())->createForEntityManager(null, $em)->purge();
        $em->clear();
        self::assertSame($name, static::getContainer()->get(PortalSettingsRepository::class)->getSettings()->getLogoFileName());
        self::assertFileExists(static::getContainer()->get(PortalAssetStorage::class)->path($name));
    }
}
